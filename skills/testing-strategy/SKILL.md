---
name: testing-strategy
description: Use when authoring or updating the Testing Strategy section of a JIRA ticket for AllDigitalRewards work. Provides the v7 universal standard (7 Anchor Rules, 40-Ask Contract, 3 Universal Systemic Blockers, UI/WCAG/Performance ticket-type addenda, 35-row test-angles matrix, 8-role RBAC matrix, role-based auth — never invent env-var names, URL verification rule, Phase 4 Merge Gate banner, "What changes after merge" block, Step 0 + Action + Expected Result + Wait Condition + Actual Result format, Newman two-tier API rules, QA-observability constraints — browser DOM/console/network + Gmail readback + webhook receiver only, no DB/Docker/Redis/queue admin, Auth-Lockout Protection, Dev-Provided Test Affordances contract, 9-convention style linter, Severity + Priority — BLOCKER/HIGH/MEDIUM/LOW/INFO + P1/P2/P3, 35-item Pre-Submit Self-Check with checkbox-only-when-real semantics, "I Don't Know" Protocol — Blank vs Unknown vs N/A semantics, feature-flag state per TC, seed mechanism per entity, real-world side-effects + cleanup plan, browser/device matrix per UI TC, API schema diff + webhook payload schema, logging surfaces declaration, external service sandbox config, escalation channel, audit log expectations, browser console state, Newman/Postman collection link, in/out-of-scope, canonical CI tags `@reads-real-email`/`@needs-online-agent`/`@sends-real-email` — never the stale `@gmail`/`@chat-hours-only`/`@live-email`, TS-in-comments discoverability pointer, conventions for date/time/locale/currency/idempotency/webhook-retry, Field Reference appendix, Ticket Type Quick Reference appendix, Common Mistakes That Waste QA Cycles table, verified QA URL/env-var/CI tag inventory + pre-existing test participants `stan12121212` PROTECTED). Required reading before transitioning a ticket to "Ready for QA" or creating a PR via adr-developer. Triggers on phrases like "testing strategy", "write test cases", "add TCs to ticket", "pre-QA handoff", "ready for QA", "QA strategy", "Playwright test cases", "Newman collection".
---

# Testing Strategy (v7)

Canonical Testing Strategy template for AllDigitalRewards JIRA tickets. **Mandatory in every ticket — no exceptions for "small," "config-only," or "obvious" changes.** If code changes, the ticket has a Testing Strategy.

## The 7 Anchor Rules (every ticket SHALL satisfy)

Stan's universal framing. Every ticket SHALL be:

1. **SHORT** — Epic + many small child Tasks when work has multiple parts; never fat single tickets. Each child = ONE concern. Single-concern tickets (one PR, one feature) are also fine — bundling is the failure mode, not splitting.
2. **SELF-CONTAINED** — executable by anyone with zero prior knowledge. Literal URLs (full `https://...`), exact role names from the 8 canonical roles, exact button labels.
3. **TESTABLE BY QA'S ACTUAL TOOLKIT** — HTTPS, Playwright (UI + axe-core), Newman, Gmail readback, webhook.site receivers. **NOT** database access, **NOT** Docker, **NOT** Redis CLI, **NOT** queue admin.
4. **CONCRETE on EXPECTED RESULTS** — every step has ONE literal expected result that becomes the Playwright/Newman test assertion. No expected result = no assertion = weak test.
5. **REAL-USER PERSPECTIVE for UI** — UI tests go through the browser exactly like a real user. Never bypass the UI via API for setup; that hides UI bugs.
6. **NEVER HITS PROD** — every URL, env-var, test-data value, payload, and webhook target placed in a ticket MUST be verified as pointing to QA, not production.
7. **SELF-CHECKED BEFORE FLIP** — dev runs the 35-item Pre-Submit Self-Check before moving status to "Ready for QA".

## When to use this skill

- Authoring a new JIRA ticket's Testing Strategy section
- Updating an existing ticket's Testing Strategy before a PR
- Before transitioning a ticket to "Ready for QA"
- Before creating a PR via the `adr-developer` skill — PR creation is BLOCKED until Section 23 Pre-QA Handoff Checklist is complete

## Two reference files — read both before authoring

- **`references/template.md`** — the full v7 template (sections 0-24 + ticket-type addenda + appendices)
- **`references/qa-environment-inventory.md`** — verified QA URL inventory, real env-var names, 8 canonical roles, canonical CI tag names, real QA artifact paths, pre-existing test participants (`stan12121212` PROTECTED), date/time/locale/currency/idempotency/webhook-retry conventions, process lessons. **Read this before posting any URL, env-var, role, or tag.** Inventing plausible-looking names ("BATCH_ADMIN_TOKEN", "qa-batch.alldigitalrewards.com") is the #1 most common defect Stan flags.

## How to use it

1. **Read both reference files** (template + QA environment inventory)
2. Copy the template into the JIRA ticket description
3. Run **Section 0 ticket-size gate FIRST** — apply the per-issue-type budget; if exceeded or any decomposition trigger applies, stop and split before writing anything else
4. Fill out the template top-to-bottom
5. **Verify every URL** posted in the ticket points to a QA environment (not prod) — check against `references/qa-environment-inventory.md`. There is no fixed allowlist; verification IS the rule.
6. **Cite the role name** in every TC Step 0 (`Authenticate as Super Admin`) — never invent env-var names like `SUPER_ADMIN_TOKEN` or `BATCH_ADMIN_TOKEN`. QA's `auth.setup.ts` handles credentials.
7. **Fill the §1a "What changes after merge" block** — the Pre-merge → Post-merge assertion target is QA's Phase 3 + Phase 4 verification target.
8. **Fill the §4a Phase 4 Merge Gate banner** — PR state + merge timestamp + deploy timestamp + commit SHA + DB migration verification (or `[BLOCKED]` markers if not yet runnable). Run `gh pr view <N> --json state,isDraft,mergedAt,updatedAt` first; don't trust memory.
9. Run the **Adversarial Pre-Mortem** (Section 20) with the minimum row count for the issue type (Story/Task ≥10 · Bug ≥5 · Sub-task ≥5 · Epic ≥15); each row resolves to either `→ TC<N>.Step<M>` (promote) or `_Accepted residual risk: <reason>_` (justify)
10. Run the **Style Linter** (Section 22) — all 9 conventions must pass
11. Walk the **Pre-QA Handoff Checklist** (Section 23) — every box checked **only when the underlying condition is actually met** (no pre-checking on emit). Items confirmed at emit-time may be `[x]`; condition-gated items stay `[ ]` and only flip when the condition is real.
12. Apply the **Cold-Read check** as the final gate — could a stranger with zero prior context manually execute every TC top-to-bottom without asking a question? If no, fix the gap.
13. **If TS lives in JIRA comments** (because the description would exceed Atlassian Cloudflare WAF threshold ~10K chars), add a single-line description pointer: `## Testing Strategy — see comments <ID>, <ID>, ... for the full TS v6.` Without it, future readers / auditors / QA reviewers miss the TS entirely.
14. Only then transition the ticket to "Ready for QA"

## Non-negotiables (apply to every ticket)

1. **Step 0 zero-knowledge baseline per TC** — UI URL, API URL, role (cite the role name; never an env-var), test account email, auth flow, prerequisites, mocked services, tags, Verification GET (mandatory for state-changing API ops), cache-bypass mechanism (if cached). Cross-references allowed; the slot is required per TC.
2. **Every step has all four labeled components:** Action · Expected Result · Wait Condition · blank Actual Result. API uses `Request` / `Response`. Linear block format — never tables for execution rows.
3. **Newman two-tier rule** for any API change. Tier 1 = dev's authoritative `*.postman_collection.json` (contract/smoke). Tier 2 = QA-authored Playwright `request` (cross-program / multi-tab / UI+API). Markdown Request/Response in the ticket SHALL match the Newman request exactly.
4. **QA verification surface is restricted:** browser DOM/URL/console/network + public HTTP API responses + Gmail readback for `suhrobu+*@alldigitalrewards.com` + QA-controlled webhook receiver (webhook.site) + read-only dashboards ONLY. **No DB queries. No container shell. No log access. No queue inspection. No Redis CLI. No Docker. No SSH. No kubectl.** Confirmed org-wide ABSOLUTE 2026-04-26.
5. **URL verification rule (absolute).** Before posting any URL in a ticket, dev MUST verify it points to a QA environment — not prod. Same rule applies to env-var values, test-data values, payloads, and webhook targets. There is no fixed allowlist; verification IS the rule. Hostname denylist (production-pattern, never write): `*.alldigitalrewards.com`, `*.adrewards.com`, `*.rewardstack.com`, `*.rewardstack.net`. Hostname allowlist (default-safe): `*.adrqa.info`, `localhost`, `webhook.site`. Verified QA URL inventory in `references/qa-environment-inventory.md`.
6. **Auth: cite the ROLE — never invent env-var names.** QA's `auth.setup.ts` already wires the 8 canonical roles (Super Admin, Org Admin, Admin View Only, Accounting, Configuration, Customer Service, Participant View, Reporting) to credentials and maintains pre-authenticated `storageState` files per role. Every TC Step 0 says `Authenticate as <role>` — QA handles credentials. Inventing env-var names like `BATCH_ADMIN_TOKEN`, `QA_SUPERADMIN_TOKEN`, `ENV_SUPERADMIN_EMAIL` is forbidden — they don't exist, and even if they did, dev shouldn't have to know them.
7. **Test data conventions:** generated values SHALL contain `plrt`; emails SHALL start with `suhrobu+` and end with `@alldigitalrewards.com`. Forbidden: `@example.com`, `qa-superadmin@alldigitalrewards.com` (no `suhrobu+` prefix), `participant_random_123` (no `plrt` marker).
8. **Visually-relevant UI steps include inline Expected + Actual screenshot slots** within the step block — pasted inline, NEVER uploaded as generic ticket attachments. UI TCs additionally require **3 dev-env reference screenshots** (pre-state, post-success, post-failure) at the TC level. Skip per-step screenshot slots on steps with purely textual assertions.
9. **`data-testid` fallback rule:** if a UI element lacks an accessible name, dev MUST add `data-testid` in the same PR. Never ship a TC whose locator depends on `nth-child`, class names, or XPath.
10. **35-row test-angles matrix (§9a)** — every angle marked YES has at least one TC mapping; every "Not applicable" has explicit justification. The skill SHALL refuse to ship if any YES has no TC.
11. **8 canonical roles RBAC matrix (§9c)** when any TC touches an auth-gated endpoint — Super Admin · Admin · Admin View Only · Accounting · Configuration · Customer Service · Participant View · Reporting. Plus cross-org IDOR (workspace-A user reaches workspace-B → 403/404, never 200).
12. **UI-vs-API surface alignment (§13):** if the AC describes user-facing behavior, the TC is `[UI]` or `[E2E]` — never `[API]`-only shortcut. The only sanctioned `[API]` exception is `POST /token` for setup auth.
13. **Auth-lockout protection (§13)** is mandatory if any TC touches a login endpoint. No bad-credential probes without justification — they trigger 15-min team-wide lockout.
14. **Dev-Provided Test Affordances (§19)** are the contract closing the QA Capability gap — if QA needs to verify behavior X and can't with their toolkit, dev SHALL expose a surface (audit-log API, cron manual-trigger, queue-message API, cache-bypass, etc.). No "[NOT-QA-TESTABLE]" without escalation.
15. **Pre-mortem promote-or-justify (§20):** every row resolves to either `→ TC<N>.Step<M>` or `_Accepted residual risk: <reason>_`. Unresolved rows block the ticket.
16. **Severity (defect) + Priority (QA verification) — both required (§1b).** Severity vocab: `BLOCKER · HIGH · MEDIUM · LOW · INFO` (per §21 auto-escalation). Priority: `P1 / P2 / P3` (P1 = customer-facing or revenue-blocking — Phase 4 within 24h; P2 = degraded experience — Phase 4 same sprint; P3 = internal/cosmetic — next cycle OK). Severity is "how bad when defect happens"; priority is "how soon QA verifies post-merge."
17. **Tags per TC (CI-canonical names — verified against `package.json`):** `@regression` (default suite) · `@smoke` (critical-path subset) · `@critical` · `@batch` · `@wcag` (axe scan, separate suite) · `@known-defect(DS-NNNNN)` · `@reads-real-email` (Gmail readback — replaces stale `@gmail`) · `@needs-online-agent` (live chat — replaces stale `@chat-hours-only`) · `@sends-real-email` (POSTs to real human-monitored inbox — replaces stale `@live-email`; most dangerous) · `@slow` (>5min) · `@e2e` · `@idor` · `@<service>` (e.g., `@catalog`, `@dashboard`). Stale tags `@gmail`, `@chat-hours-only`, `@live-email` are forbidden — they don't match CI's grep-invert pattern.
18. **Accessibility scan default-on** for UI TCs (`@axe-core/playwright`); zero critical/serious violations except an explicitly tracked allowlist.
19. **Browser / device matrix per UI TC (Ask #31):** default `chromium 1920×1080`. Mobile-specific code adds `webkit-iOS 375×667` + `chromium-Android 412×915`. Cross-browser concerns add `firefox 1920×1080` + `webkit 1920×1080`. Without explicit matrix, mobile-only and Firefox-only bugs silently ship.
20. **Ticket size budget per issue type (§0):** Story/Task ≤8 TCs · Bug ≤3 · Sub-task ≤5 · Epic = 0 (TCs live on children). Decompose if any trigger applies — touches 2+ repos / 2+ services / 2+ phases / has cross-cutting risks.
21. **Atomic single-bug rule:** one bug = one ticket. Different root cause / different code path / different symptom = different ticket. Never bundle.
22. **Style linter — 9 conventions (§22):** field naming · severity vocab (BLOCKER/HIGH/MEDIUM/LOW/INFO + P1/P2/P3 priority — distinct fields) · "Done" definition · step granularity (1 action + 1 literal expected result) · locator strategy · test-data naming (`plrt` + `suhrobu+`) · full-URL format (verified against QA inventory) · auth reference (cite the role name; never invent env-var names; never literal credentials) · status-code precision (no "or"/"likely"/"non-2xx").
23. **Cold-read rule (final gate):** a stranger reading the ticket with zero prior context must be able to manually execute every TC top-to-bottom without asking a question. If they would need to ask, the TC is incomplete.
24. **"I Don't Know" Protocol — never leave a field blank.** Three states only: write the value · `Unknown — QA to verify <how>` (investigated but not determined; QA probes and fills in) · `N/A — <why>` (considered, doesn't apply). **Blank = forgotten = blocks the ticket.** QA treats Blank as "ask the dev again," Unknown as "investigate and fill," N/A as "skip." Never silently omit a field — declare its state explicitly so QA can act on it.
25. **Phase 4 Merge Gate is hard.** Linked PR(s) MUST be (a) NOT in DRAFT and (b) deployed to QA env (timestamp + commit SHA recorded in §4a) before status flips to "Ready for QA". QA may pre-author tests in Phase 2 and dry-run them against pre-merge state in Phase 3 (assertion FAILS against pre-merge code), but the official "PASSED" report (Phase 4) cannot be posted until merged + deployed. Don't trust memory or prior conversation about PR state — run `gh pr view <N> --json state,isDraft,mergedAt,updatedAt` before asserting it.
26. **Per-step expected result (§13 in template, "the completeness rule").** Every TC step has ONE literal expected result — and that expected result IS the test assertion. Status codes are one example; expected DOM state, response field value, URL match, screenshot baseline are others. No expected result on a step = no assertion = a weak test that lets defects through. Count steps; count expected results; the two numbers MUST match. If genuinely unknown, mark `[BLOCKED-DEV-CONFIRM]` — never leave it ambiguous.
27. **"What changes after merge" block (§1a — Ask #24).** EVERY ticket SHALL state, in plain language, the exact observable behavior change a QA tester can hit through HTTP / Playwright / Newman / Gmail / webhook receiver to confirm the fix landed. Format: one short paragraph + a Pre-merge → Post-merge assertion target. Internal-only refactors with no observable change SHALL say so explicitly. This block is the assertion target for QA's Phase 3 (pre-merge dry-run, expect FAIL) and Phase 4 (post-merge verification, expect PASS).
28. **Feature flag state per TC (Ask #25).** EVERY ticket whose code path is gated by a feature flag SHALL list every flag the code checks + the required state per TC + scope (program/org/global) + how to set. If no flag gates the path, state explicitly "No feature flags gate this code path." Without this, QA may exercise the OFF branch and the test "passes" on the wrong code (false positive that ships a real defect).
29. **Test-data seed mechanism per entity (Ask #26).** Generic "Test Data Required" is not enough. Dev SHALL state HOW to seed: numbered UI/API steps, existing fixture env-var, JSON shape for QA-seed, or "dev seeded via deploy hook at <timestamp>". Without explicit seed mechanism, QA guesses the seed path; if wrong, the test fails for the wrong reason.
30. **Real-world side effects + cleanup plan per TC (Ask #29).** EVERY TC that produces a real-world side effect (sends email, creates order, charges card, mutates shared state) SHALL declare the side effect + the cleanup mechanism. `plrt` markers handle bulk SQL cleanup but don't undo real emails / orders / charges. Without an explicit cleanup plan, repeated test runs pollute QA env.
31. **DB migration confirmation in §4a Phase 4 banner (Ask #27).** Tickets whose PR includes a DB migration SHALL state migration name + how to verify it ran (`GET /api/.../migration-status` OR deploy log timestamp). Tickets with NO migration SHALL state explicitly "No DB migration in this PR." Without this, a silently-failed migration produces tests that pass against stale schema.
32. **API schema diff + webhook payload schema in §1a (Asks #28 + #32).** Tickets whose PR changes API request/response shape SHALL include a schema diff (added / removed / changed-type fields) inside §1a. Tickets whose PR changes webhook payload shape SHALL include the new payload schema. State explicitly "No schema changes" / "No webhook payload changes" if internal-only. QA's automated assertions break silently when schema changes without notice.
33. **TS-in-comments discoverability (rule from template §17).** When the Testing Strategy lives in JIRA comments because the description would breach Atlassian Cloudflare WAF (~10K chars), the description SHALL include a single-line pointer: `## Testing Strategy — see comments <ID>, <ID>, ... for the full TS v6.` Without it, the TS is invisible to anyone scanning the description. Corrections to URLs / env-vars / test data MUST land in the description body — split the ticket if needed; do NOT keep appending comments for content fixes.
34. **Pre-QA Handoff Checklist boxes stay `[ ]` until the underlying condition is met.** A pre-checked checklist isn't a gate, it's decoration. Items like "PR code-reviewed and approved" / "Deployed to QA env" / "Newman green inside Docker" stay unchecked until the event is confirmed (review approved, deploy timestamp recorded, test output attached). False checks waste QA cycles when the underlying condition isn't actually met.

## Section map (what's in the v6 template)

- **§0:** Ticket Size Gate (per-issue-type budget; decomposition triggers)
- **§1:** Behavior Change (Before / After / Primary Signal)
- **§1a:** What Changes After Merge (the QA Phase 3 + Phase 4 assertion target — Pre-merge → Post-merge + API schema diff + webhook payload schema)
- **§1b:** Severity (BLOCKER…INFO) + Priority (P1/P2/P3) + business impact
- **§2:** PR Reference & Coverage Map (changed function → TC step or unit test) + Newman collection
- **§3:** Depends On (top-of-ticket — auto-derived from PR diff)
- **§4a:** Phase 4 Merge Gate banner (PR state + merge timestamp + deploy timestamp + commit SHA + DB migration verification)
- **§4b:** QA Environment (URL, deploy status, brand-new-domain flag, refresh cadence)
- **§5a:** Test Data Required + Seed Mechanism (numbered UI/API steps · fixture · JSON shape · deploy hook)
- **§5b:** Test Data Lifecycle + Real-World Side Effects + Cleanup Plan
- **§6:** Regression Impact (downstream consumers, risk level)
- **§7a:** Dependencies, Deployment & Feature Flags (per-TC flag state + scope + how-to-set)
- **§7b:** Mocking Policy (REAL/SANDBOX/MOCK)
- **§7c:** Compliance Flags (PII/BIPA/PCI/HIPAA/COPPA/SOX/ADA)
- **§8:** Acceptance Criteria (numbered · atomic · observable · mapped)
- **§9:** Coverage Matrices — 9a 35-row test-angles · 9b Input Partitions · 9c 8-role RBAC · 9d Failure-Mode Checklist · 9e Externally-Observable State Mapping
- **§10:** AC ↔ TC two-way mapping (forward + reverse)
- **§11:** Test Cases — Unified Step Format (Step 0 with role-based auth + numbered Steps; per-TC required artifacts; 3 dev-env reference screenshots for UI; browser matrix; severity/priority; feature flag state)
- **§12:** Non-Functional Triggers (28-row PR-change matrix)
- **§13:** Auth-Lockout Protection (lockout-protected endpoints; bad-credential probe rules)
- **§14:** Audit Log + Observable Side-Effects (with QA-accessible verification surface per side-effect)
- **§15:** Error Message Content (exact text · i18n key · surface · accessibility attrs)
- **§16:** Migration Safety + Rollback (Phase 4 verification surface — never SQL)
- **§17:** API Versioning + Backwards Compatibility
- **§18:** OUT-OF-SCOPE Justifications ("why this cannot fail in prod")
- **§19:** Dev-Provided Test Affordances (audit-log API, cron trigger, queue API, cache-bypass, lockout-clear, time-freeze, feature-flag toggle, etc.)
- **§20:** Adversarial Pre-Mortem (≥10 Story/Task · ≥5 Bug · ≥15 Epic; promote-or-justify per row)
- **§21:** Severity Escalation Rules (auto-BLOCKER triggers)
- **§22:** Style Linter (9 conventions — including role-based auth + canonical CI tags)
- **§23:** Pre-QA Handoff Checklist (every box checked only when condition is real)
- **§24:** After QA Starts (don't push during QA, defect feedback loop)
- **Defect-report shape** (Bug-type / FAILED reports — Findings/Observations panel at content array index 1, Manual Repro mandatory, atomic single-bug rule)
- **Appendix A:** Field Reference — all 30 fields organized by category (Always Required · UI-only · API-only · Conditional)
- **Appendix B:** Ticket Type Quick Reference — minimum field set per issue type (UI Bug · API Bug · UI Feature · API Feature · Mixed · Refactor · Config Change)
- **Common Mistakes That Waste QA Cycles** (appended to §23) — known QA-cycle-burning errors mapped to prevention, including v6-specific items (invented env-var names, unverified URLs, stale CI tags, PR state from memory, pre-checked checklist, missing description pointer, missing architectural-promise TC, missing "What changes after merge" block, comment-instead-of-split for description-body fixes)

## Why this exists

Every section in the v6 template exists because its absence caused a real prod incident or wasted QA cycle. The 35-row test-angles matrix forces enumeration so coverage doesn't depend on the dev's vigilance. The 8-role RBAC matrix catches privilege-escalation and IDOR before prod. The 4-component step format ensures Playwright automation is unambiguous. The QA-observability constraint matches QA's actual environment reality (no DB, no container access). Role-based auth (Stan v6) replaces the brittle "dev invents env-var names" pattern that produced 30+ minutes of debug per occurrence. The URL verification rule replaces the "production-pattern hostname accidentally lands in a ticket" failure mode. The Phase 4 Merge Gate banner prevents the false-PASS-against-pre-merge-code class of defect. The "What changes after merge" block gives QA a concrete Pre-merge → Post-merge assertion target. The pre-mortem promote-or-justify rule turns "we know this could happen" into "tested or accepted-risk." The Pre-QA Handoff Checklist with "boxes stay `[ ]` until condition met" semantics turns the checklist from decoration into a real gate.

**Both sides own zero-defects-in-production together.** If a defect reaches prod, both sides — dev and QA — didn't do our work correctly. This template is the contract that lets both sides hit the bar. Testing is the first of three layers of defense — paired with staged rollouts and the prod log monitors. This template makes the first layer maximally rigorous; the other layers catch what still slips through.

## Reference

- The complete v6 template lives at `references/template.md`. Always read it before authoring a Testing Strategy section.
- The verified QA URL/env-var/role/CI-tag inventory lives at `references/qa-environment-inventory.md`. Always check it before posting any URL, env-var name, role, or tag — inventing plausible-looking names is the #1 most common defect.

## Source of v6 changes

v6 integrates QA dev Stan's 2026-04-28 feedback file (`2026-04-28-feedback-for-joe-FINAL.md`) on top of the v5 baseline. **All v5 ADR-specific rules are preserved** — Newman two-tier, QA-observability constraints (no DB/Docker/Redis/queue admin), 8-canonical-roles RBAC, hostname allow/deny lists, `plrt`/`suhrobu+` test-data conventions, Auth-Lockout Protection, Dev-Provided Test Affordances, Severity auto-escalation, Compliance Flags, 9-convention style linter, cold-read rule, "I Don't Know" Protocol, 35-row test-angles matrix, 22-item Pre-QA Handoff Checklist (now expanded), Field Reference appendix, Ticket Type Quick Reference appendix.

**Added in v6:**
- **Role-based auth (replaces env-var-name citation)** — every TC Step 0 cites the role name from the 8 canonical roles; QA's `auth.setup.ts` handles credentials. Inventing env-var names like `BATCH_ADMIN_TOKEN` / `QA_SUPERADMIN_TOKEN` is forbidden. §22 Style Linter rule 8 updated.
- **URL verification rule (absolute)** — before posting any URL, dev MUST verify it points to a QA environment, not prod. There is no fixed allowlist; verification IS the rule. New reference file `references/qa-environment-inventory.md` provides the verified QA URL inventory + real env-var names + canonical CI tags + QA artifact paths.
- **Phase 4 Merge Gate banner (§4a)** — PR state + merge timestamp + deploy timestamp + commit SHA + DB migration verification. QA may pre-author Phase 2 tests + dry-run them in Phase 3 (expect FAIL); the official "PASSED" report (Phase 4) cannot be posted until merged + deployed.
- **"What changes after merge" block (§1a — Ask #24)** — Pre-merge → Post-merge assertion target + API schema diff (Ask #28) + webhook payload schema (Ask #32). The QA Phase 3 + Phase 4 verification target.
- **Severity (BLOCKER/HIGH/MEDIUM/LOW/INFO) + Priority (P1/P2/P3) — both required (§1b)** — severity is "how bad when defect happens"; priority is "how soon QA verifies post-merge." Reconciles Stan's P1/P2/P3 ask with v5's BLOCKER…INFO vocab without overwriting either.
- **Feature flag state per TC (§7a — Ask #25)** — every flag the code path checks + required state per TC + scope + how-to-set; OR explicit "No feature flags gate this code path."
- **Test-data seed mechanism per entity (§5a — Ask #26)** — generic "Test Data Required" alone is not enough; dev SHALL state HOW to seed (numbered UI/API steps, fixture env-var, JSON shape for QA-seed, or "dev seeded via deploy hook at <timestamp>").
- **Real-world side effects + cleanup plan per TC (§5b — Ask #29)** — every TC producing real-world side effects (emails / orders / charges / shared-state mutation) declares the cleanup mechanism. `plrt` markers handle bulk SQL cleanup but don't undo real emails / charges.
- **Browser / device matrix per UI TC (Ask #31)** — default `chromium 1920×1080`; mobile-specific code adds iOS Safari + Android Chrome 375×667 / 412×915; cross-browser concerns add Firefox + Safari desktop.
- **Per-step expected result completeness (Rule 13 in template Absolute Rules)** — every step has ONE literal expected result that becomes the test assertion. Count steps; count expected results; numbers MUST match.
- **Canonical CI tags from `package.json` grep-invert** — `@reads-real-email` / `@needs-online-agent` / `@sends-real-email` replace stale `@gmail` / `@chat-hours-only` / `@live-email`. Real grep-invert pattern documented in `references/qa-environment-inventory.md`.
- **Pre-QA Handoff Checklist semantics (Rule 18, §23 intro)** — boxes stay `[ ]` until the underlying condition is met. A pre-checked checklist isn't a gate, it's decoration.
- **TS-in-comments discoverability (Rule 17)** — when TS lives in comments because of WAF threshold, the description SHALL include a single-line pointer. Corrections to URLs / env-vars / test data MUST land in the description body — split the ticket if needed.
- **Common Mistakes That Waste QA Cycles** updated with v6-specific items: invented env-var names, unverified URLs, stale CI tags, PR state from memory, pre-checked checklist, missing description pointer, missing architectural-promise TC, missing "What changes after merge" block, comment-instead-of-split for description-body fixes, body-scrub coverage missing on POST while PUT/PATCH are tested.

**v5 → v6 carry-forward (preserved verbatim):** 35-row test-angles matrix, 8-canonical-roles RBAC, top-of-ticket Depends On / QA Environment / Test Data blocks, Non-Functional Triggers (28-row PR-change matrix), Auth-Lockout Protection, Dev-Provided Test Affordances contract, Audit Log + Side-Effects (QA-observable surfaces), Error Message Content, Migration Safety + Rollback, API Versioning, Compliance Flags (PII/BIPA/PCI/HIPAA/COPPA/SOX/ADA), Severity auto-escalation, 9-convention style linter (rules 6 + 8 updated), expanded tag inventory, 3 dev-env reference screenshots per UI TC, Newman two-tier framing, atomic single-bug rule, defect-report ADF structure, pre-mortem promote-or-justify per row, ticket size budget per issue type, "I Don't Know" Protocol, Field Reference appendix, Ticket Type Quick Reference appendix.
