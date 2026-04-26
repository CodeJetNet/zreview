---
name: analyze-info-logs
description: Use when reviewing the latest production sub-WARNING log report at ~/scripts/prod-service-info/info-reports/ to identify log lines that can be removed to cut GCP Cloud Logging cost. Triggers on phrases like "analyze info logs", "which info logs can we drop", "review the info report", "find log lines to remove for cost", or after the prod-service-info monitor cron produces a new file.
---

# Analyze Info Logs

## Overview

Production INFO/NOTICE/DEBUG logs cost money on GCP. This skill classifies each row in the latest info-log grouping report into REMOVE / DOWNGRADE / KEEP / UNSURE based on diagnostic value vs. volume, then writes the verdicts back into the file as a new column. The user scans the rewritten report and decides which REMOVE rows are worth filing JIRA tickets to remove the log emit-site in the owning service's repo.

## When to Use

- User says "analyze info logs", "which logs can we cut", or similar.
- A new file has just landed in `~/scripts/prod-service-info/info-reports/`.
- User is reviewing GCP logging cost.

**Do NOT use** for `>=WARNING` reports (`error-reports/`) — those have different criteria. Use `analyze-warning-logs` instead.

## Workflow

1. Find the newest `*.md` in `~/scripts/prod-service-info/info-reports/`. Filenames are UTC timestamps; sort descending. If the directory is empty, abort and tell the user no report exists yet.
2. Read it. **Structural validation:** confirm the file contains a `**Summary:**` line and a markdown table with the expected `Qty | Service | Message | Exception | Date` header. If either is missing, abort — the file is malformed or someone changed the monitor's output format.
3. **Idempotency check** — if the table header row contains `| Verdict |`, ask the user whether to re-run (will overwrite prior verdicts) or skip. Do not silently overwrite.
4. Classify each row using the criteria below.
5. Compute:
   - Counts per verdict tag.
   - Estimated volume reduction = `sum(qty for REMOVE rows) / N`, where `N` is the "entries fetched" number from the existing `**Summary:**` line (not the sum of the table's Qty column — they may diverge if monitor.py is later changed to dedupe or sample). Rounded to nearest %.
6. Rewrite the file in place using the Edit tool:
   - Add a `**Verdicts:**` line directly below the existing `**Summary:**` line.
   - Append a `Verdict` column to the table header, separator row, and every data row.

## Verdict Criteria

| Tag | When to use |
|-----|-------------|
| **REMOVE** | Pure heartbeat / liveness / "expected thing happened" / "nothing to do" with no diagnostic content. Examples: `Worker heartbeat`, `Retry cycle complete`, `No failed orders to retry`, `Cache hit for product N`, `Request received` (duplicates GCP HTTP access log). |
| **DOWNGRADE** | Has some value but logged too verbosely. Move to DEBUG or sample at 1-10%. Examples: `Job started for participant N` at 287/day, `Processing row N of N` inside a batch loop. |
| **KEEP** | Volume justified by content. State-machine transitions (`Claim transitioned: PENDING → APPROVED`), audit/compliance trails (OFAC, tokenized PAN, financial events), deprecation telemetry (`Legacy endpoint hit detected`), low-volume business events, security/fraud audit (auth failures — even if mis-severity'd). |
| **UNSURE** | Log line too terse to judge from the message alone, or domain knowledge needed. Mark UNSURE — **do not** invent compound verdicts like `KEEP-IF-CONTEXTUAL` or `REMOVE-MAYBE`. |

**Reasoning style:** one line, ≤90 chars, lead with the *why* (the criterion that triggered the verdict). Bias toward decisive verdicts.

**Delimiter:** an em-dash (`—`, U+2014) separates the verdict tag from the reason — e.g., `REMOVE — duplicates GCP HTTP access log`. Do not substitute ASCII hyphens (`-`) or colons (`:`); the rendered table relies on consistent delimiters for at-a-glance scanning.

**Mis-severity flag:** If a log line at INFO looks like it should be at WARNING (failures, errors, exceptional outcomes that imply something went wrong), tag it `KEEP` and prepend the reason with `MIS-SEVERITY:`. This surfaces a separate axis of cleanup the user may want to file.

## Output Format

Before:
```
**Summary:** 4357 entries fetched, 71 groups

| Qty | Service | Message | Exception | Date |
| ---:| --- | --- | --- | --- |
| 812 | Rewardstack | Request received | — | 2026-04-27 |
| 89 | CardAccount | Card Authenticate Failure | — | 2026-04-27 |
```

After:
```
**Summary:** 4357 entries fetched, 71 groups
**Verdicts:** 1 REMOVE, 0 DOWNGRADE, 1 KEEP, 0 UNSURE — est. ~19% volume reduction if all REMOVEs land

| Qty | Service | Message | Exception | Date | Verdict |
| ---:| --- | --- | --- | --- | --- |
| 812 | Rewardstack | Request received | — | 2026-04-27 | REMOVE — duplicates GCP HTTP access log |
| 89 | CardAccount | Card Authenticate Failure | — | 2026-04-27 | KEEP — MIS-SEVERITY: failure event, belongs at WARNING |
```

Use the Edit tool. Match the existing table block exactly as `old_string`, supply the rewritten block as `new_string`. Single Edit call.

## Common Mistakes

- **Compound tags** (`KEEP-IF`, `REMOVE-MAYBE`) → always collapse to UNSURE.
- **Output to chat** instead of rewriting the file → must rewrite the file.
- **Skipping the Verdicts: summary line** → it's the at-a-glance signal the user reads first.
- **Forgetting the idempotency check** → re-running on a file that already has verdicts produces a malformed table.
- **Treating `failure`/`error` at INFO as REMOVE** → those belong at WARNING; tag KEEP with `MIS-SEVERITY:` reason prefix.
- **Padding UNSURE** to avoid commitment → if the criteria above clearly apply, commit to a verdict.
