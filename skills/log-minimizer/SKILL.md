---
name: log-minimizer
description: Use after analyze-warning-logs or analyze-info-logs has tagged a report with verdicts. Builds a per-monitor-project knowledge base of log patterns and renders a sister markdown report grouping them by silence-candidate / pending / silenced / regressed / keep-loud / escalate. Triggers on phrases like "minimize logs", "update silence list", "log minimizer", "show silence candidates", "render the sister report", or after the user has just finished triaging a warning or info report.
---

# Log Minimizer

## Overview

Sister skill to analyze-warning-logs and analyze-info-logs. Once a parent report has a Verdict column populated, this skill:

1. Parses the latest parent report.
2. Updates `~/scripts/<project>/log-minimizer/patterns.json` (per-project knowledge base).
3. Renders `~/scripts/<project>/log-minimizer/reports/<same-timestamp>.md`.

Patterns accumulate state across runs. Once a `silence-candidate` pattern stops appearing in the report for ≥7 days, it auto-promotes to `silenced`. If a `silenced` pattern reappears, it flips to `regressed`.

The Python implementation lives at `~/scripts/log-minimizer/minimizer.py` and is shared between both sources. The skill is the trigger + summarization layer; classification is deterministic in code.

## When to Use

- User says "minimize logs", "update the silence list", "log minimizer", "render the sister report".
- A parent report (analyze-warning-logs or analyze-info-logs) has just been tagged with verdicts.
- User wants to override or annotate a pattern in the knowledge base.

**Do NOT use** before a parent report exists with a Verdict column. The skill will abort if the latest report is unverified.

## Workflow — fresh run

1. Determine source from user phrasing or context:
   - "warning report" / "error report" / mentions of `prod-stackdriver-errors` → `errors`.
   - "info report" / "notice logs" / mentions of `prod-stackdriver-info` → `info`.
   - If ambiguous, ask once.
2. Run:
   ```
   python3 ~/scripts/log-minimizer/minimizer.py --source=<errors|info>
   ```
3. Read the rendered sister report (path printed by the script).
4. Summarize for the user: counts per state, top silence-candidates by qty, any regressed patterns (highlight these — they're the alarm signal), any pending patterns awaiting classification.

## Workflow — manual override

When the user wants to reclassify a pattern (e.g., "mark a1b2c3d4 as keep-loud", "reset that one to pending"):

```
python3 ~/scripts/log-minimizer/minimizer.py --source=<errors|info> --override <8-char-prefix> --new-state <state>
python3 ~/scripts/log-minimizer/minimizer.py --source=<errors|info> --reset <8-char-prefix>
python3 ~/scripts/log-minimizer/minimizer.py --source=<errors|info> --note <8-char-prefix> "free-form text"
```

Valid states: `pending`, `silence-candidate`, `keep-loud`, `keep-loud-infra`, `escalate`, `silenced`, `regressed`.

The script resolves any unique prefix of the pattern ID (8 chars in the report tables is always unique).

## State Reference

| State | Meaning |
|---|---|
| `pending` | UNSURE in parent — needs your classification |
| `silence-candidate` | Safe to silence at the source code level (DOWNGRADE / REMOVE seed) |
| `keep-loud` | Real bug, never silence (INVESTIGATE / KEEP seed) |
| `keep-loud-infra` | Vendor / RabbitMQ / cert / credential issue (errors only, OPS seed) |
| `escalate` | INFO log that should be at WARNING (info only, KEEP+MIS-SEVERITY seed) |
| `silenced` | Silence-candidate that hasn't appeared for ≥7 days |
| `regressed` | Silenced pattern that reappeared — investigate |

## Output Format

The sister report has these sections (omitted entirely when count is 0):

- **Regressed** — top of file when present, the alarm
- **Silence-candidates** — the action list
- **Pending** — needs classification
- **Escalate** (info only) — promote to WARNING
- **Silenced** — recently disappeared, watch for regression
- **Keep-loud** — collapsed under `<details>`
- **Keep-loud-infra** (errors only) — collapsed under `<details>`

When summarizing for the user, lead with regressed (if any), then silence-candidates (top 5 by qty), then a one-line trend (volume share).

## Common Mistakes

- **Running before parent verdicts exist** → script aborts; tell the user to run analyze-warning-logs or analyze-info-logs first.
- **Mixing sources** → each project has its own knowledge base; do not point an `errors` invocation at the info project. The script enforces this via the `source` field in the JSON.
- **Re-running parent skill mid-cycle** → re-tagging the parent report changes verdicts, but the knowledge base only reseeds `pending` patterns. Non-pending patterns keep their state. Mention this to the user if it surprises them.
- **Treating regressed as "silencing failed"** → it can also mean "upstream behavior changed". Either way, worth investigating, but don't assume revert.
