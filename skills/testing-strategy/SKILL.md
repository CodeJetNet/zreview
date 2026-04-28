---
name: testing-strategy
description: Use when authoring or updating the Testing Strategy section of a JIRA ticket for AllDigitalRewards work. Provides the v5 template (35-row test-angles matrix, 8-role RBAC matrix, Step 0 + Action + Expected Result + Wait Condition + Actual Result format, Newman two-tier API rules, QA-observability constraints — browser DOM/console/network + public HTTP only, no DB/Docker/Redis/queue admin, Auth-Lockout Protection, Dev-Provided Test Affordances contract, 9-convention style linter, Severity auto-escalation, the 22-item Pre-QA Handoff Checklist, "I Don't Know" Protocol — Blank vs Unknown vs N/A semantics, Field Reference appendix — 30 fields by category, Ticket Type Quick Reference appendix, Common Mistakes That Waste QA Cycles table). Required reading before transitioning a ticket to "Ready for QA" or creating a PR via adr-developer. Triggers on phrases like "testing strategy", "write test cases", "add TCs to ticket", "pre-QA handoff", "ready for QA", "QA strategy", "Playwright test cases", "Newman collection".
---

# Testing Strategy (v5)

Canonical Testing Strategy template for AllDigitalRewards JIRA tickets. **Mandatory in every ticket — no exceptions for "small," "config-only," or "obvious" changes.** If code changes, the ticket has a Testing Strategy.

## When to use this skill

- Authoring a new JIRA ticket's Testing Strategy section
- Updating an existing ticket's Testing Strategy before a PR
- Before transitioning a ticket to "Ready for QA"
- Before creating a PR via the `adr-developer` skill — PR creation is BLOCKED until Section 23 Pre-QA Handoff Checklist is complete

## How to use it

1. **Read `references/template.md`** — the full v4 template
2. Copy it into the JIRA ticket description
3. Run **Section 0 ticket-size gate FIRST** — apply the per-issue-type budget; if exceeded or any decomposition trigger applies, stop and split before writing anything else
4. Fill out the template top-to-bottom
5. Run the **Adversarial Pre-Mortem** (Section 20) with the minimum row count for the issue type (Story/Task ≥10 · Bug ≥5 · Sub-task ≥5 · Epic ≥15); each row resolves to either `→ TC<N>.Step<M>` (promote) or `_Accepted residual risk: <reason>_` (justify)
6. Run the **Style Linter** (Section 22) — all 9 conventions must pass
7. Walk the **Pre-QA Handoff Checklist** (Section 23) — every box must be checked
8. Apply the **Cold-Read check** as the final gate — could a stranger with zero prior context manually execute every TC top-to-bottom without asking a question? If no, fix the gap.
9. Only then transition the ticket to "Ready for QA"

## Non-negotiables (apply to every ticket)

1. **Step 0 zero-knowledge baseline per TC** — UI URL, API URL, role, account, credentials env-var (NEVER literal), auth flow, prerequisites, mocked services, tags, Verification GET (mandatory for state-changing API ops), cache-bypass mechanism (if cached). Cross-references allowed; the slot is required per TC.
2. **Every step has all four labeled components:** Action · Expected Result · Wait Condition · blank Actual Result. API uses `Request` / `Response`. Linear block format — never tables for execution rows.
3. **Newman two-tier rule** for any API change. Tier 1 = dev's authoritative `*.postman_collection.json` (contract/smoke). Tier 2 = QA-authored Playwright `request` (cross-program / multi-tab / UI+API). Markdown Request/Response in the ticket SHALL match the Newman request exactly.
4. **QA verification surface is restricted:** browser DOM/URL/console/network + public HTTP API responses + Gmail readback for `suhrobu+*@alldigitalrewards.com` + QA-controlled webhook receiver + read-only dashboards ONLY. **No DB queries. No container shell. No log access. No queue inspection. No Redis CLI. No Docker. No SSH. No kubectl.** Confirmed org-wide ABSOLUTE 2026-04-26.
5. **Hostname allowlist:** `*.adrqa.info`, `localhost`, `webhook.site`. **Denylist:** `*.alldigitalrewards.com`, `*.adrewards.com`, `*.rewardstack.com`. Production is OFF-LIMITS.
6. **Test data naming:** generated values SHALL contain `plrt`; emails SHALL start with `suhrobu+`.
7. **Visually-relevant UI steps include inline Expected + Actual screenshot slots** within the step block — pasted inline, NEVER uploaded as generic ticket attachments. UI TCs additionally require **3 dev-env reference screenshots** (pre-state, post-success, post-failure) at the TC level. Skip per-step screenshot slots on steps with purely textual assertions.
8. **`data-testid` fallback rule:** if a UI element lacks an accessible name, dev MUST add `data-testid` in the same PR. Never ship a TC whose locator depends on `nth-child`, class names, or XPath.
9. **35-row test-angles matrix (§9a)** — every angle marked YES has at least one TC mapping; every "Not applicable" has explicit justification. The skill SHALL refuse to ship if any YES has no TC.
10. **8 canonical roles RBAC matrix (§9c)** when any TC touches an auth-gated endpoint — superAdmin · admin · adminViewOnly · accounting · configuration · customerService · participantView · reporting. Plus cross-org IDOR (workspace-A user reaches workspace-B → 403/404, never 200).
11. **UI-vs-API surface alignment (§13):** if the AC describes user-facing behavior, the TC is `[UI]` or `[E2E]` — never `[API]`-only shortcut. The only sanctioned `[API]` exception is `POST /token` for setup auth.
12. **Auth-lockout protection (§13)** is mandatory if any TC touches a login endpoint. No bad-credential probes without justification — they trigger 15-min team-wide lockout.
13. **Dev-Provided Test Affordances (§19)** are the contract closing the QA Capability gap — if QA needs to verify behavior X and can't with their toolkit, dev SHALL expose a surface (audit-log API, cron manual-trigger, queue-message API, cache-bypass, etc.). No "[NOT-QA-TESTABLE]" without escalation.
14. **Pre-mortem promote-or-justify (§20):** every row resolves to either `→ TC<N>.Step<M>` or `_Accepted residual risk: <reason>_`. Unresolved rows block the ticket.
15. **Severity auto-escalation (§21):** IDOR / auth bypass / privilege escalation / SQL injection / XSS / CSRF / PII leak / plaintext credentials / unhandled-JS data loss / failed migration with no rollback / missing webhook signature → auto-BLOCKER. Author SHALL NOT downgrade without QA agreement.
16. **Tags per TC:** `@smoke` (every commit) · `@regression` (nightly) · `@slow` (>30s) · `@flaky-quarantine` (with reason) · `@known-defect(DS-NNNNN)` · `@gmail` · `@chat-hours-only` · `@live-email` · `@wcag` · `@batch` · `@critical`. `@live-email` is mandatory for any test posting to a real human-monitored inbox.
17. **Accessibility scan default-on** for UI TCs (`@axe-core/playwright`); zero critical/serious violations except an explicitly tracked allowlist.
18. **Ticket size budget per issue type (§0):** Story/Task ≤8 TCs · Bug ≤3 · Sub-task ≤5 · Epic = 0 (TCs live on children). Decompose if any trigger applies — touches 2+ repos / 2+ services / 2+ phases / has cross-cutting risks.
19. **Atomic single-bug rule:** one bug = one ticket. Different root cause / different code path / different symptom = different ticket. Never bundle.
20. **Style linter — 9 conventions (§22):** field naming · severity vocab (BLOCKER/HIGH/MEDIUM/LOW/INFO only) · "Done" definition · step granularity (1 action + 1 assertion) · locator strategy · test-data naming · full-URL format · env-var credential references · status-code precision (no "or"/"likely"/"non-2xx").
21. **Cold-read rule (final gate):** a stranger reading the ticket with zero prior context must be able to manually execute every TC top-to-bottom without asking a question. If they would need to ask, the TC is incomplete.

## Section map (what's in the v4 template)

- **§0:** Ticket Size Gate (per-issue-type budget; decomposition triggers)
- **§1:** Behavior Change (Before / After / Primary Signal)
- **§2:** PR Reference & Coverage Map (changed function → TC step or unit test) + Newman collection
- **§3:** Depends On (top-of-ticket — auto-derived from PR diff)
- **§4:** QA Environment (URL, deploy status, brand-new-domain flag, refresh cadence)
- **§5:** Test Data Required + Lifecycle (lifted to top — QA pre-seeds before Phase 2)
- **§6:** Regression Impact (downstream consumers, risk level)
- **§7:** Dependencies & Deployment · Mocking Policy (REAL/SANDBOX/MOCK) · Compliance Flags (PII/BIPA/PCI/HIPAA/COPPA/SOX/ADA)
- **§8:** Acceptance Criteria (numbered · atomic · observable · mapped)
- **§9:** Coverage Matrices — 9a 35-row test-angles · 9b Input Partitions · 9c 8-role RBAC · 9d Failure-Mode Checklist · 9e Externally-Observable State Mapping
- **§10:** AC ↔ TC two-way mapping (forward + reverse)
- **§11:** Test Cases — Unified Step Format (Step 0 + numbered Steps; per-TC required artifacts; 3 dev-env reference screenshots for UI)
- **§12:** Non-Functional Triggers (28-row PR-change matrix)
- **§13:** Auth-Lockout Protection (lockout-protected endpoints; bad-credential probe rules)
- **§14:** Audit Log + Observable Side-Effects (with QA-accessible verification surface per side-effect)
- **§15:** Error Message Content (exact text · i18n key · surface · accessibility attrs)
- **§16:** Migration Safety + Rollback
- **§17:** API Versioning + Backwards Compatibility
- **§18:** OUT-OF-SCOPE Justifications ("why this cannot fail in prod")
- **§19:** Dev-Provided Test Affordances (audit-log API, cron trigger, queue API, cache-bypass, lockout-clear, time-freeze, feature-flag toggle, etc.)
- **§20:** Adversarial Pre-Mortem (≥10 Story/Task · ≥5 Bug · ≥15 Epic; promote-or-justify per row)
- **§21:** Severity Escalation Rules (auto-BLOCKER triggers)
- **§22:** Style Linter (9 conventions)
- **§23:** Pre-QA Handoff Checklist (every box must be checked)
- **§24:** After QA Starts (don't push during QA, defect feedback loop)
- **Defect-report shape** (Bug-type / FAILED reports — Findings/Observations panel at content array index 1, Manual Repro mandatory, atomic single-bug rule)

## Why this exists

Every section in the v4 template exists because its absence caused a real prod incident or wasted QA cycle. The 35-row test-angles matrix forces enumeration so coverage doesn't depend on the dev's vigilance. The 8-role RBAC matrix catches privilege-escalation and IDOR before prod. The 4-component step format ensures Playwright automation is unambiguous. The QA-observability constraint matches QA's actual environment reality (no DB, no container access). The auth-lockout block protects the entire QA team. The dev-provided affordances close the QA Capability gap formally. The Pre-QA Handoff Checklist is the gate that prevents shipping incomplete work.

Testing is the first of three layers of defense — paired with staged rollouts and the prod log monitors. This template makes the first layer maximally rigorous; the other layers catch what still slips through. Zero prod bugs is the target; no single layer reaches it alone.

## Reference

The complete v4 template lives at `references/template.md`. Always read it before authoring a Testing Strategy section.

## Source of v4 changes

v4 integrates the QA team lead's normative spec (`qa-jira-ticket-spec.md` v1.6.3, 2026-04-27) and DS-11756 casebook. Added since v3: 35-row test-angles matrix, 8-canonical-roles RBAC, top-of-ticket Depends On / QA Environment / Test Data blocks, Non-Functional Triggers (28-row PR-change matrix), Auth-Lockout Protection, Dev-Provided Test Affordances contract, Audit Log + Side-Effects (QA-observable surfaces), Error Message Content, Migration Safety + Rollback, API Versioning, Compliance Flags (PII/BIPA/PCI/HIPAA/COPPA/SOX/ADA), Severity auto-escalation, 9-convention style linter, expanded tag inventory (`@known-defect/@gmail/@chat-hours-only/@live-email/@wcag/@batch/@critical`), 3 dev-env reference screenshots per UI TC, Newman two-tier framing, atomic single-bug rule, defect-report ADF structure with Findings panel at index 1, pre-mortem promote-or-justify per row, ticket size budget per issue type.
