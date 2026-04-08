# QA Error Monitor

Polls GCP Cloud Logging for QA5 cluster errors (PHP warnings, 500s, Slim errors), deduplicates them, and posts formatted summaries to Slack. Tracks error lifecycle (NEW, RECURRING, SPIKE, TRACKED) with optional JIRA integration.

## Prerequisites

- **PHP 8.1+** with `curl` extension
- **gcloud CLI** installed and on your `PATH`
- **GCP access** to project `green-talent-129607` (QA5 cluster)

## Setup

### 1. Authenticate with GCP

```bash
gcloud auth login
gcloud config set project green-talent-129607
```

### 2. Create config directory

```bash
mkdir -p ~/.config/qa-error-monitor
```

### 3. Add Slack webhook

Get an incoming webhook URL from Slack and save it:

```bash
echo -n "https://hooks.slack.com/services/YOUR/WEBHOOK/URL" > ~/.config/qa-error-monitor/slack-webhook
chmod 600 ~/.config/qa-error-monitor/slack-webhook
```

### 4. (Optional) JIRA integration

To enable automatic JIRA ticket sync and create-ticket links in Slack messages:

```bash
# Your JIRA email
echo -n "you@alldigitalrewards.com" > ~/.config/qa-error-monitor/jira-email

# Your JIRA account ID (find it at: https://alldigitalrewards.atlassian.net/rest/api/3/myself)
echo -n "YOUR_ACCOUNT_ID" > ~/.config/qa-error-monitor/jira-account-id

# JIRA API token (create at: https://id.atlassian.com/manage-profile/security/api-tokens)
echo -n "YOUR_API_TOKEN" > ~/.config/qa-error-monitor/jira-token
chmod 600 ~/.config/qa-error-monitor/jira-token
```

If these files are absent, the monitor runs without JIRA integration.

## Usage

```bash
# Run and post to Slack
php monitor.php

# Link a tracked error hash to a JIRA ticket
php monitor.php link <hash> DS-XXXXX

# Ignore an error (stop reporting it)
php monitor.php ignore <hash>
```

## Crontab

Run hourly during business hours (Mon-Fri, 8am-5pm ET):

```cron
0 8-17 * * 1-5 /opt/homebrew/bin/php /path/to/monitor.php >> /tmp/qa-error-monitor.log 2>&1
```

Adjust the PHP path (`which php`) and timezone as needed. Ensure `gcloud` is on your cron `PATH`:

```cron
PATH=/usr/local/bin:/opt/homebrew/bin:/usr/bin:/bin:$HOME/google-cloud-sdk/bin
```

## State

The monitor stores dedup state in `~/.config/qa-error-monitor/seen-errors.json`. This file tracks which errors have been seen, their counts, and linked JIRA tickets. Errors with no occurrences in 24 hours are automatically purged.

## Slack Message Format

```
NEW       - First time seen, no prior hash
SPIKE     - Count > 20 in one polling window
RECURRING - Seen before, no ticket linked
TRACKED   - Linked to a JIRA ticket
```

Silence = healthy. The monitor only posts when there are errors to report.
