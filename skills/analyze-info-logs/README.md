# analyze-info-logs

Claude Code skill that classifies production sub-WARNING log groups (REMOVE / DOWNGRADE / KEEP / UNSURE) so you can find log lines to drop and cut GCP Cloud Logging cost.

## Quick start

```bash
# 1. Get a fresh report (or wait for cron, if installed):
python3 ~/scripts/prod-stackdriver-info/monitor.py

# 2. In any Claude Code session, type one of:
analyze info logs
review the latest info report
which info logs can we drop
```

Claude routes to this skill via description match — there is **no slash command**. The skill reads the newest file in `~/scripts/prod-stackdriver-info/info-reports/`, applies the verdict criteria, and rewrites the file in place with a `Verdict` column and a `**Verdicts:**` summary line.

## Test without mutating the real report

```bash
cp ~/scripts/prod-stackdriver-info/info-reports/$(ls -t ~/scripts/prod-stackdriver-info/info-reports/ | head -1) /tmp/test-info-report.md
```

Then in Claude Code: `analyze the info report at /tmp/test-info-report.md`

## What the output looks like

A `**Verdicts:**` line is added below the existing `**Summary:**`, and a sixth column is appended to the table:

```
**Summary:** 92 entries fetched, 11 groups
**Verdicts:** 3 REMOVE, 0 DOWNGRADE, 4 KEEP, 3 UNSURE — est. ~30% volume reduction if all REMOVEs land

| Qty | Service | Message | Exception | Date | Verdict |
| ---:| --- | --- | --- | --- | --- |
| 49 | RA | Vendor Order Queue Processor | — | 2026-04-26 | UNSURE — message too terse, can't tell heartbeat vs per-order |
| 12 | Amazon_Fulfillment | Retry cycle complete | — | 2026-04-26 | REMOVE — pure heartbeat, no diagnostic content |
| 5 | CardAccount | Card Authenticate Failure | — | 2026-04-26 | KEEP — MIS-SEVERITY: failure event, belongs at WARNING |
```

**Verdict tags:**

- **REMOVE** — pure noise, file a JIRA to delete the emit site.
- **DOWNGRADE** — has value but logged too verbosely; sample or move to DEBUG.
- **KEEP** — volume justified. `MIS-SEVERITY:` prefix flags failure events that belong at WARNING+.
- **UNSURE** — log line too terse to judge; needs a human / domain knowledge.

The skill is **idempotent**: re-running on a file that already has a `Verdict` column prompts before overwriting.

## Files

- `SKILL.md` — the skill definition (verdict criteria, workflow, output format). This is what Claude reads.
- `README.md` — this file. Operator notes for you, not for Claude.

## How it gets here

Source of truth lives at `~/.claude/skills/analyze-info-logs/`. A PostToolUse hook in `~/.claude/settings.json` auto-syncs and pushes any edit to:

- https://github.com/CodeJetNet/zreview (Claude Code plugin repo)
- https://github.com/jmuto2/dotclaude (private dotfiles mirror)

## Related

- **Data source:** `~/scripts/prod-stackdriver-info/monitor.py` — fetches GCP Cloud Logging entries at `severity<WARNING` for 28 production services and writes the markdown report this skill consumes.
- **Sibling, not yet built:** `analyze-warning-logs` for `>=WARNING` reports in `~/scripts/prod-stackdriver-errors/error-reports/`. Different verdict tags (FIX / DEDUPE / DOWNGRADE / TRIAGE) because the goal differs — warnings are rarely candidates for deletion; the question is whether to fix the underlying bug or rate-limit the emit site.
