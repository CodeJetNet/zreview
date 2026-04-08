# Production Log Audit

Daily audit of production GCP logs (prod4 and card-services2 clusters). Categorizes errors by type (application bugs, SSL issues, FPM capacity, dead services, slow requests, timeouts) and posts a formatted summary to Slack.

## Prerequisites

- **Python 3.8+** (no pip dependencies)
- **gcloud CLI** installed and on your `PATH`
- **GCP access** to project `green-talent-129607` (prod4 and card-services2 clusters)

## Setup

### 1. Authenticate with GCP

```bash
gcloud auth login
gcloud config set project green-talent-129607
```

### 2. Create config directory

```bash
mkdir -p ~/.config/prod-log-audit
```

### 3. Add Slack webhook

Get an incoming webhook URL from Slack and save it:

```bash
echo -n "https://hooks.slack.com/services/YOUR/WEBHOOK/URL" > ~/.config/prod-log-audit/slack-webhook
chmod 600 ~/.config/prod-log-audit/slack-webhook
```

## Usage

```bash
# Run audit and post to Slack
python3 monitor.py

# Dry run — print report to stdout, don't post to Slack
python3 monitor.py --dry
```

## Crontab

Run daily at 8am ET:

```cron
0 8 * * * /usr/local/bin/python3 /path/to/monitor.py >> /tmp/prod-log-audit.log 2>&1
```

Adjust the Python path (`which python3`) as needed. Ensure `gcloud` is on your cron `PATH`:

```cron
PATH=/usr/local/bin:/opt/homebrew/bin:/usr/bin:/bin:$HOME/google-cloud-sdk/bin
```

## Report Categories

The audit groups errors into sections, ordered by severity:

| Category | What it catches |
|----------|----------------|
| **Application Bugs** | PHP warnings/fatals, HTTP 500s, Slim errors — grouped by service with ingress correlation |
| **Expired SSL Certificates** | Certificates past expiration date |
| **Missing Default SSL Certificate** | tls-ingress cert not found, falling back to generated |
| **PHP-FPM Capacity Exhaustion** | pm.max_children hit, busy spawning |
| **Dead Services** | No active endpoints for a service |
| **Slow Requests** | Requests flagged as executing too slowly |
| **Request Timeouts** | 60s+ latency entries |
| **Other** | Anything else with 2+ occurrences |

If no actionable errors are found, the report says "Clean day." and still posts to Slack.

## Noise Filtering

The script aggressively filters infrastructure noise: Fluent Bit logs, FPM startup notices, kube-probe health checks, cert-manager chatter, connection resets, and other non-actionable entries. The filter is defined in `LOG_FILTER` and `NOISE_PATTERNS` within the script.
