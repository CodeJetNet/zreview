---
name: testing-strategy
description: Use when authoring or updating the Testing Strategy section of a JIRA ticket for AllDigitalRewards work. Provides the v3 template (Step 0 + Action + Expected Result + Wait Condition + Actual Result format), coverage matrices, mandatory Newman API rules, QA-observability constraints (browser DOM/console/network + public HTTP only — no DB or container access), Playwright locator precision rules, and the Pre-QA Handoff Checklist. Required reading before transitioning a ticket to "Ready for QA" or creating a PR via adr-developer. Triggers on phrases like "testing strategy", "write test cases", "add TCs to ticket", "pre-QA handoff", "ready for QA", "QA strategy", "Playwright test cases", "Newman collection".
---

# Testing Strategy (v3)

Canonical Testing Strategy template for AllDigitalRewards JIRA tickets. **Mandatory in every ticket — no exceptions for "small," "config-only," or "obvious" changes.** If code changes, the ticket has a Testing Strategy.

## When to use this skill

- Authoring a new JIRA ticket's Testing Strategy section
- Updating an existing ticket's Testing Strategy before a PR
- Before transitioning a ticket to "Ready for QA"
- Before creating a PR via the `adr-developer` skill — PR creation is BLOCKED until Section 10 Pre-QA Handoff Checklist is complete

## How to use it

1. **Read `references/template.md`** — the full v3 template
2. Copy it into the JIRA ticket description
3. Run **Section 0 ticket-size gate FIRST** — if you'd need 6+ TCs, stop and split the ticket before writing anything else
4. Fill out the template top-to-bottom
5. Run the **Adversarial Pre-Mortem** (Section 9) — separate Claude session generates 10 failure modes that the strategy doesn't cover; each becomes a new TC, a new public verification surface, or a written justification
6. Walk the **Pre-QA Handoff Checklist** (Section 10) — every box must be checked
7. Apply the **Cold-Read check** as the final gate (last item in Section 10) — could a stranger with zero prior context manually execute every TC top-to-bottom without asking a question? If no, fix the gap.
8. Only then transition the ticket to "Ready for QA"

## Non-negotiables (apply to every ticket)

1. **Step 0 zero-knowledge baseline per TC** — UI URL, API URL, role, account, credentials, auth flow, prerequisites, mocked services, tags. QA must replicate with no prior context. Cross-references allowed ("Same as TC1 Step 0") but the slot is required per TC.
2. **Every step has all four labeled components:** Action · Expected Result · Wait Condition · blank Actual Result. API uses `Request` / `Response` (equivalent labels). Linear block format — never tables for execution rows.
3. **Newman collection mandatory** for any API change. Lives at `tests/newman/<service>/`. The Playwright QA framework reads Newman directly to author API tests; markdown Request/Response in the ticket must mirror the Newman request exactly.
4. **QA verification surface is restricted:** browser DOM/URL/console/network + public HTTP API responses ONLY. **No DB queries. No container shell. No log access. No queue inspection.** State changes that are DB-only must either get a public surface added in this PR (Section 5d Externally-Observable State Mapping) or move to per-TC Dev-Only Verification.
5. **Visually-relevant UI steps include inline Expected + Actual screenshot slots** within the step block — pasted inline, NEVER uploaded as generic ticket attachments. Skip screenshot slots on steps with purely textual assertions (URL pattern, status code, response field, DOM attribute).
6. **`data-testid` fallback rule:** if a UI element lacks an accessible name, dev MUST add `data-testid` in the same PR. Never ship a TC whose locator depends on `nth-child`, class names, or XPath.
7. **Tags per TC:** `@smoke` (every commit) · `@regression` (nightly) · `@slow` (>30s) · `@flaky-quarantine` (with reason).
8. **Accessibility scan default-on** for UI TCs (`@axe-core/playwright`); zero violations except an explicitly tracked allowlist.
9. **Ticket size capped at 5 TCs.** If you'd need 6+, split the ticket before writing the strategy.
10. **Cold-read rule (final gate):** a stranger reading the ticket with zero prior context must be able to manually execute every TC top-to-bottom without asking a question. If they would need to ask, the TC is incomplete.

## Section map (what's in the template)

- **Section 0:** Ticket Size Gate (split trigger if 6+ TCs)
- **Section 1:** Behavior Change (Before / After / Primary Signal)
- **Section 2:** PR Reference & Coverage Map (every changed function → TC step or unit test)
- **Section 3:** Regression Impact (downstream consumers, risk level)
- **Section 4:** Dependencies & Deployment (smoke check before full TC suite)
- **Section 5:** Coverage Matrices — 5a Input Partitions · 5b Role × Action · 5c Failure-Mode Checklist (with State integrity line) · 5d Externally-Observable State Mapping
- **Section 6:** AC → Test Case Mapping
- **Section 7:** Test Cases (Step 0 + numbered Steps in unified linear block format)
- **Section 8:** OUT-OF-SCOPE Justifications ("why this cannot fail in prod")
- **Section 9:** Adversarial Pre-Mortem (10 failure modes the strategy doesn't cover)
- **Section 10:** Pre-QA Handoff Checklist (every box must be checked)
- **Section 11:** After QA Starts (don't push during QA, defect feedback loop)

## Why this exists

Every section in the v3 template exists because its absence caused a real prod incident or wasted QA cycle. Coverage matrices force enumeration so coverage doesn't depend on the dev's vigilance. The 4-component step format ensures Playwright automation is unambiguous. The QA-observability constraint matches the actual QA env reality (no DB, no container access). The Pre-QA Handoff Checklist is the gate that prevents shipping incomplete work.

Testing is the first of three layers of defense — paired with staged rollouts and the prod log monitors. This template makes the first layer maximally rigorous; the other layers catch what still slips through. Zero prod bugs is the target; no single layer reaches it alone.

## Reference

The complete v3 template lives at `references/template.md`. Always read it before authoring a Testing Strategy section.
