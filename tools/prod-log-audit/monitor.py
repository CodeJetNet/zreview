#!/usr/bin/env python3
"""
Daily Production Log Audit — Local CLI

Queries GCP Cloud Logging for prod4 and card-services2 clusters,
categorizes errors, and posts a formatted summary to Slack.

Usage:
    python3 monitor.py          # Run audit and post to Slack
    python3 monitor.py --dry    # Run audit, print report, don't post
"""

import json
import os
import re
import subprocess
import sys
from collections import defaultdict
from datetime import datetime, timezone, timedelta
from io import StringIO
from pathlib import Path

GCP_PROJECT = "green-talent-129607"
WEBHOOK_FILE = Path.home() / ".config" / "prod-log-audit" / "slack-webhook"
LIMIT = 1000

LOG_FILTER = r'''
resource.type="k8s_container"
resource.labels.cluster_name=("card-services2" OR "prod4")

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

NOT "client request body is buffered to a temporary file"
NOT "Failed to parse latency field"
NOT "Error waiting for task"
NOT "rpc error: code = Unavailable"
NOT "NOTICE: [pool www]"
NOT "child exited with code 0"
NOT "SSL_do_handshake"

NOT textPayload:"No such file or directory"
NOT textPayload:"open() "
NOT textPayload:"kube-probe"
NOT textPayload:"uasexporter"
NOT textPayload:"access forbidden by rule"
NOT textPayload:"SSL_read() failed"

NOT jsonPayload.msg:"Failed to export metrics to Cloud Monitoring"
NOT jsonPayload.error:"code = Canceled"
NOT jsonPayload.message:"No domain found in rule HostRegexp"
NOT jsonPayload.message:"cert-manager/secret-for-certificate-mapper"
NOT textPayload=~"(Connection reset|Primary script unknown|Operation timed out|upstream timed out|failed to ptrace)"

NOT resource.labels.container_name:"catalog"
NOT resource.labels.container_name:"fluentbit"
'''


def load_webhook():
    """Load Slack webhook URL from config file."""
    if not WEBHOOK_FILE.exists():
        WEBHOOK_FILE.parent.mkdir(parents=True, exist_ok=True)
        print(f"Error: Slack webhook not found. Write it to:", file=sys.stderr)
        print(f"  {WEBHOOK_FILE}", file=sys.stderr)
        sys.exit(1)
    url = WEBHOOK_FILE.read_text().strip()
    if not url:
        print(f"Error: Slack webhook file is empty: {WEBHOOK_FILE}", file=sys.stderr)
        sys.exit(1)
    return url


def fetch_logs():
    """Fetch logs from GCP using gcloud CLI."""
    now = datetime.now(timezone.utc)
    since = now - timedelta(hours=24)
    timestamp_filter = f'timestamp >= "{since.isoformat()}"'
    full_filter = f"{LOG_FILTER}\n{timestamp_filter}"

    cmd = [
        "gcloud", "logging", "read",
        full_filter,
        f"--project={GCP_PROJECT}",
        "--format=json",
        f"--limit={LIMIT}",
    ]

    try:
        result = subprocess.run(cmd, capture_output=True, text=True, timeout=120)
    except subprocess.TimeoutExpired:
        print("Error: gcloud logging read timed out after 120s", file=sys.stderr)
        return None

    if result.returncode != 0:
        print(f"Error: gcloud logging read failed:\n{result.stderr}", file=sys.stderr)
        return None

    if not result.stdout.strip():
        return []

    return json.loads(result.stdout)


def extract_text(entry):
    """Extract the meaningful text from a log entry."""
    text = entry.get("textPayload", "")
    if not text:
        jp = entry.get("jsonPayload", {})
        text = (
            jp.get("message", "")
            or jp.get("msg", "")
            or jp.get("error", "")
            or json.dumps(jp)[:300]
        )
    return text


def normalize_text(text, container=""):
    """Normalize log text for grouping similar entries."""
    n = re.sub(r"\d{4}[-/]\d{2}[-/]\d{2}[T ]\d{2}:\d{2}:\d{2}[.\d]*", "TS", text)
    n = re.sub(r"\d+\.\d+\.\d+\.\d+", "IP", n)
    n = re.sub(r"pid \d+", "pid N", n)
    n = re.sub(r"#\d+", "#N", n)
    n = re.sub(
        r'request: "(?:GET|POST|PUT|DELETE|PATCH|OPTIONS|HEAD) [^"]*"',
        'request: "METHOD URL"',
        n,
    )
    upstream_match = re.search(r'"proxyUpstreamName":\s*"([^"]+)"', n)
    if upstream_match and '"upstreamStatus": "500"' in n:
        return f"UPSTREAM_500:{upstream_match.group(1)}"
    if re.search(r'"\s+50\d\s*$', n) and re.match(r"IP\s+-", n):
        return f"ACCESS_LOG_500:{container}"
    n = re.sub(r"child \d+", "child N", n)
    n = re.sub(r"executing too slow \([0-9.]+ sec\)", "executing too slow", n)
    return n[:250].strip()


NOISE_PATTERNS = [
    r"NOTICE: fpm is running",
    r"NOTICE: ready to handle connections",
    r"Phinx by CakePHP",
    r"using config parser",
    r"using config file",
    r"Fluent Bit v",
    r"Copyright.*Fluent Bit",
    r"CNCF sub-project",
    r"fluentbit\.io",
    r"^\s*[\[\]{}|_/\\,\d\s]+$",
    r"Processing entity ",
    r"Successfully deleted cache entries",
    r"Clearing all Metadata cache entries",
    r"Proxy classes generated",
    r"info\s+service/",
    r"info\s+healthcheck/",
    r"Received signal from OS",
    r"Starting shutdown",
    r"Stopping receivers",
    r"Neither --kubeconfig nor --master",
    r"manager goroutine exited.*null",
    r"leader election lost",
    r"change detected in proxy server count",
    r"Error watching metadata: context canceled",
    r"deprecated since v1\.14",
    r"coresToReplicas|nodesToReplicas|coresPerReplica",
    r"^\s*\d+\s*$",
    r"invalid ssh key entry",
]
NOISE_RE = re.compile("|".join(NOISE_PATTERNS), re.IGNORECASE)


def is_noise(text):
    return bool(NOISE_RE.search(text))


def categorize(text, container, namespace):
    """Return a category string for a log entry."""
    lower = text.lower()

    if "lua udp socket read timed out" in lower:
        return "infra_noise"
    if container in (
        "metrics-server", "core-metrics-exporter", "kubedns-metrics-collector",
        "konnectivity-agent", "autoscaler", "dnsmasq", "gke-metrics-agent",
        "cert-manager-webhook", "cert-manager-cainjector", "prometheus-metrics-collector",
    ):
        return "infra_noise"

    if "ssl certificate" in lower and "expired" in lower:
        return "ssl_expired"
    if "ssl certificate" in lower and "not found" in lower:
        return "ssl_missing"
    if "error loading custom default certificate" in lower:
        return "ssl_missing"

    if "pm.max_children" in lower or "seems busy" in lower:
        return "fpm_capacity"

    if "does not have any active endpoint" in lower:
        return "dead_service"
    if "no object matching key" in lower:
        return "dead_service"

    if "executing too slow" in lower:
        return "slow_request"

    if "latency" in lower and re.search(r'"latency":\s*"[^"]*6[0-9]\.\d+ s', text):
        return "timeout"

    if "php warning" in lower or "php fatal" in lower or "php notice" in lower:
        return "app_bug"

    if "could not open input file" in lower:
        return "app_bug"

    if '"upstreamstatus": "500"' in lower:
        return "app_bug"

    if re.search(r'"\s+50[0-9]\s*$', text):
        return "app_bug"

    if text.strip() in ("{}", ""):
        return "infra_noise"

    return "other"


def group_entries(entries):
    """Group log entries by normalized text, filtering noise."""
    groups = defaultdict(lambda: {
        "count": 0,
        "containers": set(),
        "namespaces": set(),
        "severities": set(),
        "first_seen": "",
        "last_seen": "",
        "sample": "",
        "category": "",
    })

    for entry in entries:
        text = extract_text(entry)
        if is_noise(text):
            continue

        container = entry.get("resource", {}).get("labels", {}).get("container_name", "unknown")
        namespace = entry.get("resource", {}).get("labels", {}).get("namespace_name", "unknown")
        severity = entry.get("severity", "unknown")
        ts = entry.get("timestamp", "")

        category = categorize(text, container, namespace)
        if category == "infra_noise":
            continue

        key = normalize_text(text, container)
        g = groups[key]
        g["count"] += 1
        g["containers"].add(container)
        g["namespaces"].add(namespace)
        g["severities"].add(severity)
        if not g["sample"]:
            g["sample"] = text[:400]
        g["category"] = category
        if not g["first_seen"] or ts < g["first_seen"]:
            g["first_seen"] = ts
        if not g["last_seen"] or ts > g["last_seen"]:
            g["last_seen"] = ts

    return groups


def format_service(g):
    ns = ", ".join(sorted(g["namespaces"]))
    ct = ", ".join(sorted(g["containers"]))
    return f"{ns}/{ct}"


def build_report(groups):
    """Build report as a string."""
    out = StringIO()
    p = lambda *args, **kwargs: print(*args, file=out, **kwargs)

    today = datetime.now(timezone.utc).strftime("%Y-%m-%d")
    p(f"**Production Errors — Past 24 Hours ({today})**\n")

    categorized = defaultdict(list)
    for key, g in groups.items():
        categorized[g["category"]].append(g)

    for cat in categorized:
        categorized[cat].sort(key=lambda x: x["count"], reverse=True)

    item_num = 0

    if categorized.get("app_bug"):
        p("**Application Bugs**\n")
        svc_bugs = defaultdict(lambda: {"count": 0, "php_errors": [], "http_500s": 0, "first": "", "last": ""})
        for g in categorized["app_bug"]:
            svc = format_service(g)
            sample = g["sample"]
            is_ingress_500 = '"upstreamStatus": "500"' in sample
            is_access_500 = bool(re.match(r'[\d.]+ -\s+\S+ "[A-Z]+ \S+" 50\d', sample))
            if is_ingress_500 or is_access_500:
                if is_ingress_500:
                    upstream_match = re.search(r'"proxyUpstreamName":\s*"([^"]+)"', sample)
                    if upstream_match:
                        raw = upstream_match.group(1)
                        raw = re.sub(r"-\d+$", "", raw)
                        svc = raw.replace("-", "/", 1)
                svc_bugs[svc]["http_500s"] += g["count"]
            else:
                truncated = sample[:300]
                if truncated not in svc_bugs[svc]["php_errors"]:
                    svc_bugs[svc]["php_errors"].append(truncated)
            svc_bugs[svc]["count"] += g["count"]
            if not svc_bugs[svc]["first"] or g["first_seen"] < svc_bugs[svc]["first"]:
                svc_bugs[svc]["first"] = g["first_seen"]
            if not svc_bugs[svc]["last"] or g["last_seen"] > svc_bugs[svc]["last"]:
                svc_bugs[svc]["last"] = g["last_seen"]

        for svc, info in sorted(svc_bugs.items(), key=lambda x: x[1]["count"], reverse=True):
            item_num += 1
            p(f"**{item_num}. {svc}** (x{info['count']} total)")
            for err in info["php_errors"]:
                p(f"- {err}")
            if info["http_500s"]:
                p(f"- {info['http_500s']} matching HTTP 500 responses from ingress/access logs")
            p(f"- First: {info['first'][:19]}Z | Last: {info['last'][:19]}Z")
            p()

    if categorized.get("ssl_expired"):
        p("**Expired SSL Certificates**\n")
        for g in categorized["ssl_expired"]:
            item_num += 1
            domain_match = re.search(r'"([^"]+)" expired \(([^)]+)\)', g["sample"])
            if domain_match:
                p(f"**{item_num}. {domain_match.group(1)}** — expired {domain_match.group(2)} (x{g['count']})")
            else:
                p(f"**{item_num}. SSL Expired** (x{g['count']})")
                p(f"- {g['sample'][:300]}")
        p()

    if categorized.get("ssl_missing"):
        total = sum(g["count"] for g in categorized["ssl_missing"])
        item_num += 1
        p("**Missing Default SSL Certificate**\n")
        p(f"**{item_num}. tls-ingress default cert not found** — falling back to generated cert (x{total})")
        p()

    if categorized.get("fpm_capacity"):
        p("**PHP-FPM Capacity Exhaustion**\n")
        fpm_by_service = defaultdict(lambda: {"count": 0, "max_children_sample": "", "busy_count": 0})
        for g in categorized["fpm_capacity"]:
            svc = format_service(g)
            fpm_by_service[svc]["count"] += g["count"]
            sample = g["sample"]
            if "max_children" in sample and not fpm_by_service[svc]["max_children_sample"]:
                fpm_by_service[svc]["max_children_sample"] = sample
            if "seems busy" in sample.lower():
                fpm_by_service[svc]["busy_count"] += g["count"]
        for svc, info in sorted(fpm_by_service.items(), key=lambda x: x[1]["count"], reverse=True):
            item_num += 1
            limit_match = re.search(r"setting \((\d+)\)", info["max_children_sample"])
            limit = limit_match.group(1) if limit_match else "unknown"
            parts = f"**{item_num}. {svc}** — limit: {limit}, hit x{info['count']}"
            if info["busy_count"]:
                parts += f" ({info['busy_count']} spawning bursts)"
            p(parts)
        p()

    if categorized.get("dead_service"):
        p("**Dead Services (No Active Endpoints)**\n")
        for g in categorized["dead_service"]:
            item_num += 1
            svc_match = re.search(r'Service "([^"]+)"', g["sample"])
            svc_name = svc_match.group(1) if svc_match else format_service(g)
            p(f"**{item_num}. {svc_name}** (x{g['count']})")
        p()

    if categorized.get("slow_request"):
        p("**Slow Requests**\n")
        slow_by_svc = defaultdict(lambda: {"count": 0, "sample": ""})
        for g in categorized["slow_request"]:
            svc = format_service(g)
            slow_by_svc[svc]["count"] += g["count"]
            if not slow_by_svc[svc]["sample"]:
                slow_by_svc[svc]["sample"] = g["sample"]
        for svc, info in sorted(slow_by_svc.items(), key=lambda x: x[1]["count"], reverse=True):
            item_num += 1
            p(f"**{item_num}. {svc}** (x{info['count']})")
            p(f"- {info['sample'][:300]}")
            p()

    if categorized.get("timeout"):
        p("**Request Timeouts**\n")
        for g in categorized["timeout"]:
            item_num += 1
            svc = format_service(g)
            p(f"**{item_num}. {svc}** (x{g['count']})")
            p(f"- {g['sample'][:300]}")
            p()

    if categorized.get("other"):
        other = [g for g in categorized["other"] if g["count"] >= 2]
        if other:
            p("**Other (2+ occurrences)**\n")
            for g in other:
                item_num += 1
                svc = format_service(g)
                p(f"**{item_num}. {svc}** (x{g['count']})")
                p(f"- {g['sample'][:300]}")
                p()

    if item_num == 0:
        p("No actionable errors found. Clean day.")

    return out.getvalue()


def markdown_to_slack(text):
    """Convert markdown bold **text** to Slack mrkdwn bold *text*."""
    return re.sub(r"\*\*(.+?)\*\*", r"*\1*", text)


def post_to_slack(webhook_url, report):
    """Post the report to Slack via incoming webhook using curl."""
    slack_text = markdown_to_slack(report)
    payload = json.dumps({"text": slack_text})
    result = subprocess.run(
        ["curl", "-s", "-o", "/dev/null", "-w", "%{http_code}",
         "-X", "POST", "-H", "Content-Type: application/json",
         "-d", payload, webhook_url],
        capture_output=True, text=True, timeout=30,
    )
    if result.stdout.strip() != "200":
        print(f"Error posting to Slack: HTTP {result.stdout.strip()}", file=sys.stderr)
        sys.exit(1)


def main():
    dry_run = "--dry" in sys.argv

    if not dry_run:
        webhook_url = load_webhook()

    print("Fetching production logs (past 24h)...", file=sys.stderr)
    entries = fetch_logs()

    if entries is None:
        sys.exit(1)

    print(f"Got {len(entries)} log entries, grouping...", file=sys.stderr)
    groups = group_entries(entries)
    report = build_report(groups)

    if dry_run:
        print(report)
    else:
        post_to_slack(webhook_url, report)
        print(f"Posted to Slack ({len(entries)} entries processed).", file=sys.stderr)


if __name__ == "__main__":
    main()
