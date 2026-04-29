---
name: analyze-warning-logs
description: Use when reviewing the latest production WARNING/ERROR log report at ~/scripts/prod-stackdriver-errors/error-reports/ to triage which rows deserve a JIRA ticket vs which are noise to downgrade. Triggers on phrases like "analyze warning logs", "analyze error logs", "triage prod errors", "review the error report", "which prod errors are actionable", or after the prod-stackdriver-errors monitor cron produces a new file.
---

# Analyze Warning Logs

## Overview

Production WARNING/ERROR logs need triage, not cost-cutting. This skill classifies each row in the latest warning-log grouping report into INVESTIGATE / DOWNGRADE / OPS / UNSURE based on whether the row is a real bug, predictable user-error noise, or an external/infra issue. The user scans the rewritten report and decides which INVESTIGATE rows deserve JIRA tickets and which DOWNGRADE rows deserve a severity-cleanup pass to reduce alert fatigue.

## When to Use

- User says "analyze warning logs", "triage the error report", "which prod errors should we file", or similar.
- A new file has just landed in `~/scripts/prod-stackdriver-errors/error-reports/`.
- User is reviewing the production alert stream for actionable items.

**Do NOT use** for sub-WARNING reports (`info-reports/`) — those are cost-driven, not triage. Use `analyze-info-logs` instead.

## Workflow

1. Find the newest `*.md` in `~/scripts/prod-service-errors/error-reports/`. Filenames are UTC timestamps; sort descending. If the directory is empty, abort and tell the user no report exists yet.
2. Read it. **Structural validation:** confirm the file contains a `**Summary:**` line and a markdown table with the expected `Qty | Service | Error Message | Exception | Date` header. If either is missing, abort — the file is malformed or someone changed the monitor's output format.
3. **Idempotency check** — if the table header row contains `| Verdict |`, ask the user whether to re-run (will overwrite prior verdicts) or skip. Do not silently overwrite.
4. Classify each row using the criteria below.
5. Compute:
   - Counts per verdict tag.
   - INVESTIGATE volume share = `sum(qty for INVESTIGATE rows) / N`, where `N` is the "entries fetched" number from the existing `**Summary:**` line.
   - DOWNGRADE volume share = `sum(qty for DOWNGRADE rows) / N`.
   - Both rounded to nearest %.
6. Rewrite the file in place using the Edit tool:
   - Add a `**Verdicts:**` line directly below the existing `**Summary:**` line.
   - Append a `Verdict` column to the table header, separator row, and every data row.

## Verdict Criteria

| Tag | When to use |
|-----|-------------|
| **INVESTIGATE** | Likely a real bug or system failure deserving a JIRA ticket. Recurring "Fatal" lines, unhandled exceptions, deadlocks, duplicate-key inserts (race condition / missing idempotency), broken service discovery, internal lookup chains failing, app-side data bugs (e.g. emoji rejected by non-utf8mb4 column), vendor order failures the app should have caught. High volume + no obvious user-error explanation = INVESTIGATE. |
| **DOWNGRADE** | Predictable user/business outcome logged too loud. Belongs at INFO/DEBUG; currently pollutes the alert stream. Examples: `Pin is inactive`/`not valid`, `Claim has expired`, `Invalid Claim code`, address validation failures, expired/invalid SSO tokens, "not enough points", catalog-filter outcomes (`Product has no offers`, `does not meet price requirements`, `has invalid price`), invalid phone/email input format. |
| **OPS** | Infra / external / credential issue outside app code. AMQP/RabbitMQ disconnects (`CONNECTION_FORCED`), SSL cert drift / self-signed certs from a vendor, vendor API timeouts, vendor-side 4xx/5xx with no app fix path, credential rotation needed (401 from third-party). File with infra team or vendor, not as an app-bug ticket. |
| **UNSURE** | Too terse to judge, exception truncated, or domain knowledge needed (e.g. is a `Domain not found` row a real config gap or bot probing?). Mark UNSURE — **do not** invent compound verdicts like `INVESTIGATE-IF-RECURRING` or `DOWNGRADE-MAYBE`. |

**Reasoning style:** one line, ≤90 chars, lead with the *why* (the criterion that triggered the verdict). Bias toward decisive verdicts.

**Delimiter:** an em-dash (`—`, U+2014) separates the verdict tag from the reason — e.g., `DOWNGRADE — user supplied inactive pin, system worked correctly`. Do not substitute ASCII hyphens (`-`) or colons (`:`); the rendered table relies on consistent delimiters for at-a-glance scanning.

**Pair detection:** When two or more rows share the same `Service` + `Error Message` but differ only in the `Exception` payload (e.g. multiple `RedemptionClient — Rewardstack Service: AvsVerifyAddress Failure` rows), they are the same emit-site / code path. Tag them consistently — splitting verdicts across copies of the same code path is incoherent.

**Linked-failure flag:** When two distinct rows describe the *upstream* and *downstream* of the same broken chain (e.g. `Hydrate Template Data Failure: Unable to fetch campaign` paired with `GetCampaignByDomain Failure 400`), tag both INVESTIGATE and prepend the reason with `LINKED:` so the reader sees they are one root cause, not two tickets.

## Output Format

Before:
```
**Summary:** 1986 entries fetched, 72 groups

| Qty | Service | Error Message | Exception | Date |
| ---:| --- | --- | --- | --- |
| 656 | RA | Incorrect Invoice Balance. | — | 2026-04-26 |
| 27 | RedemptionClient | Rewardstack Service: IsRedemptionPinValid Failure | … Pin is inact… | 2026-04-26 |
```

After:
```
**Summary:** 1986 entries fetched, 72 groups
**Verdicts:** 13 INVESTIGATE, 45 DOWNGRADE, 6 OPS, 8 UNSURE — ~55% volume INVESTIGATE-actionable, ~15% downgrade candidates

| Qty | Service | Error Message | Exception | Date | Verdict |
| ---:| --- | --- | --- | --- | --- |
| 656 | RA | Incorrect Invoice Balance. | — | 2026-04-26 | INVESTIGATE — high-volume invoice integrity issue, no diag detail |
| 27 | RedemptionClient | Rewardstack Service: IsRedemptionPinValid Failure | … Pin is inact… | 2026-04-26 | DOWNGRADE — user supplied inactive pin, system worked correctly |
```

Use the Edit tool. Match the existing table block exactly as `old_string`, supply the rewritten block as `new_string`. Single Edit call.

## Common Mistakes

- **Compound tags** (`INVESTIGATE-IF`, `DOWNGRADE-MAYBE`) → always collapse to UNSURE.
- **Output to chat** instead of rewriting the file → must rewrite the file.
- **Skipping the Verdicts: summary line** → it's the at-a-glance signal the user reads first.
- **Forgetting the idempotency check** → re-running on a file that already has verdicts produces a malformed table.
- **Treating user-error noise as INVESTIGATE** → `Invalid Pin`, `Expired Claim`, address validation, "not enough points" = DOWNGRADE; the system worked correctly, the user supplied bad input.
- **Treating real bugs as OPS to defer** → `Deadlock found`, `Duplicate entry` race conditions, unhandled application exceptions are app code, not infra.
- **Splitting verdicts across same-code-path rows** → multiple rows with identical Service+Message but different Exception payloads = same emit-site; tag consistently.
- **Padding UNSURE** to avoid commitment → if the criteria above clearly apply, commit to a verdict.
