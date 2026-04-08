#!/usr/bin/env php
<?php

declare(strict_types=1);

final class QaErrorMonitor
{
    private const GCP_PROJECT = 'green-talent-129607';
    private const FRESHNESS = '60m';
    private const LOG_LIMIT = 1000;
    private const SPIKE_THRESHOLD = 20;
    private const STALE_HOURS = 24;
    private const STATE_DIR = '.config/qa-error-monitor';
    private readonly string $stateFile;
    private const MAX_SIGNATURE_LENGTH = 80;
    private const MAX_DISPLAY_LENGTH = 200;
    private const JIRA_BASE = 'https://alldigitalrewards.atlassian.net';
    private const JIRA_PROJECT_ID = '10033';
    private const JIRA_BUG_TYPE_ID = '10010';
    private const JIRA_LABEL = 'qa-error-monitor';
    private const JIRA_CLOUD_ID = 'f33afec9-94f4-4034-85cd-d9ff50b1f73b';
    private readonly string $jiraEmail;
    private readonly string $jiraAccountId;

    /** Max seconds between log entries to consider them part of the same error */
    private const REASSEMBLY_WINDOW_SECONDS = 2;
    private const GCP_TIMEOUT_SECONDS = 30;

    private const GCP_FILTER = <<<'FILTER'
resource.type="k8s_container"
resource.labels.cluster_name="qa5"

(
  severity >= WARNING
  OR labels.stream="stderr"
  OR textPayload:"PHP"
  OR textPayload:"Slim"
  OR httpRequest.status >= 500
  OR textPayload=~"\" 50[0-9]"
)

NOT textPayload=~"\" (200|201|202|204|301|302|304|400|401|403|404|405|422|423|429)"
NOT httpRequest.status=(200 OR 201 OR 202 OR 204 OR 301 OR 302 OR 304 OR 400 OR 401 OR 403 OR 404 OR 405 OR 422 OR 423 OR 429)

NOT textPayload:"NOTICE: [pool www]"

NOT jsonPayload.msg:"Failed to export metrics to Cloud Monitoring"
NOT jsonPayload.error:"code = Canceled"
NOT jsonPayload.msg:"Failed to process metrics"
NOT textPayload:"uasexporter"
NOT textPayload:"kube-probe"
NOT resource.labels.container_name="prometheus-metrics-collector"

NOT textPayload:"No such file or directory"
NOT textPayload:"open() "
NOT textPayload=~"(Connection reset|Primary script unknown|Operation timed out|upstream timed out|failed to ptrace)"

NOT jsonPayload.message:"No domain found in rule HostRegexp"
NOT jsonPayload.message:"cert-manager/secret-for-certificate-mapper"
NOT textPayload:"CardAccount.ERROR: Domain not found"
FILTER;

    /** @var array<string, array{signature: string, first_seen: string, last_seen: string, total_count: int, ticket?: string}> */
    private array $state;
    private readonly string $slackWebhook;

    public function __construct()
    {
        $configDir = $_SERVER['HOME'] . '/' . self::STATE_DIR;
        $this->stateFile = $configDir . '/seen-errors.json';

        $this->state = $this->loadState();
        $this->slackWebhook = $this->readConfigFile($configDir . '/slack-webhook', 'Slack webhook');
        $this->jiraEmail = $this->readConfigFile($configDir . '/jira-email', 'JIRA email', required: false);
        $this->jiraAccountId = $this->readConfigFile($configDir . '/jira-account-id', 'JIRA account ID', required: false);
    }

    private function readConfigFile(string $path, string $label, bool $required = true): string
    {
        $value = trim(@file_get_contents($path) ?: '');

        if ($value === '' && $required) {
            fwrite(STDERR, "Error: {$label} not found. Write it to:\n");
            fwrite(STDERR, "  {$path}\n");
            exit(1);
        }

        return $value;
    }

    public function run(): void
    {
        $this->syncJiraTickets();

        $logEntries = $this->queryGcpLogs();

        if ($logEntries === null) {
            return; // Error already reported to Slack
        }

        if ($logEntries === []) {
            $this->purgeStaleEntries();
            $this->saveState();
            return; // Silence is healthy
        }

        $errors = $this->reassembleErrors($logEntries);

        if ($errors === []) {
            $this->purgeStaleEntries();
            $this->saveState();
            return;
        }

        $groups = $this->groupBySignature($errors);
        $classified = $this->classifyErrors($groups);

        $this->purgeStaleEntries();
        $this->saveState();

        $this->postToSlack($classified);
    }

    public function link(string $hash, string $ticket): void
    {
        if (!isset($this->state[$hash])) {
            fwrite(STDERR, "Error: hash '{$hash}' not found in state.\n");
            fwrite(STDERR, "Known hashes:\n");
            foreach ($this->state as $h => $entry) {
                fwrite(STDERR, "  {$h} — {$entry['signature']}\n");
            }
            exit(1);
        }

        $this->state[$hash]['ticket'] = $ticket;
        unset($this->state[$hash]['ignored']);
        $this->saveState();

        $entry = $this->state[$hash];
        $this->postSlackRaw(
            "✅ TRACKED | `{$hash}` | {$entry['signature']} (×{$entry['total_count']})"
            . " — ticket: <https://alldigitalrewards.atlassian.net/browse/{$ticket}|{$ticket}>"
        );

        echo "Linked {$hash} → {$ticket} ({$entry['signature']})\n";
    }

    public function ignore(string $hash): void
    {
        if (!isset($this->state[$hash])) {
            fwrite(STDERR, "Error: hash '{$hash}' not found in state.\n");
            fwrite(STDERR, "Known hashes:\n");
            foreach ($this->state as $h => $entry) {
                fwrite(STDERR, "  {$h} — {$entry['signature']}\n");
            }
            exit(1);
        }

        $this->state[$hash]['ignored'] = true;
        $this->saveState();

        echo "Ignored {$hash} ({$this->state[$hash]['signature']})\n";
    }

    private function syncJiraTickets(): void
    {
        $configDir = $_SERVER['HOME'] . '/' . self::STATE_DIR;
        $token = trim(@file_get_contents($configDir . '/jira-token') ?: '');

        if ($token === '' || $this->jiraEmail === '') {
            return; // JIRA not configured, skip sync
        }

        // Find unlinked hashes in state
        $unlinked = [];
        foreach ($this->state as $hash => $entry) {
            if (!isset($entry['ticket'])) {
                $unlinked[$hash] = true;
            }
        }

        if ($unlinked === []) {
            return; // Everything is already tracked
        }

        // Query JIRA for tickets with our label (using JQL search via POST)
        $url = "https://api.atlassian.com/ex/jira/" . self::JIRA_CLOUD_ID
            . "/rest/api/3/search/jql";

        $payload = json_encode([
            'jql' => 'labels = "' . self::JIRA_LABEL . '" ORDER BY created DESC',
            'fields' => ['key', 'description'],
            'maxResults' => 50,
        ]);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Basic ' . base64_encode($this->jiraEmail . ':' . $token),
                'Accept: application/json',
                'Content-Type: application/json',
            ],
            CURLOPT_TIMEOUT => 15,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 || $response === false) {
            fwrite(STDERR, "JIRA sync failed: HTTP {$httpCode}\n");
            return;
        }

        $data = json_decode($response, true);

        if (!isset($data['issues']) || !is_array($data['issues'])) {
            return;
        }

        foreach ($data['issues'] as $issue) {
            $key = $issue['key'] ?? '';
            $desc = $this->extractDescriptionText($issue['fields']['description'] ?? null);

            if (preg_match('/qa-monitor-hash:\s*([a-f0-9]{8})/', $desc, $matches)) {
                $hash = $matches[1];

                if (isset($this->state[$hash]) && !isset($this->state[$hash]['ticket'])) {
                    $this->state[$hash]['ticket'] = $key;
                    $this->saveState();

                    $entry = $this->state[$hash];
                    $this->postSlackRaw(
                        "✅ TRACKED | `{$hash}` | {$entry['signature']} (×{$entry['total_count']})"
                        . " — ticket: <https://alldigitalrewards.atlassian.net/browse/{$key}|{$key}>"
                    );
                }
            }
        }
    }

    private function extractDescriptionText(?array $adf): string
    {
        if ($adf === null) {
            return '';
        }

        // ADF (Atlassian Document Format) — recursively extract text nodes
        $text = '';

        if (isset($adf['text'])) {
            $text .= $adf['text'];
        }

        if (isset($adf['content']) && is_array($adf['content'])) {
            foreach ($adf['content'] as $node) {
                $text .= $this->extractDescriptionText($node);
            }
        }

        return $text;
    }

    private function queryGcpLogs(): ?array
    {
        $command = sprintf(
            'gcloud logging read %s --project=%s --freshness=%s --limit=%d --format=json 2>&1',
            escapeshellarg(self::GCP_FILTER),
            escapeshellarg(self::GCP_PROJECT),
            escapeshellarg(self::FRESHNESS),
            self::LOG_LIMIT,
        );

        $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);

        if (!is_resource($process)) {
            $this->postSlackRaw("⚠️ QA Error Monitor: Failed to launch gcloud process");
            return null;
        }

        $deadline = time() + self::GCP_TIMEOUT_SECONDS;
        $stdout = '';

        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        while (true) {
            $status = proc_get_status($process);

            if (!$status['running']) {
                $stdout .= stream_get_contents($pipes[1]);
                $stdout .= stream_get_contents($pipes[2]);
                break;
            }

            if (time() >= $deadline) {
                proc_terminate($process, 9);
                fclose($pipes[1]);
                fclose($pipes[2]);
                proc_close($process);
                $this->postSlackRaw("⚠️ QA Error Monitor: gcloud timed out after " . self::GCP_TIMEOUT_SECONDS . "s");
                return null;
            }

            $read = [$pipes[1], $pipes[2]];
            $write = null;
            $except = null;

            if (stream_select($read, $write, $except, 1) > 0) {
                foreach ($read as $pipe) {
                    $stdout .= fread($pipe, 8192);
                }
            }
        }

        $exitCode = $status['exitcode'];
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process);

        if ($exitCode !== 0) {
            $this->postSlackRaw("⚠️ QA Error Monitor: Failed to query GCP logs — {$stdout}");
            return null;
        }

        $entries = json_decode($stdout, true);

        if (!is_array($entries)) {
            $this->postSlackRaw("⚠️ QA Error Monitor: Failed to parse GCP log output");
            return null;
        }

        return $entries;
    }

    /**
     * GCP splits PHP errors into individual log entries per line.
     * Reassemble them by grouping entries from the same pod within a tight time window.
     *
     * @return list<array{service: string, type: string, message: string, file: string, line: string, timestamp: string}>
     */
    private function reassembleErrors(array $entries): array
    {
        // Group entries by pod_name, then sort by timestamp within each group
        $byPod = [];

        foreach ($entries as $entry) {
            $pod = $entry['resource']['labels']['pod_name'] ?? 'unknown';
            $text = $this->extractRawText($entry);
            $timestamp = $entry['timestamp'] ?? '';

            $byPod[$pod][] = [
                'text' => $text,
                'timestamp' => $timestamp,
                'service' => $entry['resource']['labels']['container_name'] ?? 'unknown',
            ];
        }

        $errors = [];

        foreach ($byPod as $podEntries) {
            // Sort by timestamp ascending
            usort($podEntries, static fn(array $a, array $b): int =>
                $a['timestamp'] <=> $b['timestamp']
            );

            // Group entries within the reassembly window
            $clusters = $this->clusterByTime($podEntries);

            foreach ($clusters as $cluster) {
                $error = $this->parseErrorCluster($cluster);

                if ($error !== null) {
                    $errors[] = $error;
                }
            }
        }

        return $errors;
    }

    /**
     * Group consecutive entries that are within REASSEMBLY_WINDOW_SECONDS of each other.
     *
     * @return list<list<array{text: string, timestamp: string, service: string}>>
     */
    private function clusterByTime(array $entries): array
    {
        if ($entries === []) {
            return [];
        }

        $clusters = [];
        $current = [$entries[0]];

        for ($i = 1, $count = count($entries); $i < $count; $i++) {
            $prevTime = strtotime($entries[$i - 1]['timestamp']);
            $currTime = strtotime($entries[$i]['timestamp']);

            if ($currTime !== false && $prevTime !== false
                && abs($currTime - $prevTime) <= self::REASSEMBLY_WINDOW_SECONDS) {
                $current[] = $entries[$i];
            } else {
                $clusters[] = $current;
                $current = [$entries[$i]];
            }
        }

        $clusters[] = $current;

        return $clusters;
    }

    /**
     * Parse a cluster of log lines into a structured error.
     * Handles both Slim-style structured errors and standalone PHP warnings.
     *
     * @return ?array{service: string, type: string, message: string, file: string, line: string, timestamp: string}
     */
    private function parseErrorCluster(array $cluster): ?array
    {
        $service = $cluster[0]['service'];
        $timestamp = $cluster[0]['timestamp'];
        $texts = array_map(static fn(array $e): string => $e['text'], $cluster);

        // Try to extract structured Slim error (Type/Message/File/Line fields)
        $type = '';
        $message = '';
        $file = '';
        $line = '';

        foreach ($texts as $text) {
            if (str_starts_with($text, 'Type: ')) {
                $type = substr($text, 6);
            } elseif (str_starts_with($text, 'Message: ')) {
                $message = substr($text, 9);
            } elseif (str_starts_with($text, 'File: ')) {
                $file = substr($text, 6);
            } elseif (str_starts_with($text, 'Line: ')) {
                $line = substr($text, 6);
            }
        }

        // If we found a structured error, use it
        if ($message !== '') {
            return [
                'service' => $service,
                'type' => $type,
                'message' => $message,
                'file' => $this->shortenPath($file),
                'line' => $line,
                'timestamp' => $timestamp,
            ];
        }

        // Otherwise, look for standalone PHP errors (not part of a Slim error)
        foreach ($texts as $text) {
            // Skip stack trace lines, headers, access logs, and metadata
            if ($this->isNoiseEntry($text)) {
                continue;
            }

            // Standalone PHP warning/notice/error or FPM warning
            if (preg_match('/^(?:PHP\s+)?(Warning|Fatal error|Notice|Error|WARNING):/i', $text)
                || preg_match('/^\[.*\]\s*WARNING:/', $text)) {
                $fileLine = $this->extractFileLine($text);

                return [
                    'service' => $service,
                    'type' => 'Warning',
                    'message' => $text,
                    'file' => $fileLine !== '' ? explode(':', $fileLine)[0] : '',
                    'line' => $fileLine !== '' ? explode(':', $fileLine)[1] ?? '' : '',
                    'timestamp' => $timestamp,
                ];
            }
        }

        // Single-entry cluster that isn't noise — treat as a standalone error
        if (count($texts) === 1 && !$this->isNoiseEntry($texts[0])) {
            return [
                'service' => $service,
                'type' => 'Error',
                'message' => $texts[0],
                'file' => '',
                'line' => '',
                'timestamp' => $timestamp,
            ];
        }

        return null;
    }

    private function isNoiseEntry(string $text): bool
    {
        // Stack trace lines
        if (preg_match('/^#\d+\s/', $text)) {
            return true;
        }

        // Trace header
        if (str_starts_with($text, 'Trace: #')) {
            return true;
        }

        // Slim Application Error header
        if (str_contains($text, 'Slim Application Error')) {
            return true;
        }

        // FPM access log (any status)
        if (preg_match('/^\d+\.\d+\.\d+\.\d+\s.*"\w+\s.*"\s\d{3}$/', $text)) {
            return true;
        }

        // Code: N
        if (preg_match('/^Code:\s*\d+$/', $text)) {
            return true;
        }

        // Bare File: and Line: (already captured in structured parsing)
        if (preg_match('/^(File|Line):\s/', $text)) {
            return true;
        }

        return false;
    }

    /**
     * @return array<string, array{signature: string, service: string, file_line: string, message: string, display_message: string, count: int}>
     */
    private function groupBySignature(array $errors): array
    {
        $groups = [];

        foreach ($errors as $error) {
            $normalized = $this->normalizeForSignature($error['message']);
            $signature = "{$error['service']} | {$normalized}";
            $hash = $this->hashSignature($signature);

            $fileLine = '';

            if ($error['file'] !== '' && $error['line'] !== '') {
                $fileLine = "{$error['file']}:{$error['line']}";
            } elseif ($error['file'] !== '') {
                $fileLine = $error['file'];
            }

            if (!isset($groups[$hash])) {
                $groups[$hash] = [
                    'signature' => $signature,
                    'service' => $error['service'],
                    'file_line' => $fileLine,
                    'message' => $normalized,
                    'display_message' => $error['message'],
                    'count' => 0,
                ];
            }

            $groups[$hash]['count']++;
        }

        return $groups;
    }

    private function extractRawText(array $entry): string
    {
        if (isset($entry['textPayload']) && is_string($entry['textPayload'])) {
            return trim($entry['textPayload']);
        }

        if (isset($entry['jsonPayload']['message']) && is_string($entry['jsonPayload']['message'])) {
            return trim($entry['jsonPayload']['message']);
        }

        return '';
    }

    private function extractFileLine(string $message): string
    {
        if (preg_match('/(\w+\.php)[: ](?:on )?line (\d+)/i', $message, $matches)) {
            return "{$matches[1]}:{$matches[2]}";
        }

        if (preg_match('/(\w+\.php):(\d+)/', $message, $matches)) {
            return "{$matches[1]}:{$matches[2]}";
        }

        return '';
    }

    private function shortenPath(string $path): string
    {
        // Strip /app/ prefix for cleaner output
        return preg_replace('#^/app/#', '', $path);
    }

    private function buildJiraCreateLink(string $hash, array $error): string
    {
        $summary = "[QA5] {$error['service']}";

        if ($error['file_line'] !== '') {
            $summary .= " — {$error['file_line']}";
        }

        $description = "Error detected by QA Error Monitor\n\n"
            . "*Service:* {$error['service']}\n"
            . "*Message:* {$error['display_message']}\n";

        if ($error['file_line'] !== '') {
            $description .= "*Location:* {$error['file_line']}\n";
        }

        $description .= "*First seen:* " . gmdate('Y-m-d H:i') . " UTC\n"
            . "*Occurrences:* ×{$error['count']}\n"
            . "\n---\nqa-monitor-hash: {$hash}\n";

        $params = http_build_query([
            'pid' => self::JIRA_PROJECT_ID,
            'issuetype' => self::JIRA_BUG_TYPE_ID,
            'summary' => $summary,
            'description' => $description,
            'labels' => self::JIRA_LABEL,
        ]);

        return self::JIRA_BASE . '/secure/CreateIssueDetails!init.jspa?' . $params;
    }

    private function normalizeForSignature(string $line): string
    {
        // Strip FPM-style timestamps: [25-Mar-2026 19:09:01]
        $line = preg_replace('/\[\d{2}-\w{3}-\d{4} \d{2}:\d{2}:\d{2}\]\s*/', '', $line);

        // Strip ISO timestamps
        $line = preg_replace('/\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}:\d{2}[.\dZ]*\s*/', '', $line);

        // Normalize PIDs, child IDs
        $line = preg_replace('/\bchild \d+\b/', 'child N', $line);
        $line = preg_replace('/\bpid\s*=?\s*\d+\b/i', 'pid N', $line);

        // Normalize memory addresses
        $line = preg_replace('/0x[0-9a-f]+/i', '0xN', $line);

        // Normalize large numeric IDs (8+ digits)
        $line = preg_replace('/\b\d{8,}\b/', 'N', $line);

        // Normalize "after N.N seconds"
        $line = preg_replace('/after [\d.]+ seconds/', 'after N seconds', $line);

        // Strip "called in /app/path on line N" trailer (keep the core error)
        $line = preg_replace('/, called in \/app\/\S+ on line \d+$/', '', $line);

        return trim($line);
    }

    /**
     * @return array<string, array{status: string, signature: string, service: string, file_line: string, message: string, count: int, ticket?: string}>
     */
    private function classifyErrors(array $groups): array
    {
        $now = gmdate('Y-m-d\TH:i:s\Z');
        $classified = [];

        foreach ($groups as $hash => $group) {
            if (!empty($this->state[$hash]['ignored'])) {
                continue;
            }

            $isNew = !isset($this->state[$hash]);
            $isSpike = $group['count'] > self::SPIKE_THRESHOLD;
            $ticket = $this->state[$hash]['ticket'] ?? null;

            if ($isSpike) {
                $status = 'SPIKE';
            } elseif ($isNew) {
                $status = 'NEW';
            } elseif ($ticket !== null) {
                $status = 'TRACKED';
            } else {
                $status = 'RECURRING';
            }

            if ($isNew) {
                $this->state[$hash] = [
                    'signature' => $group['signature'],
                    'first_seen' => $now,
                    'last_seen' => $now,
                    'total_count' => $group['count'],
                ];
            } else {
                $this->state[$hash]['last_seen'] = $now;
                $this->state[$hash]['total_count'] += $group['count'];
            }

            $classified[$hash] = [
                'status' => $status,
                'signature' => $group['signature'],
                'service' => $group['service'],
                'file_line' => $group['file_line'],
                'message' => $group['message'],
                'display_message' => $group['display_message'],
                'count' => $group['count'],
            ];

            if ($ticket !== null) {
                $classified[$hash]['ticket'] = $ticket;
            }
        }

        return $classified;
    }

    private function purgeStaleEntries(): void
    {
        $cutoff = time() - (self::STALE_HOURS * 3600);

        foreach ($this->state as $hash => $entry) {
            $lastSeen = strtotime($entry['last_seen']);

            if ($lastSeen !== false && $lastSeen < $cutoff) {
                unset($this->state[$hash]);
            }
        }
    }

    private function postToSlack(array $classified): void
    {
        $statusOrder = ['SPIKE' => 0, 'NEW' => 1, 'TRACKED' => 2, 'RECURRING' => 3];

        uasort($classified, static fn(array $a, array $b): int =>
            ($statusOrder[$a['status']] ?? 99) <=> ($statusOrder[$b['status']] ?? 99)
        );

        $lines = [];
        $totalCount = 0;

        $icons = [
            'NEW' => '🔴 NEW',
            'SPIKE' => '🚨 SPIKE',
            'RECURRING' => '🟡 RECURRING',
            'TRACKED' => '✅ TRACKED',
        ];

        foreach ($classified as $hash => $error) {
            $hash = (string) $hash;
            $icon = $icons[$error['status']] ?? $error['status'];
            $filePart = $error['file_line'] !== '' ? " | {$error['file_line']}" : '';
            $msg = $this->truncate($error['display_message'], self::MAX_DISPLAY_LENGTH);

            $suffix = '';

            if (isset($error['ticket'])) {
                $suffix = " — ticket: <https://alldigitalrewards.atlassian.net/browse/{$error['ticket']}|{$error['ticket']}>";
            } elseif (in_array($error['status'], ['NEW', 'SPIKE'], true)) {
                $jiraLink = $this->buildJiraCreateLink($hash, $error);
                $suffix = " — <{$jiraLink}|Create ticket>";
            }

            $lines[] = "{$icon} | `{$hash}` | {$error['service']}{$filePart} | {$msg} (×{$error['count']}){$suffix}";
            $totalCount += $error['count'];
        }

        $uniqueCount = count($classified);
        $lines[] = '';
        $lines[] = "{$uniqueCount} unique errors | {$totalCount} total occurrences | Last 60 min | QA5";

        $this->postSlackRaw(implode("\n", $lines));
    }

    private function postSlackRaw(string $text): void
    {
        $payload = json_encode(['text' => $text], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $ch = curl_init($this->slackWebhook);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error !== '') {
            fwrite(STDERR, "Slack webhook failed: {$error}\n");
            return;
        }

        if ($httpCode !== 200) {
            fwrite(STDERR, "Slack webhook returned HTTP {$httpCode}: {$response}\n");
        }
    }

    private function loadState(): array
    {
        if (!file_exists($this->stateFile)) {
            return [];
        }

        $content = file_get_contents($this->stateFile);
        $state = json_decode($content, true);

        if (!is_array($state)) {
            fwrite(STDERR, "State file corrupt, resetting to empty.\n");
            return [];
        }

        return $state;
    }

    private function saveState(): void
    {
        $dir = dirname($this->stateFile);

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents(
            $this->stateFile,
            json_encode($this->state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n",
        );
    }

    private function hashSignature(string $signature): string
    {
        return substr(md5($signature), 0, 8);
    }

    private function truncate(string $text, int $maxLength): string
    {
        if (mb_strlen($text) <= $maxLength) {
            return $text;
        }

        return mb_substr($text, 0, $maxLength - 1) . '…';
    }
}

// --- CLI entry point ---

$monitor = new QaErrorMonitor();

match ($argv[1] ?? null) {
    'link' => (function () use ($monitor, $argv) {
        $hash = $argv[2] ?? null;
        $ticket = $argv[3] ?? null;
        if ($hash === null || $ticket === null) {
            fwrite(STDERR, "Usage: php monitor.php link <hash> <ticket>\n");
            exit(1);
        }
        $monitor->link($hash, $ticket);
    })(),
    'ignore' => (function () use ($monitor, $argv) {
        $hash = $argv[2] ?? null;
        if ($hash === null) {
            fwrite(STDERR, "Usage: php monitor.php ignore <hash>\n");
            exit(1);
        }
        $monitor->ignore($hash);
    })(),
    default => $monitor->run(),
};
