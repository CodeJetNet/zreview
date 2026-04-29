# Testing Strategy — JIRA Ticket Section (v7 Template)

> **MANDATORY IN EVERY JIRA TICKET.** No ticket advances to "Ready for QA" without a complete Testing Strategy. No exceptions for "small," "obvious," or "config-only" changes. If the ticket touches code, it has a Testing Strategy.

> **The cold-read rule (final gate):** a reader with **zero prior context** — no Slack, no PR, no codebase — must be able to manually execute every TC top-to-bottom from this section alone. If they would need to ask a question, the TC is incomplete. Once manually executable, Playwright (UI + API) automates the same steps in the QA environment.

> **Both sides own zero-defects-in-production together.** If a defect reaches prod, both sides — dev and QA — didn't do our work correctly. This template is the contract that lets both sides hit the bar.

---

## QA Reality (constraints baked into this template)

1. **QA's Playwright automation framework can verify ONLY:** browser DOM/URL/console/network and public HTTP API responses, plus Gmail readback for `suhrobu+*@alldigitalrewards.com` and a QA-controlled webhook receiver (webhook.site) and read-only dashboards. **No DB queries. No container shell. No log access. No queue inspection. No Redis CLI. No Docker. No SSH. No kubectl.** Confirmed org-wide ABSOLUTE 2026-04-26.
2. **Newman is two-tier.** Tier 1 = dev's authoritative `*.postman_collection.json` synced into `docs/api-collections/` — runs as contract/smoke layer. Tier 2 = QA-authored Playwright `request` for cross-program / multi-tab / UI+API integration. Markdown Request/Response in this ticket SHALL match the named Newman request exactly.
3. **Every TC will be automated in Playwright.** Steps must be locator-precise and assertion-precise.
4. **The URL verification rule (absolute).** Before posting any URL in a ticket, dev MUST verify it points to a QA environment — not prod. Same rule applies to env-var values, test-data values, payloads, and webhook targets — every external destination referenced by a TC. There is no fixed allowlist; verification IS the rule. Hostname allowlist (default-safe): `*.adrqa.info`, `localhost`, `webhook.site`. Hostname denylist (production-pattern, never write): `*.alldigitalrewards.com`, `*.adrewards.com`, `*.rewardstack.com`, `*.rewardstack.net`. The denylist is the failsafe — even with verification, a hostname matching the denylist is automatically wrong. **The verified QA URL inventory lives in `references/qa-environment-inventory.md` — read it before authoring any URL.**
5. **Auth: cite the ROLE — never invent env-var names.** QA's `auth.setup.ts` already wires the 8 canonical roles (Super Admin, Org Admin, Admin View Only, Accounting, Configuration, Customer Service, Participant View, Reporting) to credentials and maintains pre-authenticated `storageState` files per role. Every TC Step 0 says `Authenticate as <role>` — QA handles credentials. Inventing env-var names like `BATCH_ADMIN_TOKEN` / `QA_SUPERADMIN_TOKEN` / `ENV_SUPERADMIN_EMAIL` is forbidden — they don't exist, and even if they did, dev shouldn't have to know them. Full role list + worked examples in `references/qa-environment-inventory.md`.
6. **Generated test data SHALL contain the project token (`plrt`); test emails SHALL start with `suhrobu+`** and end with `@alldigitalrewards.com`. So QA cleanup queries are safe and real inboxes are never spammed.

---

## Absolute Rules (non-negotiable)

1. **Every TC starts at Step 0** — zero-knowledge setup. URL, role (cite the role name; never an env-var), test account email, auth flow, prerequisites, mocked services, tags. Cross-references allowed; the slot is required per TC.
2. **Every step has these conceptual components: Step 0 (setup) → Action → Expected Result → Wait Condition → blank Actual Result.** API uses domain-appropriate labels: `Request` (= Action) and `Response` (= Expected Result). No step exists without every component filled.
3. **Linear block format for every execution step — never tables.** Tables don't fit screenshots (UI) or multi-line request/response bodies (API). Each step is a sequence of labeled blocks.
4. **Every UI step has its OWN inline `Expected screenshot` + `Actual screenshot` slots within that step's block — NEVER uploaded as a generic ticket attachment.** Skip on steps with purely textual assertions (URL pattern, status code, response field, DOM attribute). API steps never have screenshots — the Response body is the evidence.
5. **Every UI Action names a precise Playwright locator** (`getByRole`/`getByLabel`/`getByTestId` + exact name). No vague "click the button." If element lacks an accessible name, dev MUST add `data-testid` in the SAME PR. Never ship a TC whose locator depends on `nth-child`, class names, or XPath.
6. **Every API Action names full HTTP shape:** method, URL, headers, body — AND the Newman collection/folder/request name that mirrors it.
7. **Every Wait Condition names a deterministic signal** (URL pattern, element state, response status). Never `waitForTimeout` / `sleep`.
8. **Every Expected Result is QA-observable** (DOM, URL, console, network response, public API, Gmail readback, webhook receiver). Anything verifiable only via DB or container shell goes in the per-TC "Dev-Only Verification" block — NOT a TC.
9. **Every AC maps to at least one TC step. Every TC maps back to ≥1 AC** OR carries written justification (regression baseline, security probe).
10. **Every changed function in the PR diff** is referenced by a TC step OR a unit test in this PR. Coverage gaps block the ticket.
11. **Every OUT-OF-SCOPE item argues why it cannot fail in production.** "Different ticket" is not an argument.
12. **Every pre-mortem row resolves** to either `→ TC<N>.Step<M>` (promote) OR `_Accepted residual risk: <reason>_` (justify). Unresolved rows block the ticket.
13. **Every TC step has ONE literal expected result — and that expected result IS the test assertion.** Status codes are one example; expected DOM state, response field value, URL match, screenshot baseline are others. No expected result on a step = no assertion in our automated test = a weak test that lets defects through. **Completeness rule:** count steps; count expected results; the two numbers MUST match. If a step's expected result is genuinely unknown, mark `[BLOCKED-DEV-CONFIRM]` — never leave it ambiguous and never move the ticket to "Ready for QA" until every step has a pinned expected result.
14. **No bad-credential probes** at any login endpoint. Triggers 15-min team-wide lockout. Empty-body and null-value probes are OK.
15. **"I Don't Know" Protocol — never leave a field blank.** Three states only. Pick one and write it; silence is a defect.
16. **The Phase 4 Merge Gate is hard.** Linked PR(s) MUST be (a) NOT in DRAFT and (b) deployed to QA env (timestamp + commit SHA recorded in §4) before status flips to "Ready for QA". QA may pre-author tests in Phase 2 and dry-run them against pre-merge state in Phase 3, but the official "PASSED" report (Phase 4) cannot be posted until merged + deployed. Don't trust memory or prior conversation about PR state — run `gh pr view <N> -R alldigitalrewards/<repo> --json state,isDraft,mergedAt,updatedAt` before asserting it.
17. **TS-in-comments discoverability.** When the Testing Strategy lives in JIRA comments (because the description would exceed the Atlassian Cloudflare WAF threshold ~10K chars), the description SHALL include a single-line pointer: `## Testing Strategy — see comments <ID>, <ID>, ... for the full TS v7 (size-gated to fit Cloudflare WAF).` Without the pointer, future readers / auditors / QA reviewers miss the TS entirely. Corrections to URLs / env-vars / test data MUST land in the description body — split the ticket if needed; do NOT keep appending comments for content fixes.
18. **Pre-QA Handoff Checklist boxes stay `[ ]` until the underlying condition is met.** A pre-checked checklist isn't a gate, it's decoration. Items like "PR code-reviewed and approved" / "Deployed to QA env" / "Newman green inside Docker" stay unchecked until the event is confirmed (review approved, deploy timestamp recorded, test output attached). False checks waste QA cycles when the underlying condition isn't actually met.

---

## Field-Completion Semantics ("I Don't Know" Protocol)

Three states only. Anything else (silence, "TBD", "tbd", "?", "—") = blank = blocks the ticket.

| State | Write exactly | When to use | What QA does |
|---|---|---|---|
| **Value** | the actual value | You know it | QA uses it as-is |
| **Unknown** | `Unknown — QA to verify <how>` | You investigated and couldn't determine; QA has the surface to find out | QA probes (DOM/API/etc.) and fills the value, then comments back |
| **N/A** | `N/A — <why>` | The field was considered and doesn't apply to this ticket | QA skips the field |
| **Blank / TBD / "?"** | _(forbidden)_ | — | QA blocks the ticket back to dev: "what is this?" — costs ≥1 cycle |

**Examples (good vs bad):**

| Field | ❌ Bad | ✅ Good |
|---|---|---|
| Element type for "Submit" | _(blank)_ | `Unknown — accessible name is "Submit"; QA to verify whether <button> or <a> via DOM` |
| Cache considerations | _(blank)_ | `N/A — client-side change only, no caching layer involved` |
| Rate limit | _(blank)_ | `Unknown — QA to verify by sending sequential requests until 429, then capture Retry-After` |
| Test account password | `password: hunter2` | `password: env CM_PARTICIPANT_PASSWORD` (per §22 Style Linter rule 8) |
| Some button | _(blank)_ | `<button>`, accessible name = `"Get Started"` (exact, case-sensitive) |

**Why this is a non-negotiable:** blank fields force QA to play dev-detective — they file a "what does this mean?" comment, dev replies hours later, the ticket bounces back to In Progress, and the whole sprint loses a day. Stating *Unknown* or *N/A* explicitly is what lets QA act now.

---

## 0. Ticket Size Gate (run BEFORE writing the rest)

| Issue type | Max body | Max TCs | Target | Verdict if exceeded |
|---|---|---|---|---|
| Story / Task | 800 lines | 8 | 400–600 lines, 3–5 TCs | Decompose into Epic + children |
| Bug | 400 lines | 3 | 200–300 lines, 1–2 TCs | One bug per ticket — never bundle |
| Sub-task | 400 lines | 5 | 200–300 lines | Split delta into separate sub-task |
| Epic | 1500 lines (no TCs) | 0 (TCs live on children) | 600–1000 lines | Decompose further |

> **Testing Strategy is exempt from the body budget** — TS is the value, not the overhead. If meeting all SHALL requirements would force cutting TS detail, decompose instead.

**Decomposition triggers** (decompose if any apply): touches 2+ repos · touches 2+ services · 2+ user-facing surfaces · 2+ deploy phases · cross-cutting risks not owned by any single PR. The DS-11756 pattern (1 Epic + 5 children, one per repo/PR) is exemplary.

> **Additional split trigger:** if the change produces state effects with **no UI or public-API surface** to observe them, EITHER add a verification surface in this PR (Section 5d + §19 Affordances) OR split into two tickets: one ships the change, the next ships the surface.

> **My count:** _<N>_ TCs → _<Proceed | Split into N sub-tickets: list keys>_

---

## 1. Behavior Change

- **Before:** _<exact prior behavior>_
- **After:** _<exact new behavior>_
- **Primary Signal (Playwright-observable):** _<one assertion: URL pattern, response status, response field, DOM element state>_

### 1a. What Changes After Merge (mandatory — the QA Phase 3 + Phase 4 verification target)

**Why this block exists:** post-merge verification (Phase 4) requires QA to know the precise observable behavior change the PR introduces. Without that, QA can't write a verification check that fails before merge and passes after — which is the whole point of post-merge testing.

**Format:**

> **Observable change:** _<one short paragraph in plain language naming the observable behavior change a QA tester can hit through HTTP / Playwright / Newman / Gmail / webhook receiver to confirm the fix landed>_
>
> **Pre-merge:** _<exact assertion target — e.g., `GET https://admin.adrqa.info/api/X` returns 500>_
>
> **Post-merge:** _<exact assertion target — e.g., `GET https://admin.adrqa.info/api/X` returns 200 + `body[0].field === 'value'`>_
>
> **API schema diff** _(if PR changes API request/response shape — Ask #28)_:
> - `Added field: <name> (<type>, <enum/format if any>)`
> - `Removed field: <name>`
> - `Changed type: <name> (<old> → <new>)`
> _(or state explicitly: "No schema changes — internal-only" / "No schema changes — UI-only refactor".)_
>
> **Webhook payload schema** _(if PR changes webhook emission — Ask #32)_:
> - `Webhook <event_name> payload schema:` + JSON shape (or link to canonical schema source)
> _(or state explicitly: "No webhook payload changes.")_

If the PR is internal-only with no observable effect, state so explicitly so QA doesn't waste cycles trying to verify nothing: `Internal-only refactor — no observable behavior change. QA Phase 4 verification: confirm pre-merge regression suite still passes post-deploy.` This block IS the assertion target for both Phase 3 (pre-merge dry-run, expect FAIL) and Phase 4 (post-merge verification, expect PASS).

### 1b. Severity / Priority (mandatory — Ask #30)

| Field | Value |
|---|---|
| **Defect severity (when found)** | _BLOCKER \| HIGH \| MEDIUM \| LOW \| INFO_ — per §22 vocab; auto-escalations from §21 applied |
| **QA verification priority** | _P1 \| P2 \| P3_ — P1 = customer-facing or revenue-blocking (Phase 4 within 24h of merge); P2 = degraded experience (Phase 4 same sprint); P3 = internal/cosmetic (next cycle OK) |
| **Business impact (one line)** | _<who breaks if this regresses, how visibly>_ |

Severity (BLOCKER…INFO) is how bad a defect is when it happens. Priority (P1/P2/P3) is how soon QA should verify post-merge. They're complementary; both are required.

---

## 2. PR Reference & Coverage Map

- **PR:** _<org/repo#NNN>_
- **Default branch under test:** _<master | main>_
- **Migrations included:** _<file names | no>_

**Changed files → coverage:**

| Changed file | Function/method changed | Covered by |
|---|---|---|
| _<path>_ | _<symbol>_ | _<TC#.Step# \| unit test name>_ |

> Empty cells in "Covered by" = coverage gaps = block the ticket.

**Dev unit tests in this PR:**
- _<test name> (<file>) — covers <what>_

**Newman collection (MANDATORY for any API change):**
- Path: `tests/newman/<service>/<collection>.json` (canonical, stable path the Playwright framework expects)
- Tier-1 source (if dev maintains a Postman collection): `docs/api-collections/<name>.postman_collection.json` synced via `sync.sh`
- Folders modified/added: _<Auth · Validation · Happy path · Error handling · Webhooks>_
- New requests added: _<list — each is referenced by name in TCs below>_
- Playwright-readability check: every new Newman request has populated `headers`, `body`, `auth`, and at least one `test` script asserting the expected status

---

## 3. Depends On (top-of-ticket — QA pre-seeds before Phase 2)

| Ticket | Required state | Reason |
|---|---|---|
| _<ID>_ | _MERGED \| MERGED + DEPLOYED to <URL> \| IN-DEVELOPMENT (test against branch)_ | _<one-line reason — auto-derive from PR diff: imports, API contracts, event subscriptions>_ |

If none: state `No prerequisite tickets — self-contained fix.`

---

## 4. QA Environment

### 4a. Phase 4 Merge Gate (mandatory — Ask #16)

| PR | State | Merged | Deployed to QA env | DB migration verified (if applicable — Ask #27) |
|---|---|---|---|---|
| _<PR link — org/repo#NNN>_ | _OPEN \| MERGED_ | _NO \| YES (timestamp + commit SHA)_ | _NO \| YES (timestamp + commit SHA verified via HEAD probe of the service's liveness endpoint — e.g., `curl -I https://admin.adrqa.info` → 200)_ | _N/A — no DB migration in this PR \| Migration `<name>` verified via `<GET /api/.../migration-status` OR deploy log timestamp \| `[BLOCKED-DEV-CONFIRM]`_ |

**QA Phase 4 (post-merge verification) is BLOCKED until PR is MERGED + DEPLOYED to QA env** (and migration verified if applicable).

QA may pre-author Playwright/Newman tests in Phase 2 and dry-run them against pre-merge state in Phase 3 (assertion FAILS against pre-merge code). The official "PASSED" report (Phase 4) cannot be posted until the deployment timestamp + merge commit SHA are recorded in this banner. False-PASS posted against pre-merge state is the most expensive QA defect — it claims the fix is live when it isn't.

> **Don't trust memory or prior conversation about PR state.** Run `gh pr view <N> -R alldigitalrewards/<repo> --json state,isDraft,mergedAt,updatedAt` before filling in the row.

### 4b. Environment details

| Property | Value |
|---|---|
| Service URL (UI) | _<full QA env URL from `references/qa-environment-inventory.md` — verified to be `*.adrqa.info` or other QA-only host before posting>_ |
| Service URL (API) | _<full QA env URL>_ |
| Health check | _<liveness probe — e.g., `curl -I https://admin.adrqa.info` → 200; some services expose `/health`, admin/cards do NOT (root is the canonical liveness probe)>_ |
| Deploy status | _Last deployed: <YYYY-MM-DD HH:MM UTC> from `<branch>@<sha>`; GHA workflow `<name>`_ |
| Brand-new domain | _YES (QA must run live probe before writing locators; check `references/qa-environment-inventory.md` first) \| NO_ |
| Auth model | _<bearer / cookie / mTLS>_; auth via QA's `auth.setup.ts` per role — never name env-vars |
| Data dependencies | _<other services this depends on for state>_ |
| Applicable envs | _Local \| QA only \| QA + Staging \| QA + Staging + Production-readonly_ |
| Data refresh cadence | _<when QA env data resets — e.g., "nightly 02:00 UTC">_ |
| Known infrastructure quirks | _<e.g., self-signed TLS — `rejectUnauthorized: false`>_ |

---

## 5. Test Data Required + Lifecycle

### 5a. Test Data Required + Seed Mechanism (mandatory — Ask #26)

| Entity | Quantity (production-realistic) | Minimum test scale | Constraint | Seed mechanism (HOW dev/QA seeds it) |
|---|---|---|---|---|
| _<entity>_ | _<P50 / P95>_ | _<functional + perf-spot-check>_ | _Identifiers contain `plrt`; emails `suhrobu+...`_ | _One of: (a) "Seed via UI/API: <numbered steps>", (b) "Use existing fixture: <env-var or UUID>", (c) "QA SHALL seed; data shape:" + JSON shape, (d) "Dev seeded via deploy hook — confirmed in QA env at <timestamp>"_ |

> **Generic "Test Data Required" is not enough.** Dev SHALL state HOW to seed, not just WHAT exists. Without explicit seed mechanism, QA guesses the seed path; if wrong, the test fails for the wrong reason and we waste a cycle.
>
> **QA cannot seed via DB.** Dev owns seeding via deploy hook / API / admin UI BEFORE handoff. If seeding requires a value QA's `.env.example` doesn't have (UUID, fixture path), follow the "Protocol when dev needs an env-var QA doesn't have yet" in `references/qa-environment-inventory.md`.

### 5b. Test Data Lifecycle + Real-World Side Effects (mandatory — Ask #29)

| Aspect | Specification |
|---|---|
| Uniqueness | Every created entity has unique ID (timestamp + random suffix + `plrt` marker); no parallel collisions |
| Cleanup mechanism | `afterEach` deletes via **API DELETE endpoint** — NEVER SQL DELETE. Dev exposes `DELETE /api/<entity>/{id}` per entity type. Failure logs warning, does not fail test. |
| Parallel-safety | _parallel-safe \| serial-only (declare why)_ |
| Shared seed data | _<list any seed that persists across runs, with reason>_ |
| TTL | _<e.g., "QA env nightly cron deletes plrt-tagged data > 24h">_ |
| Conflict isolation | _<if two tests modify same entity, declare which wins>_ |

**Real-world side effects + cleanup plan per TC** (Ask #29) — `plrt` markers handle bulk SQL cleanup but don't undo real emails sent / real orders created / real money charged / real shared-state mutation:

| TC | Action | Cost / impact | Cleanup mechanism |
|---|---|---|---|
| _<TC#>_ | _<sends email to suhrobu+... \| creates marketplace order \| charges sandbox card \| mutates shared org-level setting>_ | _<"clutters Gmail inbox; auto-archives after 7d" \| "creates real Galileo sandbox order; manual void via admin UI" \| "modifies shared org config; afterEach reverts via PATCH">_ | _<command / cron / manual step / afterEach restoration>_ |

If the TC has no real-world side effects beyond `plrt`-tagged DB rows, state explicitly: `Side effects: none beyond plrt-tagged DB rows; cleanup via standard afterEach API DELETE.`

---

## 6. Regression Impact

- **Components/services touched:** _<list every module modified>_
- **Downstream consumers** (verified via grep, not guessed):
  - _<file/route/job> — uses <symbol>_
- **Existing Playwright specs that might break:** _<spec file names>_
- **Existing Newman folders that might break:** _<collection/folder names>_
- **Risk level:** _LOW | MEDIUM | HIGH_ — _<one-line reason>_

---

## 7. Dependencies, Mocking, Compliance

### 7a. Dependencies, Deployment & Feature Flags (Ask #25)

- **Blocking dependencies:** _<other ticket/PR that must merge + deploy first | None>_
- **Deployment verification check (Playwright-runnable smoke):** _<one fast UI/API check confirming the fix is live before running the full TC suite>_
- **What to do if smoke check fails:** _<wait | comment back to dev | check feature flag>_

**Feature flags required by this ticket** (mandatory — Ask #25):

| Flag | Required state per TC | Scope | How to set |
|---|---|---|---|
| _<flag_name>_ | _<true/false per TC; if 2+ states, each gets its own TC>_ | _<program / org / global>_ | _<PATCH /api/.../flag, Admin UI path, env var, `POST /api/_test/feature-flags/{name}` per §19>_ |

If no feature flag gates this code path, state explicitly: `No feature flags gate this code path.` Without this, QA may exercise the OFF branch and the test "passes" on the wrong code (false positive that ships a real defect).

### 7b. Mocking Policy (per external dependency)

| Dependency | Mode | Reason | How QA enables |
|---|---|---|---|
| _<vendor / service>_ | _REAL \| SANDBOX \| MOCK_ | _<why this mode>_ | _<env var, route-fulfill snippet, sandbox URL>_ |

**Rules:** Happy-path TCs prefer REAL or SANDBOX. Failure-path TCs (5xx / timeout / malformed) SHALL use MOCK. Never use MOCK for happy path as a sandbox-setup shortcut.

### 7c. Compliance Flags

| Regime | Applies? | Extra testing required if applies |
|---|---|---|
| **PII (GDPR / CCPA)** | _Y/N_ | Right-to-erasure · data export · consent capture · audit log · retention |
| **Biometrics (BIPA — Illinois)** | _Y/N_ | Consent capture screen · revocation · 3-year retention max · written consent record |
| **Payments (PCI-DSS)** | _Y/N_ | No raw card numbers in logs/screenshots/HAR · tokenization · CVV not stored |
| **Health (HIPAA)** | _Y/N_ | BAA confirmed · audit log · access controls · encryption at rest + transit |
| **Children (COPPA)** | _Y/N_ | Parental consent · limited data · no behavioral advertising |
| **Financial reporting (SOX)** | _Y/N_ | Audit log · separation of duties · approval workflow · immutable record |
| **Accessibility (ADA / WCAG 2.1 AA)** | _Y/N_ | axe-core · keyboard nav · screen reader · color contrast · focus trap |

If any regime applies, the corresponding extra testing SHALL appear in Section 9 OR be listed Out-of-Scope (Epic only) with reason.

---

## 8. Acceptance Criteria

ACs SHALL be:
- **Numbered** (AC1, AC2, ...).
- **Atomic** — one verifiable behavior per AC. Split if you find yourself writing "and".
- **Observable** — phrased so a Reader can determine pass/fail without reading code.
- **Mapped** — every AC appears in §10 Coverage Matrix.

---

## 9. Coverage Matrices (mandatory — fill every cell)

### 9a. Test-from-all-angles (35-row matrix)

For each angle: `Required for this ticket?` (YES / Not applicable). Every YES has at least one TC mapping. "Not applicable" needs explicit justification.

| # | Angle | Required? | Covered by | Justification if N/A |
|---|---|---|---|---|
| 1 | Happy path | YES (always) | TC#.Step# | — |
| 2 | Sad path / 4xx 5xx | YES (always) | TC#.Step# | — |
| 3 | Empty / null / missing input | YES if accepts input | TC# | — |
| 4 | Boundary values (0, 1, max-1, max, max+1, negative) | YES if numeric input | TC# | — |
| 5 | Invalid types / malformed input | YES if structured input | TC# | — |
| 6 | Authorization (correct role) | YES if auth-gated | TC# | — |
| 7 | Authorization (incorrect role → 401/403) | YES if auth-gated | TC# | — |
| 8 | Cross-tenant / IDOR | YES if multi-tenant | TC# | — |
| 9 | Idempotency (replay) | YES on every mutation | TC# | — |
| 10 | Concurrency (two requests) | YES on every mutation | TC# | _single-user feature, N/A_ |
| 11 | Rate limiting (burst test) | YES on every public endpoint | TC# | — |
| 12 | Pagination boundaries (page=0, beyond-last) | YES on every list endpoint | TC# | — |
| 13 | Sort stability | YES on every sortable list | TC# | — |
| 14 | Search injection (XSS, SQLi) | YES on every search input | TC# | — |
| 15 | Schema validation | YES on every API response | TC# | — |
| 16 | Backwards compatibility | YES on every API change | TC# | _additive only_ |
| 17 | Migration safety (forward + rollback) | YES on every schema change | TC# | — |
| 18 | Performance budget (p50/p95/p99) | YES if hot path | TC# | _off hot path_ |
| 19 | Accessibility (axe + keyboard + focus trap) | YES on every UI route | TC# | — |
| 20 | Console errors (zero JS errors) | YES on every UI TC | TC# | — |
| 21 | Mobile / responsive (375 / 768 / 1280) | YES on every UI route | TC# | _admin-only desktop tool_ |
| 22 | Cross-browser (chromium / firefox / webkit) | YES on every UI route | TC# | — |
| 23 | Locale / i18n (RTL, currency, date) | YES if user-visible text | TC# | _internal admin only_ |
| 24 | Realistic scale | YES on every list/search/aggregation | TC# | — |
| 25 | Network failures (offline, slow, 5xx upstream) | YES if external deps | TC# | — |
| 26 | State machine (invalid transitions → 4xx, not 500) | YES if feature has state | TC# | — |
| 27 | Caching (invalidation, stale data) | YES if cached | TC# | — |
| 28 | Audit log (correct events emitted) | YES on every state change | TC# | — |
| 29 | Email / notification delivery | YES if sends notifications | TC# | — |
| 30 | Webhook delivery + retry | YES if sends webhooks | TC# | — |
| 31 | Feature flag states (each declared state) | YES if flag-gated | TC# | — |
| 32 | PII handling (no leakage) | YES if handles PII | TC# | — |
| 33 | Security headers (CSP, HSTS, X-Frame-Options) | YES on every new page route | TC# | — |
| 34 | CSRF protection | YES on every state-changing form | TC# | — |
| 35 | Session fixation / token rotation | YES if touches auth | TC# | — |

> The skill SHALL refuse to ship if any "YES" angle has no TC mapping. Negative cases (#2, #3, #4, #5, #7) are mandatory whenever applicable — a happy-path-only TS fails the spec.

### 9b. Input Partitions (per user input field or API parameter)

| Field | Valid partitions | Boundary values | Invalid forms | Covered by |
|---|---|---|---|---|
| _<field>_ | _<list>_ | _<min, min-1, max, max+1>_ | _<empty, null, wrong type, injection, overflow, unicode>_ | _<TC#.Step#>_ |

### 9c. Role × Action — RBAC Matrix (mandatory if multi-role / auth-gated)

Iterate every auth-gated TC across the **8 canonical roles** (`config/auth/role-matrix.ts` is single source of truth):

| Action | superAdmin | admin | adminViewOnly | accounting | configuration | customerService | participantView | reporting |
|---|---|---|---|---|---|---|---|---|
| _<action>_ | TC#.Step# (200) | TC#.Step# (200/403) | TC#.Step# (200 read / 403 write) | TC#.Step# | TC#.Step# | TC#.Step# | TC#.Step# (401) | TC#.Step# |

Plus: cross-org IDOR (workspace-A user reaches workspace-B resource → 403/404, NEVER 200 with cross-tenant data).

RA-service roles (`raApi`, `raPayment`, `raOrder`, `raAdmin`) and per-service roles (LX Hausys, AK, Changemaker) iterate separately when applicable.

### 9d. Failure-Mode Checklist (every item: `Yes — TC#.Step#` · `No — <reason>` · `N/A — <why>`)

**Data:** empty collection · single item · pagination boundary · max size · unicode/emoji · NULL vs empty string · duplicate submission

**State integrity:** illegal state transitions rejected — every status field that changes has a TC proving the API returns 4xx when given an invalid From → To pair (e.g., Approved → Draft must reject with 409)

**Concurrency:** simultaneous edits · double-submit · two tabs/devices · request retry (idempotency) · read/write race

**Auth:** expired token mid-flow · revoked token · role change mid-session · cross-tenant access · direct URL bypassing UI

**Time / locale:** non-UTC timezone (DST) · midnight boundary · multi-currency rounding · future/past dates

**Failure / degraded:** external service 5xx · external service timeout · malformed payload · partial write rollback · email/webhook send fail (does primary action still succeed?)

**Migration / deploy:** mid-deploy old/new code interaction · migration on prod-shaped data · rollback path · backfill for existing records

**ADR-specific:** audit log written for every state change · PII handling unchanged · queue consumer is idempotent on replay · dead-letter outcome verified

### 9e. Externally-Observable State Mapping (mandatory)

> Every state mutation this PR introduces SHALL be observable through one of: UI element, API response field, network call body, response status, response header, QA-controlled webhook receiver, Gmail readback. If no surface exposes a given mutation, dev MUST add one in this PR (per §19 Affordances) — or move that mutation to "Dev-Only Verification" and explicitly accept it cannot be QA-tested.

| State mutation | DB-only? | QA-observable surface | Covered by |
|---|---|---|---|
| _<e.g., activity.status set to APPROVED>_ | yes | `GET /api/v1/activities/<id>` → `status: "APPROVED"` | TC2.Step3 |
| _<e.g., audit log entry created>_ | yes | `GET /api/v1/audit?entity=activity&id=<id>` (per §19 affordance) → row with `action: APPROVED` | TC2.Step4 |
| _<e.g., email queued>_ | yes | Gmail API readback for `suhrobu+<RAND>@alldigitalrewards.com` shows email with subject `<X>` | TC2.Step5 |

---

## 10. AC → Test Case Mapping (two-way)

**Forward (AC → TC):**

| AC# | Acceptance Criterion | Covered by | Evidence type |
|---|---|---|---|
| AC1 | _<text>_ | _<TC#.Step#>_ | _<URL assertion \| response field \| DOM state \| network response>_ |

**Reverse (TC → AC):**

| TC | Covers ACs | Justification (if no AC mapping) |
|---|---|---|
| TC1 | AC1, AC4 | — |
| TC4 | — | _Negative input — exercises validation; no AC, but required by Section 9a angle #5_ |

- **Unmapped ACs:** _None_ | _<list with reason — blocks "Ready for QA">_

---

## 11. Test Cases — Unified Step Format

> **Linear block format. Same conceptual components for UI and API — domain-appropriate labels.** Every TC has Step 0 (Setup) + numbered Steps. Every step is a sequence of labeled blocks (no tables for execution rows; tables don't fit screenshots or multi-line request bodies).
>
> **Screenshot placement rule (read once, applies to every UI step that has a screenshot):**
>
> **Screenshots are per-relevant-step, not blanket on every step.** Include screenshot slots only on steps where the assertion is partly visual — element layout/position/styling, modal or banner appearance, error state rendering, or the proof-of-fix moment for the TC's Primary Signal. Skip on steps where the only assertion is a URL pattern, status code, response field, or DOM attribute (textual evidence is sufficient).
>
> **When a step DOES include a screenshot, it has its own pair**, both pasted inline within that step's block:
> - **Expected screenshot** — dev provides reference image at handoff
> - **Actual screenshot** — QA pastes observed image during execution
>
> **Screenshots are ALWAYS pasted inline within the step block, NEVER uploaded as generic ticket attachments.** To paste inline in JIRA: copy image to clipboard, click into the description at the placeholder line, paste — JIRA embeds at the cursor.
>
> **API steps never have screenshots.** The Response body is the evidence.
>
> **TC-level Visual Reference (UI tickets only):** dev provides 3 reference screenshots from dev environment for the TC overall — pre-state, post-success, post-failure — separate from the per-step inline screenshot slots. These set QA's expectation before they execute.

### TC label (mandatory)

Every TC labeled exactly one of: `[UI]` · `[API]` · `[UI+API]` · `[E2E]`. The label drives Step 0 (UI / API / both) and the test runner (Playwright UI · Newman + Playwright `request` API · both for UI+API · all for E2E with Gmail readback).

**UI vs API surface alignment:** if the AC describes user-facing behavior (button click, form fill, page render, modal), the TC is `[UI]` or `[E2E]` — never `[API]`-only shortcut. The only sanctioned `[API]` exception is `POST /token` for setup auth.

### UI step format

```
#### Step N — <short imperative description>

**Action:**
  1. <numbered exact instruction — navigate / click / type, with the Playwright locator from the Locator Reference>
  2. <next instruction>

**Expected Result:**
  - <observable assertion — URL state, DOM attribute, element visibility, network response, console state>
  - <observable assertion>

  **Expected screenshot** (include only if step is visually relevant):
  _Paste reference screenshot inline here._

**Wait Condition (Playwright):**
  <deterministic signal: `await expect(page).toHaveURL(...)` / `await expect(locator).toBeVisible()` / `await page.waitForResponse(predicate)`. NEVER `waitForTimeout`.>

**Actual Result (QA fills during execution):**
  - Pass / Fail:
  - Observed state:

  **Actual screenshot** (only if Expected screenshot above; QA pastes observed image inline within this step):
  _Paste actual screenshot inline here during execution._
```

### API step format

```
#### Step N — <short imperative description>

**Newman canonical:** `<collection> / <folder> / <request name>` — the Playwright engineer authors the API test from this Newman request. The markdown Request/Response below MUST match the Newman request exactly.

**Request:**
  ```
  <METHOD> <full URL>
  <Header: value>
  <Header: value>

  <body JSON, multi-line>
  ```
  - Required fields: <list with type + constraints>
  - Optional fields: <list with default if omitted>

**Response (Expected):**
  - Status: `<single literal code>` — never "or", "likely", "non-2xx"
  - Body shape:
    ```
    { ... full JSON shape ... }
    ```
  - Assertions (Playwright):
    - `expect(response.status()).toBe(<code>)`
    - `expect(body.<field>).toBe(<value>)`
    - <every assertion the test must make>
  - Read-after-write (if applicable): `GET <path>` returns the same body

**Wait Condition:** Synchronous endpoint — single-shot `await request.<method>(...)`. For async endpoints, name the polling URL + terminal status + max wait.

**Actual Response (QA fills during execution):**
  - Status:
  - Body:
  - Pass / Fail:
```

> **Source-of-truth rule for API:** the Newman request is canonical. The markdown Request/Response is a human-readable copy. If they disagree, Newman wins and the markdown is a defect. CI must include a Newman-vs-markdown drift check (or this is enforced manually at PR review).

---

### TC1 — _<descriptive title>_

**Label:** _[UI] | [API] | [UI+API] | [E2E]_
**Goal:** _<one sentence — what this TC proves>_
**Tags (CI-canonical — verified against `package.json` 2026-04-27):** _<pick from: `@regression` (default suite — most TCs), `@smoke` (critical-path subset), `@critical` (end-to-end SSO/checkout), `@batch` (longer batch upload), `@wcag` (accessibility — runs only via `npm run test:wcag`), `@reads-real-email` (Gmail readback — CI-excluded), `@needs-online-agent` (live chat with CSR — CI-excluded), `@sends-real-email` (POSTs to real human inbox — CI-excluded; most dangerous), `@slow` (runtime >5min — CI-excluded), `@known-defect(DS-NNNNN)` (CI-excluded), `@e2e`, `@idor`, `@cors`, `@audit-log`, `@info-disclosure`, `@<service>` (e.g., `@catalog`, `@campaign`)>_

> Stale tag names — do NOT use: `@gmail` → `@reads-real-email` · `@chat-hours-only` → `@needs-online-agent` · `@live-email` → `@sends-real-email`. Real grep-invert pattern: see `references/qa-environment-inventory.md`.

**Browser matrix (mandatory for UI TCs — Ask #31):** _Default minimum: `chromium 1920×1080`. When mobile-specific code exists, add: `webkit-iOS 375×667` + `chromium-Android 412×915`. When cross-browser concerns exist (CSS quirks, polyfills): add `firefox 1920×1080` + `webkit 1920×1080`. Without explicit matrix, mobile-only and Firefox-only bugs silently ship._
**Console baseline (UI only):** errors allowed: 0; warnings allowed: _<N>_
**Concurrency:** _parallel-safe \| serial-only \| requires-exclusive-resource:<resource>_
**Time-of-day:** _any \| business-hours:<tz/hours> \| specific-window:<ISO8601>_
**Feature flag state (per Ask #25):** _<flag_name>: <ON/OFF/variant> + scope (program/org/global)_ \| _No feature flags gate this code path_
**Mocking posture per dependency:** _<dep1>: REAL/SANDBOX/MOCK_
**Severity / Priority (per §1b):** _BLOCKER/HIGH/MEDIUM/LOW/INFO + P1/P2/P3_
**Covers ACs:** _AC1, AC3_
**Covers angles (per §9a):** _#1, #2, #6, #7, #15_
**Estimated runtime:** _<seconds>_

#### Step 0 — Setup (Zero-Knowledge Baseline)

| Item | Value |
|---|---|
| UI base URL (QA env) | _<https://...>_ — verified against `references/qa-environment-inventory.md` before posting |
| API base URL (QA env) | _<https://...>_ — verified against `references/qa-environment-inventory.md` before posting |
| Preview override (if pre-merge) | _<URL \| N/A>_ |
| **Auth** | `Authenticate as <Super Admin \| Org Admin \| Admin View Only \| Accounting \| Configuration \| Customer Service \| Participant View \| Reporting \| LX Hausys admin \| AK admin \| Changemaker admin \| RA Payment service account \| RA Order service account \| RA Admin>` (QA's `auth.setup.ts` handles credentials) |
| **Role** | _<the role name from the Auth row above>_ |
| Test account | _<email — `suhrobu+<token>-plrt@alldigitalrewards.com`>_ |
| Auth flow (manual) | _<modal \| redirect \| API token>_ — _<one-line how it works>_ |
| Auth flow (Playwright) | _<`STORAGE_PATHS.<roleKey>` reusable storageState \| full login per test if isolation required>_. Per-role storage state pre-authenticated via `npx playwright test --project=auth-setup` (avoids lockout). |
| Test data preconditions | _<entity IDs OR API GET to discover them>_ — example: `GET /api/v1/workspaces/acme-corp/challenges` returns at least one challenge with `enrolledByCurrentUser: true` |
| Test data seed mechanism (per §5a) | _<numbered UI/API steps \| existing fixture env-var \| QA seeds with this JSON shape \| dev seeded via deploy hook at <timestamp>>_ |
| External services mocked in this TC | _<service name + fixture path>_ OR _none — all calls go live_. Mock when service is flaky in QA, has rate limits, or costs money. |
| If preconditions missing | _<dev seeds via deploy hook / API / admin UI BEFORE handoff — QA does NOT seed via DB>_ |
| Rate limit | _<N attempts → M min lockout — Playwright must back off, do NOT retry past lockout>_ |
| Browser context | _<fresh per TC \| shared via storageState — and why>_ |
| Verification GET (API only — MANDATORY for state-changing operations) | _<method + path + assertion that confirms persistence>_. SHALL be a QA-accessible HTTP endpoint — never SQL. |
| Cache-bypass mechanism (if endpoint is cached) | _`Cache-Control: no-cache` honored \| `?no_cache=1` \| dedicated bypass endpoint (per §19)_. QA has no Redis CLI. |

> **Critical: NEVER cite env-var names like `SUPER_ADMIN_USERNAME` / `QA_SUPERADMIN_TOKEN` / `BATCH_ADMIN_TOKEN` / `ENV_SUPERADMIN_EMAIL` in the Auth row.** Cite the role name. QA's `auth.setup.ts` reads the right env-vars under the hood and maintains pre-authenticated `storageState` per role. Inventing plausible-looking env-var names is the #1 most common defect Stan flags — fake names route to `undefined`, auth fails silently, and QA debugs "401 Unauthorized" for 30+ minutes before realizing the env-var doesn't exist.

**Locator Reference (UI elements this TC touches — Playwright-grade):**

| Element label | Playwright locator | Component (file) |
|---|---|---|
| "Get Started" button | `page.getByRole('button', { name: 'Get Started', exact: true })` | `SubmitActivityBanner` (`submit-activity-banner.tsx`) |
| "Activities" tab | `page.getByRole('tab', { name: 'Activities', exact: true })` | `ChallengeTabs` (`page.tsx`) |
| Tab active assertion | tab locator + `toHaveAttribute('aria-selected', 'true')` | — |

> **Locator preference order:** `getByRole` (with accessible name + `exact: true`) → `getByLabel` → `getByPlaceholder` → `getByText` → `getByTestId`. Reach for `getByTestId` only if no accessible name exists.
>
> **`data-testid` fallback rule (mandatory):** If a UI element has no accessible name (icon-only buttons, generic divs, third-party widgets without ARIA), the dev **MUST add `data-testid="<descriptive-name>"` in the same PR**. Never ship a TC whose locator depends on `nth-child`, class names, or XPath.
>
> **Centralized locators:** if the service has a file in `utils/ui/locators/<service>.locators.ts`, reference its export — don't inline. If a needed locator is missing, request it via `[QA-LOCATOR-PROBE-REQUIRED]`.

**Visual Reference (UI ticket — dev provides 3 reference screenshots from dev env):**

- **Pre-state** _(page before any action)_: `[Attached: <TICKET>-<TC>-pre-state.png]` — caption: _<what is visible / current behavior>_
- **Post-state — success path**: `[Attached: <TICKET>-<TC>-post-success.png]` — caption: _<what success looks like>_
- **Failure-state — error path**: `[Attached: <TICKET>-<TC>-post-failure.png]` — caption: _<what failure looks like>_

**PII redaction:** every uploaded screenshot SHALL have personal IPs and PII redacted before upload. Caption SHALL declare `(i) PII redacted: <fields>` if redaction was applied.

**Parameterization:** _<variant list — Playwright `for...of` loop \| None>_

---

#### Step 1 — Sign in and open enrolled challenge (UI example)

**Action:**
1. Navigate to: `<UI base>/auth/login`
2. Fill email field (`page.getByLabel('Email')`) with the value of `ENV_VAR_EMAIL`
3. Fill password field (`page.getByLabel('Password')`) with the value of `ENV_VAR_PASSWORD`
4. Click sign-in button (`page.getByRole('button', { name: 'Sign In', exact: true })`)
5. After redirect, navigate to: `<UI base>/w/acme-corp/participant/challenges/clg_abc123`

**Expected Result:**
- After step 4: URL matches `<UI base>/w/acme-corp/participant/dashboard`; user avatar visible (`page.getByRole('button', { name: /avatar/i })`)
- After step 5: URL matches exactly `<UI base>/w/acme-corp/participant/challenges/clg_abc123`
- Tablist visible with 4 tabs in order: Overview · My Progress · Leaderboard · Activities
- Overview tab has `aria-selected="true"`
- "Get Started" button visible inside `SubmitActivityBanner`
- Zero `console.error` events emitted during the flow

**Expected screenshot (reference image):**
_Paste reference screenshot inline here._

**Wait Condition (Playwright):**
- Step 4: `await expect(page).toHaveURL(/\/participant\/dashboard$/)`
- Step 5: `await expect(page.getByRole('tablist')).toBeVisible()` AND `await expect(page.getByRole('button', { name: 'Get Started', exact: true })).toBeVisible()`

**Actual Result (QA fills during execution):**
- Pass / Fail:
- Observed state:

**Actual screenshot:**
_Paste actual screenshot inline here during execution._

---

#### Step 2 — Click "Get Started" from Overview tab (UI example)

**Action:**
1. Confirm Overview tab is currently active (assertion only — no interaction)
2. Click "Get Started" button: `page.getByRole('button', { name: 'Get Started', exact: true }).click()`

**Expected Result:**
- URL contains `?tab=activities` (suffix); full URL: `<UI base>/w/acme-corp/participant/challenges/clg_abc123?tab=activities`
- Activities tab has `aria-selected="true"`
- Overview tab has `aria-selected="false"`
- Activities content panel visible (replaces Overview content)
- Zero `console.error` events emitted during click

**Expected screenshot (reference image):**
_Paste reference screenshot inline here._

**Wait Condition (Playwright):**
`await expect(page).toHaveURL(/\?tab=activities$/)` AND `await expect(page.getByRole('tab', { name: 'Activities', exact: true })).toHaveAttribute('aria-selected', 'true')`

**Actual Result (QA fills during execution):**
- Pass / Fail:
- Observed state:

**Actual screenshot:**
_Paste actual screenshot inline here during execution._

---

#### Step 3 — Submit activity (API example)

**Newman canonical:** `tests/newman/changemaker/activities.json` → folder `Happy path` → request `Submit activity (participant)`. The markdown Request/Response below MUST match the Newman request exactly.

**Request:**
```
POST <API base>/api/v1/workspaces/acme-corp/challenges/clg_abc123/activities
Authorization: Bearer <token from Step 0>
Content-Type: application/json

{
  "type": "RUN",
  "distanceMeters": 5000,
  "occurredAt": "2026-04-25T08:00:00Z"
}
```
- Required fields: `type` (enum: RUN|RIDE|SWIM), `distanceMeters` (int, > 0, ≤ 1_000_000), `occurredAt` (ISO 8601 UTC)
- Optional fields: `notes` (string, ≤ 500 chars; default omitted)

**Response (Expected):**
- Status: `201`
- Body shape:
  ```
  {
    "id": "<UUID v4>",
    "type": "RUN",
    "distanceMeters": 5000,
    "occurredAt": "2026-04-25T08:00:00Z",
    "status": "SUBMITTED",
    "createdAt": "<ISO 8601 timestamp within 5s of now>"
  }
  ```
- Assertions (Playwright):
  - `expect(response.status()).toBe(201)`
  - `expect(body.id).toMatch(/^[0-9a-f-]{36}$/)`
  - `expect(body.type).toBe('RUN')`
  - `expect(body.distanceMeters).toBe(5000)`
  - `expect(body.status).toBe('SUBMITTED')`
  - `expect(Date.now() - new Date(body.createdAt).getTime()).toBeLessThan(5000)`
- Read-after-write: `GET /api/v1/activities/<id>` returns the same body (status 200)

**Wait Condition:** Synchronous endpoint — single-shot `await request.post(...)`.

**Actual Response (QA fills during execution):**
- Status:
- Body:
- Pass / Fail:

---

#### Step 4 — Error: missing required field (API example)

**Newman canonical:** `tests/newman/changemaker/activities.json` → folder `Validation` → request `Submit activity — missing distance`.

**Request:**
```
POST <API base>/api/v1/workspaces/acme-corp/challenges/clg_abc123/activities
Authorization: Bearer <token>
Content-Type: application/json

{
  "type": "RUN",
  "occurredAt": "2026-04-25T08:00:00Z"
}
```
NOTE: `distanceMeters` intentionally omitted to trigger 400.

**Response (Expected):**
- Status: `400`
- Body: `{ "error": "VALIDATION_FAILED", "message": "<text mentioning distanceMeters>", "fields": ["distanceMeters"] }`
- Assertions (Playwright):
  - `expect(response.status()).toBe(400)`
  - `expect(body.error).toBe('VALIDATION_FAILED')`
  - `expect(body.fields).toContain('distanceMeters')`
- No record created: `GET /api/v1/workspaces/acme-corp/challenges/clg_abc123/activities?occurredAt=2026-04-25T08:00:00Z` returns no matching activity

**Wait Condition:** Synchronous endpoint — single-shot.

**Actual Response (QA fills during execution):**
- Status:
- Body:
- Pass / Fail:

---

> Repeat the format for every error class the endpoint supports: 401 (no auth), 403 (wrong role), 404 (missing entity), 409 (conflict), 422 (business rule violation), 429 (rate limit). Every error is its own step.

---

#### Data Impact (QA-observable side effects only)

- _**Read-only:** "Nothing created or changed."_ — OR
- _**Creates:** `<entity>` — verify via `GET /api/v1/<path>/<id>` returns the new record. Fields needing uniqueness per Playwright run: `<field>` — use timestamp suffix._
- _**Modifies shared state:** `<setting>` — Playwright `afterEach` reverts via `<API call OR admin UI step>`._
- _**Triggers email:** verify via Gmail API readback for `suhrobu+<RAND>@alldigitalrewards.com`; assert subject + recipient._
- _**Triggers webhook:** verify via QA-controlled receiver (webhook.site) — Playwright polls the bin API and asserts payload._

#### Dev-Only Verification (pre-handoff — NOT a QA TC; NOT automated)

> Effects QA cannot observe (DB rows, container logs, queue contents). Dev runs these inside Docker before transitioning to Ready for QA. Document them so we know they were checked. **Do NOT write QA TC steps that require DB / Docker / Redis CLI / queue admin / SSH — those are forbidden.** If QA needs visibility into one of these, build a §19 affordance.

| Check | Command (dev runs in Docker) | Expected |
|---|---|---|
| DB: row created in `activities` | `docker compose exec mysql mysql -e "SELECT * FROM activities WHERE id='<id>'"` | 1 row, `status='SUBMITTED'`, `created_at` recent |
| DB: audit row written | `... SELECT * FROM audit_log WHERE entity_id='<id>'` | 1 row, `action='CREATE'` |
| Container log: no errors | `docker compose logs --tail 200 <service> 2>&1 \| grep -iE 'error\|fatal'` | empty |
| Queue: message published | `docker compose exec <service> php bin/console queue:peek <queue>` | 1 message with payload `{...}` |
| Migration applied | `docker compose exec <service> php bin/console doctrine:migrations:status` | latest version = `<version>` |

#### Console / Network / Accessibility Policy (QA-observable)

- **Browser console:** Playwright registers `page.on('console', msg => { if (msg.type() === 'error') errors.push(msg) })` — assert `errors.length === 0` at end of TC.
- **Network failures:** Playwright registers `page.on('requestfailed', req => failures.push(req))` — assert `failures.length === 0`.
- **Accessibility (mandatory for UI TCs):** Playwright runs `@axe-core/playwright` scan at end of TC — assert zero critical/serious violations. Allowlist for known pre-existing violations: _<list specific rules \| none>_.
- **Known pre-existing console noise to ignore:** _<list specific messages \| none>_
- **Container logs:** NOT in QA scope — see Dev-Only Verification.

#### Per-TC Required Artifacts

| Artifact | Required when | Path |
|---|---|---|
| Per-step screenshot | Every UI TC visually-relevant step | `.verify-states/screenshots/<TICKET>/<TC>-step<N>-<desc>.png` |
| Video MP4 | Every UI TC | `.verify-states/videos/<TICKET>-<phase>.mp4` |
| Playwright trace.zip | Every UI TC | `.verify-states/traces/<TICKET>.zip` |
| Console errors JSON | Every UI TC | `.verify-states/<TICKET>-console-errors.json` |
| axe-core violations JSON | Every UI TC touching new route | `.verify-states/<TICKET>-axe-violations.json` |
| HAR file | API-heavy or multi-request TC | `.verify-states/<TICKET>.har` |
| API request/response log | Every API TC | `.verify-states/<TICKET>-api-test-output.txt` |
| Verification GET response | Every TC asserting persistence | Captured as JSON; **NEVER a DB query** |

---

### TC2 — _<title>_

> Repeat the full structure. Step 0 may say "Same env, auth, and locator reference as TC1 Step 0" — but the structural slot stays. Unified Step format applies regardless of UI/API mix.

---

> **Past max TCs for issue type?** Return to Section 0 and split the ticket.

---

## 12. Non-Functional Triggers

Map PR change types → required test categories. Every YES row has corresponding TS coverage OR explicit "not applicable because <reason>". Categories left unaddressed block the ticket.

| PR change | Required test categories | Applies? |
|---|---|---|
| Adds API endpoint | Auth bypass · IDOR · privilege escalation · org isolation · schema validation · rate limit · idempotency | _Y/N_ |
| Modifies auth / session | Session fixation · token rotation · privilege escalation · cross-tab sync · concurrent session policy | _Y/N_ |
| Adds form / mutation | CSRF · XSS · input validation · DB constraint errors · idempotency | _Y/N_ |
| Adds page route | Accessibility (axe + keyboard + focus trap) · responsive layout · console errors | _Y/N_ |
| Adds calculation / formula | Edge cases (0, negative, max, min, NaN, Infinity, decimal precision) · floating-point safety · locale formatting | _Y/N_ |
| Adds file upload | File-type validation · size limit · malware scan · PII in filename · error on invalid | _Y/N_ |
| Adds file download | Content-Disposition · MIME · auth required · file size > 0 · safe filename · no PII leak | _Y/N_ |
| Adds notification (email/SMS) | Template variables · recipient list · rate limit · `@sends-real-email` tag (or `@reads-real-email` if test only verifies via Gmail readback) · subject format | _Y/N_ |
| Adds webhook (sender) | Signature header · retry · idempotency · dead-letter · failed-dispatch logging | _Y/N_ |
| Adds webhook (receiver) | Signature verification · idempotent intake · double-delivery · invalid-payload rejection | _Y/N_ |
| Adds DB column | Migration safety · rollback · default-value backfill · NULL behavior visible via API · post-migration GET shape | _Y/N_ |
| Adds queue worker | Idempotency (repeated trigger) · retry (status API per §19) · dead-letter (`/api/_test/queue/{name}/dead-letter`) | _Y/N_ |
| Adds external API call | Timeout · retry · circuit breaker · 5xx fallback · vendor outage handling | _Y/N_ |
| Adds caching | Cache-Control headers · invalidation · stale-while-revalidate · cache-poisoning resistance | _Y/N_ |
| Adds rate limiting | 429 with Retry-After · per-IP / per-user / per-token policy · burst allowance | _Y/N_ |
| Adds search / filter | Empty results · injection (XSS, SQLi) · pagination boundary · sort stability | _Y/N_ |
| Adds pagination | Beyond-last-page · page=0 · cursor stability · total count match | _Y/N_ |
| Adds drag-and-drop | Dropzone validation · order persistence · keyboard alternative | _Y/N_ |
| Adds clipboard write | No PII in copy · no auto-copy on load | _Y/N_ |
| Adds print/PDF export | MIME · Content-Disposition · @media print emulation | _Y/N_ |
| Adds WebSocket | WSS · auth on upgrade · malformed-message · reconnect | _Y/N_ |
| Adds rich-text editor | XSS via paste · script injection · DOM sanitization | _Y/N_ |
| Adds 2FA flow | Valid TOTP · invalid TOTP · backup code · remember-device · bypass | _Y/N_ |
| Adds CSV import | Header validation · row count · cell injection (formula) · partial-failure report | _Y/N_ |
| Adds cron / scheduled job | Schedule expression · timezone · drift on DST · idempotency · trigger-on-demand mechanism (§19) | _Y/N_ |
| Adds queue consumer | Visibility timeout · poison-message · DLQ routing · ordering | _Y/N_ |
| Adds metric / telemetry | Metric name · cardinality · dashboard URL · alert threshold | _Y/N_ |
| Adds A/B experiment | Variant assignment seed · sample size · holdout group · cleanup after experiment ends | _Y/N_ |

---

## 13. Auth-Lockout Protection (mandatory if any TC touches a login endpoint)

Single most expensive section to violate — lockouts block the entire QA team for 15 min minimum.

| Aspect | Specification |
|---|---|
| Auth endpoints touched | _<list of full URLs>_ |
| Bad-credential probes | _forbidden (default — Author SHALL NOT propose) \| permitted-with-justification: <reason>_ |
| Probe budget per run | _<max attempts before risk of lockout>_ |
| Recovery if lockout triggered | _<admin unlock URL or "switch VPN AND wait 15 min">_ |
| Concurrency with other auth tests | _serial-only (mandatory for auth tests)_ — declare which test holds exclusive auth-endpoint access |
| Lockout state file | _`.auth/lockout-state.json` (QA convention — `token.attempts[]` + `lockedUntil` schema)_ |

**Lockout-protected endpoints** (consult before proposing any test that touches these):

| Endpoint pattern | Service | Lockout trigger | Lockout duration |
|---|---|---|---|
| `POST /token` | admin portal | 3 invalid creds in 15 min | 15 min |
| `POST /account/login` | per service | varies | varies |
| `POST /auth/refresh` | per service | usually higher tolerance; rate-limited | varies |
| FBM admin login | fbm | 3 invalid creds | 15 min (different Redis from admin portal) |
| LX Hausys / Changemaker / Card-account-site / Marketplace SSO | service-specific | service-specific | service-specific |

**Anti-patterns (SHALL NOT):**
- Multiple test files each opening their own browser + login (each = 1 attempt).
- Iterating by creating new probe scripts (`probe_v1.mjs`, `probe_v2.mjs` — each = 1 attempt).
- Per-test `beforeEach` that re-logs in (use shared auth state via `npm run test:auth`).
- Negative tests that send literal invalid passwords against any login endpoint.

Empty-body and null-value probes are PERMITTED when explicitly declared.

---

## 14. Audit Log + Observable Side-Effects (mandatory on state-changing features)

Every side-effect SHALL have a QA-accessible verification surface. NEVER direct DB / queue admin / Redis CLI / SSH.

| Side-effect type | Spec | QA-accessible verification surface |
|---|---|---|
| Audit log entry | `audit.<entity>.<action>` event emitted | **API endpoint** (per §19): `GET /api/audit-log?event_type=<name>&entity_id=<id>` — NEVER SQL `SELECT * FROM audit_log` |
| Domain event | `<EventClass>` fired | **QA-controlled webhook receiver** (webhook.site) configured as subscriber — OR `GET /api/events?type=<name>` (per §19) |
| Metric | `<counter>` increments by 1 | **Read-only dashboard URL** (Datadog / Cloud Run metrics) — OR `GET /api/metrics?name=<name>` |
| Webhook dispatched | `POST <target>` | **QA-controlled receiver** intercepts dispatch URL — OR `GET /api/webhook-log?event=<name>` (per §19) — NEVER Mongo direct |
| Queue message | adrcs intake receives 1 message | **API endpoint** (per §19): `GET /api/_test/queue/{name}/messages?since=<ts>` — NEVER RabbitMQ Management UI |
| Email sent | 1 email per trigger | **Gmail API readback** for `suhrobu+*@alldigitalrewards.com` |
| Cache invalidation | Key `<key>` evicted | **HTTP response headers** (Cache-Control, ETag, X-Cache-Hit) on subsequent GET — OR `GET /api/_test/cache/{key}/state` — NEVER `redis-cli` |
| Cron job triggered | n/a unless feature schedules | **Manual-trigger API** (per §19): `POST /api/_test/cron/{name}/trigger` — NEVER `crontab -e` |
| Background job completed | _<description>_ | **Status API** (per §19): `GET /api/_test/job/{id}/status` returns `pending` / `running` / `completed` / `failed` |
| DB row created | n/a | Verified via the entity's API GET — NEVER direct SQL |
| File uploaded to storage | n/a | API GET exposing URL or metadata — NEVER direct S3/GCS bucket inspection |

If QA cannot programmatically verify via any surface above, declare `[NOT-QA-TESTABLE — escalate to dev unit test coverage]` AND ensure dev's PR has corresponding unit tests covering it. The declaration triggers a **mandatory affordance request** in §19.

---

## 15. Error Message Content (mandatory when feature surfaces user-visible errors)

| Trigger | Exact text | i18n key | Surface | Accessibility |
|---|---|---|---|---|
| _<empty required field>_ | _"This field is required."_ | `errors.field.required` | _Inline below field, red text_ | _aria-describedby on input; aria-live="polite" on error region_ |
| _<server 500 on save>_ | _"Something went wrong. Please try again."_ | `errors.generic.server` | _Toast top-right, red bg_ | _role="alert" on toast; auto-dismiss 8s; keyboard-dismissible_ |
| _<permission denied>_ | _"You don't have access to this feature."_ | `errors.permission.denied` | _Full-page replacement_ | _role="alert"; focus moves to message_ |
| _<session expired>_ | _"Your session has expired. Please sign in again."_ | `errors.session.expired` | _Modal blocking interaction_ | _aria-modal="true"; focus trap; escape dismisses_ |

Exact text SHALL match production verbatim. Tests SHALL assert via i18n key (not hardcoded English string).

---

## 16. Migration Safety + Rollback (mandatory on every DB schema change)

| Aspect | Specification |
|---|---|
| Migration file | _<filename>_ |
| Forward migration | _<SQL or framework migration>_ |
| Backward migration / rollback | _<SQL or framework rollback; explicit "no rollback supported" if irreversible>_ |
| Default value backfill strategy | _<how existing rows get the new column's value>_ |
| NULL behavior | _<nullable? if non-null without default → backfill SHALL precede deploy>_ |
| Query-plan impact | _<EXPLAIN on representative queries; declare slowdown or "no impact">_ |
| Index changes | _<new indexes; estimated build time on production-scale data>_ |
| Locking behavior | _<does the migration lock? for how long? online-DDL strategy>_ |
| Backfill safety | _<if app-layer, declare batch size + concurrency limit>_ |
| Concurrent write safety | _<can app continue writes during migration?>_ |
| Pre-deploy verification | _<how to test on production-equivalent data before deploying>_ |
| Post-deploy verification | _<API endpoint QA hits to confirm post-migration shape>_ — NEVER SQL. If migration impact cannot be observed via any API endpoint, dev SHALL expose one per §19. |

For irreversible migrations (data loss), declare explicitly and require explicit reviewer ack.

---

## 17. API Versioning + Backwards Compatibility (mandatory on every API change)

| Aspect | Specification |
|---|---|
| Change type | _additive \| breaking \| deprecation_ |
| Versioning strategy | _URL path (/api/v2/...) \| Accept header (application/vnd.app.v2+json) \| query param (?version=2) \| none — additive only_ |
| Existing clients still work? | _YES (additive) \| NO (breaking — declare which clients break and migration plan)_ |
| Deprecation timeline | _<announcement date, removal date, customer notification path>_ |
| Backwards-compatibility tests | TC<N> covers v1 client behavior remains intact |
| Forward-compatibility tests | TC<M> covers v2 client behavior |
| Migration documentation | _<link to API changelog / migration guide>_ |

Breaking changes without a versioning strategy SHALL be flagged BLOCKER per §22.

---

## 18. OUT-OF-SCOPE Justifications

> Every item answers: **"Why can it not fail in production?"** Acceptable: "Component X is not touched and is covered by spec Y," "Code path Z requires permission this user role cannot obtain," "Behavior W is gated behind flag F which is OFF in prod and removal is tracked in ticket K."
>
> **Differentiate "no UI yet" (still testable via API) from "no behavior yet" (truly untestable).** Items with API-only behavior get a TC; items with no surface stay OOS.

| Excluded behavior | Why it cannot fail in prod | Existing test/safeguard that proves it |
|---|---|---|
| _<case>_ | _<argument>_ | _<spec/component/flag reference>_ |

**DEFERRED (tag <reviewer> to decide before sign-off):**
- _<case — reason>_

---

## 19. Dev-Provided Test Affordances (the contract closing the QA Capability gap)

If QA needs to verify behavior X, dev SHALL expose surface S. No "just check the DB" — QA can't.

| Affordance | Why QA needs it | Surface dev SHALL expose |
|---|---|---|
| Audit-log API | QA cannot SQL the audit_log table | `GET /api/audit-log?event=<X>&entity_id=<Y>` returning JSON |
| Webhook-log API | QA cannot Mongo the webhook_log collection | `GET /api/webhook-log?event=<X>&since=<ts>` |
| Cron manual-trigger | QA cannot crontab the scheduler | `POST /api/_test/cron/{name}/trigger` returning 202 + run id |
| Job-status API | QA cannot inspect background workers | `GET /api/_test/job/{id}/status` returning state |
| Queue-message API | QA cannot RabbitMQ-admin the queue | `GET /api/_test/queue/{name}/messages?since=<ts>&status=<state>` |
| Cache-bypass header | QA cannot redis-cli the cache | API endpoints honor `Cache-Control: no-cache` (or `?no_cache=1`) |
| Cache-state API | When bypass alone isn't enough | `GET /api/_test/cache/{key}/state` returning hit/miss/value |
| Webhook receiver config | QA needs to capture dispatched webhooks | Dispatch URL configurable per env (QA-env points to QA-controlled receiver) |
| DELETE endpoints for cleanup | QA cannot SQL DELETE | `DELETE /api/<entity>/{id}` SHALL exist for every entity QA creates |
| Test-mode flag | Sandbox vs production behavior | Env var or feature flag dev declares per service |
| Lockout-clear endpoint | When testing involves auth-rate-limit recovery | `POST /api/_test/lockout/clear?email=<X>` (admin-only) |
| Time-freeze / time-travel | Time-dependent behavior (TTL, expiry, cron) | Configurable "current time" via header or env |
| Feature-flag toggle API | When test exercises multiple flag states | `POST /api/_test/feature-flags/{name}` body `{"state": "on"}` |
| Migration run + rollback in QA env | QA cannot run migrations | Dev runs both forward + rollback in QA env so QA verifies pre/post + rollback API behavior |

Emit only the affordances actually needed for the PR. Unused affordances are noise.

**Naming convention for test-only endpoints:**
- Prefix `/api/_test/`
- Auth-required (Super Admin token or dedicated test-token)
- Disabled / 404 in production environment by feature flag or build-time constant
- Documented in per-service gotchas
- Logged when called

---

## 20. Adversarial Pre-Mortem (mandatory before "Ready for QA")

Hard minimum row counts: **Story/Task ≥10 · Bug ≥5 · Sub-task ≥5 · Epic ≥15.**

Run a separate Claude session with this prompt:

> Given the PR diff and Testing Strategy below, generate ≥10 ways this code could fail in production that the strategy does NOT cover. Critical constraint: QA's Playwright framework can verify ONLY browser DOM/URL/console/network and public HTTP API responses — NOT database state, NOT container logs, NOT queue contents. Prioritize failure modes that would be INVISIBLE to QA's verification surface (DB-only state corruption, silent log errors, queue message loss, audit trail gaps). For each finding: trigger, failure mode, user impact, and either (a) the new TC that catches it, (b) the new public surface needed to make it observable, or (c) a written argument that automated tests in this PR catch it.

Paste the output below. **Every row resolves to one of:**
- `→ TC<N>.Step<M>: <test>` (promote to TC), OR
- `_Accepted residual risk: <explicit reason>_` (justify with reasoning).

| # | Failure scenario | Trigger | Impact | QA-visible? | Resolution |
|---|---|---|---|---|---|
| 1 | _<scenario>_ | _<trigger>_ | _<user impact>_ | _yes/no_ | _→ TC#.Step# \| _Accepted residual risk: <reason>__ |

> Rows missing a resolution block the ticket. Observation rows ("we noticed X happens") may be left as observations; attack/failure-mode rows MUST resolve.

---

## 21. Severity Escalation (auto-BLOCKER triggers)

Per QA spec §9.1 — these SHALL auto-escalate regardless of author rating. Author SHALL NOT downgrade without QA agreement.

| Finding type | Escalates to | Reason |
|---|---|---|
| IDOR (Insecure Direct Object Reference) | BLOCKER | Cross-tenant data exposure |
| Auth bypass on protected endpoint | BLOCKER | Authentication broken |
| Privilege escalation | BLOCKER | Authorization broken |
| Org-isolation breach | BLOCKER | Multi-tenancy broken |
| PII leak in response / logs / screenshots | BLOCKER | Compliance violation |
| SQL injection | BLOCKER | Data integrity / exfiltration |
| XSS (stored or reflected) | BLOCKER | Account takeover risk |
| CSRF on state-changing endpoint | BLOCKER | Action forgery |
| Plaintext credentials in DB / logs / response | BLOCKER | Credential compromise |
| Unhandled JS exception causing data loss | BLOCKER | Silent destruction |
| Failed migration with no rollback path | BLOCKER | Recovery impossible |
| Webhook signature validation missing | BLOCKER | Spoofing |
| WCAG 2.1 AA critical violation in main user flow | HIGH (BLOCKER if blocks login/checkout) | ADA compliance |
| Race condition causing duplicate writes | HIGH (BLOCKER if financial) | Data integrity |

Severity vocabulary: **BLOCKER · HIGH · MEDIUM · LOW · INFO** only. Never P1, S1, Critical, Major.

---

## 22. Style Linter (9 conventions — all must pass)

| # | Convention | Rule | Bad | Good |
|---|---|---|---|---|
| 1 | Field naming | snake_case in JSON, camelCase in TypeScript, never mix in same ticket | `id_verification_status` and `idVerificationStatus` for same field | Pick one, use consistently |
| 2 | Defect severity vocab (§21) | One of `BLOCKER/HIGH/MEDIUM/LOW/INFO` for defect severity. Distinct from QA verification priority (P1/P2/P3 — see §1b). | Mix of `Critical`, `Major`, `S1` for severity | The 5 canonical severity labels + the 3 canonical priority labels (P1/P2/P3), each labeled clearly |
| 3 | "Done" definition | "Ready for QA" = (PR not draft) AND (deployed to QA env) AND (handoff checklist 100% — boxes only checked when condition is actually met) | Ticket flipped while PR is DRAFT; or checklist all `[x]` pre-emptively without the underlying conditions confirmed | Wait for all three; un-check items whose conditions haven't happened yet |
| 4 | Step granularity | Each TC step = ONE atomic action + ONE literal expected result (Rule 13 — every step has an assertion) | Step combining 4 actions + 3 assertions; or step with action but no expected result | Split into atomic steps; every step ends with one literal expected result that becomes the test assertion |
| 5 | Locator strategy | role/label/text first; `data-testid` fallback when no accessible name; never `nth-child` / class / XPath | `:has-text("Save")` when accessible name exists; `.MuiButton-root:nth-child(3)` | `page.getByRole('button', { name: 'Save', exact: true })`, or `page.getByTestId('save-program-btn')` if no accessible name |
| 6 | Test data naming | Generated values contain `plrt`; emails start with `suhrobu+` and end with `@alldigitalrewards.com` | `test@example.com`, `qa-superadmin@alldigitalrewards.com`, `participant_random_123` | `suhrobu+ds12800-<purpose>-plrt@alldigitalrewards.com`, `participant_plrt_<RAND>` |
| 7 | URL format | Always full URL with host, verified against `references/qa-environment-inventory.md` (no fixed allowlist — verification IS the rule) | "go to /programs/create"; `https://qa-mpadmin.alldigitalrewards.com/...` (production-pattern, never verified as QA) | `https://admin.adrqa.info/programs/create` (verified via HEAD probe + active spec usage) |
| 8 | Auth reference | Cite the role name from QA's 8 canonical roles; never invent env-var names; never literal credentials | `${SUPER_ADMIN_TOKEN}`, `${BATCH_ADMIN_TOKEN}`, `ENV_SUPERADMIN_EMAIL`, `password: hunter2` | `Authenticate as Super Admin` (QA's `auth.setup.ts` handles credentials) |
| 9 | Status code precision | One literal code per assertion | "Status: 400 or 422", "Status: non-2xx", "Status: 200 or 201, depending on framework" | "Status: 422" (or `[BLOCKED-DEV-CONFIRM]`) |

---

## 23. Pre-QA Handoff Checklist

Every box checked before transitioning to "Ready for QA". The checklist is a **real gate**, not decoration.

> **Checkbox semantics (rule #18):** items stay `[ ]` until the underlying condition is met. A pre-checked checklist isn't a gate — it's noise. Don't `[x]` "PR code-reviewed and approved" before review actually approves; don't `[x]` "Deployed to QA env" before the deploy timestamp + commit SHA are recorded; don't `[x]` "Newman green inside Docker" without test output attached. False checks waste QA cycles when the underlying condition isn't actually met. Items confirmed at emit-time (e.g., "All Q&A on this ticket, not Slack") may be `[x]`; condition-gated items stay `[ ]` and only flip when the condition is real.

**Code & deploy (dev):**
- [ ] PR code-reviewed and approved
- [ ] All linked PRs un-drafted (`gh pr view {N} --json isDraft` → `false`)
- [ ] All linked PRs deployed to QA env (timestamp + commit SHA recorded in §4)
- [ ] All blocking dependencies merged AND deployed (per §3)
- [ ] Feature flags set correctly per environment
- [ ] Clean stack verification ran locally — `docker compose down -v`, rebuild no-cache, full test suite green, container logs zero errors
- [ ] PHPCS + PHPUnit + **Newman (mandatory for API change)** all green inside Docker
- [ ] `git diff` reviewed — only intended changes
- [ ] Test data exists in QA env (dev seeded via deploy hook / API / admin UI — QA cannot seed via DB)

**Strategy completeness (dev):**
- [ ] Section 0 ticket-size gate cleared (within budget OR split performed)
- [ ] Behavior Change has a Playwright-observable Primary Signal
- [ ] Section 2 Coverage Map has every changed function mapped (no empty cells)
- [ ] **Newman collection updated** — every API change has a Newman request; folder structure preserved
- [ ] **Newman ↔ markdown parity** — markdown HTTP block matches Newman request exactly (URL, method, headers, body, expected status)
- [ ] Section 3 Depends On populated (or "No prerequisites")
- [ ] Section 4 QA Environment populated (URL, deploy status, brand-new-domain flag, refresh cadence)
- [ ] Section 5 Test Data Required + Lifecycle populated (cleanup via API DELETE — never SQL)
- [ ] Section 7 Mocking Policy declared per dependency · Compliance Flags declared
- [ ] Section 8 ACs numbered + atomic + observable + mapped
- [ ] **Section 9a 35-row test-angles matrix** — every YES has TC; every "Not applicable" has explicit justification
- [ ] Section 9b Input Partitions · 9c RBAC matrix (8 canonical roles if auth-gated) · 9d Failure-Mode Checklist · 9e State Mapping all complete
- [ ] Section 10 AC↔TC two-way mapping complete (no unmapped ACs; orphan TCs justified)

**Per-TC completeness (dev) — uniform UI/API:**
- [ ] Every TC labeled `[UI]` / `[API]` / `[UI+API]` / `[E2E]` matching AC's user-facing surface (per §13 alignment rule — no API shortcuts for UI behavior)
- [ ] Every TC has a Step 0 zero-knowledge baseline
- [ ] Every step is linear block format (no tables for execution rows)
- [ ] Every UI step: Action · Expected · Wait · blank Actual; screenshot slots on visually-relevant steps (inline, never attachment)
- [ ] Every API step: Newman canonical · Request · Response (Expected) · Wait · blank Actual Response
- [ ] Every UI Action names a precise Playwright locator (role + name + `exact: true`)
- [ ] Every API Request names full HTTP shape AND matching Newman request name
- [ ] Every Wait Condition names a deterministic signal (NOT `waitForTimeout`)
- [ ] Every Expected Result has concrete Playwright assertions
- [ ] Every TC has a Dev-Only Verification block (or "N/A — no DB/log/queue effects")
- [ ] Console / Network / Accessibility policy stated per TC
- [ ] Locator Reference table populated; every interactive element listed
- [ ] **Every UI locator uses role/label/text/testid — NEVER `nth-child`, class names, or XPath.** If element lacks accessible name, dev added `data-testid` in this PR.
- [ ] Step 0 declares external services mocked (with fixture path) OR "none — all calls go live"
- [ ] Tags declared per TC using CI-canonical names from `package.json` (`@regression` / `@smoke` / `@critical` / `@slow` / `@known-defect(<ID>)` / `@reads-real-email` / `@needs-online-agent` / `@sends-real-email` / `@wcag` / `@batch` / `@e2e` / `@<service>`) — never stale names (`@gmail` / `@chat-hours-only` / `@live-email`)
- [ ] **Visual Reference 3 dev-env screenshots attached per UI TC** (pre / success / failure) with captions and PII-redaction declaration
- [ ] Per-TC artifact list declared

**QA-observability hard checks (dev):**
- [ ] No Expected Result requires DB query, container shell, log grep, Redis CLI, or queue admin
- [ ] Every state mutation in §9e has a UI element OR public API response that exposes it
- [ ] Section 19 Affordances declared for every `[NOT-QA-TESTABLE]` mutation
- [ ] Test account exists in QA env and dev logged in with it from clean browser
- [ ] `.env` variable names exist in Playwright project's `.env.example` OR dev told QA to add them

**Non-functional + safety (dev):**
- [ ] Section 12 Non-Functional Triggers — every applicable PR-change row has TS coverage OR explicit "not applicable"
- [ ] Section 13 Auth-Lockout Protection declared if any TC touches login endpoints — no bad-credential probes without justification
- [ ] Section 14 Audit Log + Side-Effects declared for state-changing features
- [ ] Section 15 Error Message Content declared if user-visible errors
- [ ] Section 16 Migration Safety declared if PR touches DB schema
- [ ] Section 17 API Versioning declared if PR modifies existing endpoint; breaking changes flagged BLOCKER

**Style + cold-read (dev):**
- [ ] Section 22 Style Linter — all 9 conventions pass
- [ ] Status codes pinned to a literal value (no "or" / "likely" / "non-2xx")
- [ ] Locators pinned to `data-testid` or marked `[QA-LOCATOR-PROBE-REQUIRED]`
- [ ] PII redaction confirmed on every uploaded screenshot
- [ ] **Cold-read check:** re-read the entire Testing Strategy as if you knew nothing about this ticket. Could a stranger manually execute every TC top-to-bottom without asking a question? If no — fix the gap. **This is the bar.**

**Adversarial review:**
- [ ] Pre-mortem (§20) ran; minimum row count met; every row resolved (promoted to TC OR accepted residual risk)
- [ ] Architecture promises (cross-program, globally, permanent, all programs) mapped to TCs (Epic-level)

**Phase 4 Merge Gate + Post-Merge Behavior (Asks #24, #27, #28, #32):**
- [ ] §1a "What changes after merge" block populated with Pre-merge → Post-merge assertion target (Ask #24); OR explicit "Internal-only refactor — no observable behavior change" justification
- [ ] §1a API schema diff included if PR changes request/response shape (added/removed/changed-type fields), OR explicit "No schema changes" (Ask #28)
- [ ] §1a Webhook payload schema included if PR changes webhook emission, OR explicit "No webhook payload changes" (Ask #32)
- [ ] §4a Phase 4 Merge Gate banner populated with PR state + merge timestamp + deploy timestamp + commit SHA (or `[BLOCKED]` markers indicating Phase 4 is not yet runnable) (Ask #16)
- [ ] DB migration verification line filled in §4a Phase 4 Merge Gate (migration name + verification method, OR explicit "No DB migration in this PR") (Ask #27)
- [ ] PR state confirmed via live `gh pr view <N> --json state,isDraft,mergedAt,updatedAt` — not from memory or prior conversation

**Per-TC config completeness (Asks #25, #26, #29, #30, #31):**
- [ ] §7a Feature flag table populated for every flag the code path checks + required state per TC + scope + how-to-set, OR explicit "No feature flags gate this code path" (Ask #25)
- [ ] §5a Test data seed mechanism declared per entity (numbered UI/API steps, fixture env-var, JSON shape for QA-seed, or "dev seeded via deploy hook at <timestamp>") — generic "Test Data Required" alone is not enough (Ask #26)
- [ ] §5b Real-world side effects + cleanup plan declared per TC that produces side effects (emails, orders, charges, shared state mutation), OR explicit "Side effects: none beyond plrt-tagged DB rows" (Ask #29)
- [ ] §1b Severity (BLOCKER/HIGH/MEDIUM/LOW/INFO) + Priority (P1/P2/P3) + business-impact one-liner all declared (Ask #30)
- [ ] §11 TC header Browser matrix declared for every UI TC (default `chromium 1920×1080`; mobile-specific code adds `webkit-iOS 375×667` + `chromium-Android 412×915`; cross-browser concerns add `firefox` + `webkit`) (Ask #31)

**TS-in-comments discoverability (rule #17):**
- [ ] If TS lives in JIRA comments (because description would exceed Atlassian Cloudflare WAF threshold ~10K chars), the description includes a single-line pointer: `## Testing Strategy — see comments <ID>, <ID>, ... for the full TS v7.`
- [ ] Corrections to URLs / env-vars / test data live in the description body (not appended as another comment); if the description would breach WAF after the correction, the ticket was split per §0

**Auth + URL hygiene (rules from QA Reality + §22 rules 6/7/8):**
- [ ] Every URL verified to point to a QA environment (not prod) before posting — checked against `references/qa-environment-inventory.md`
- [ ] No hostname matches the denylist (`*.alldigitalrewards.com`, `*.adrewards.com`, `*.rewardstack.com`, `*.rewardstack.net`)
- [ ] Every TC Step 0 cites the role name from the 8 canonical roles ("Authenticate as Super Admin" etc.) — never `${SUPER_ADMIN_TOKEN}`, never `BATCH_ADMIN_TOKEN`, never any invented env-var name
- [ ] All test emails start with `suhrobu+` and end with `@alldigitalrewards.com`
- [ ] All generated identifiers (participant IDs, program IDs, session IDs, claim codes) contain `plrt`
- [ ] Any new env-var dev needs but QA's `.env.example` doesn't have is named explicitly under `New env-vars required by this ticket` per the Protocol in `references/qa-environment-inventory.md` — never silently invented

**Step-completeness (Rule #13):**
- [ ] Every TC step has ONE literal expected result (no "or", "likely", "non-2xx", "depending on framework")
- [ ] Step count = expected-result count per TC (the completeness rule)

**Once all boxes checked:** Backlog → Analysis → Selected for Development → Development in Progress → Ready for QA. Walk every transition; do not skip.

### Common Mistakes That Waste QA Cycles (audit yourself before flipping the ticket)

| Mistake | What Happens | Prevention |
|---|---|---|
| Sending ticket before code is deployed | All tests fail with "old behavior" — looks like a bug, but the fix isn't there | Run the §4 deploy verification check yourself; record the timestamp + commit SHA before flipping |
| Preview URL only, no stable QA URL | Preview expires when PR closes — tests can't even start post-merge | Use stable QA URL as primary; preview is an OVERRIDE, never the only entry point |
| Test account missing required data | "Element not found" failures that aren't bugs | Log in as the test account from a clean browser and verify §5 data prerequisites exist |
| Locator typo (case/whitespace) | `getByRole('button', {name: 'Get Started'})` fails because actual is "Get started" | Copy text from live DOM, NEVER from memory; assert with `exact: true` |
| Dependency PR not merged + deployed | Fix relies on another PR's behavior that isn't live | List in §3 with verification check; QA pre-runs the check before Phase 2 |
| Stale browser cache serving old bundle | Fix is deployed but browser serves cached JS | Document in §7 cache considerations: "hard refresh / incognito / cache-bypass header" |
| Credentials "shared via Slack" | QA agent has no Slack — can't even start | Reference the env-var name (per §22 rule 8); literal values in tickets are a §21 BLOCKER (plaintext credentials) |
| Missing AC → TC mapping | QA tests 3 of 5 ACs and signs off; the other 2 ship broken | Fill §10 forward + reverse mapping; "Unmapped ACs: None" is the bar |
| Auth flow not described | QA writes wrong login automation (modal vs redirect vs API) | §11 Step 0 declares auth flow + Playwright `storageState` strategy explicitly |
| Bad-credential probes against login | Triggers 15-min team-wide lockout; entire QA queue stalls | Per §13: empty-body and null-value probes ONLY; never literal-wrong-password |
| Blank field instead of "Unknown" or "N/A" | QA can't distinguish "forgot" from "doesn't apply" — files a comment, ticket bounces | "I Don't Know" Protocol (rule #15): write the value, `Unknown — QA to verify <how>`, or `N/A — <why>`. Never blank. |
| `nth-child` / class / XPath locator | Test breaks on every UI tweak; flakiness mistaken for regression | §22 rule 5: role/label/text/testid only. If element lacks accessible name, dev adds `data-testid` in THIS PR. |
| "Status: 200 or 201" | Test asserts loosely, masks an actual contract change | §22 rule 9: one literal status code per assertion; if genuinely unknown, mark `[BLOCKED-DEV-CONFIRM]` |
| Inventing env-var names like `BATCH_ADMIN_TOKEN` / `QA_SUPERADMIN_TOKEN` | Env-var doesn't exist → Playwright reads `undefined` → silent auth failure → 30+ min QA debug | §22 rule 8: cite the role name only ("Authenticate as Super Admin"); QA's `auth.setup.ts` handles credentials. See `references/qa-environment-inventory.md`. |
| URL not verified as QA before posting (`qa-mpadmin.alldigitalrewards.com`) | Production-pattern hostname → tests either fail or hit prod | URL verification rule (rule from QA Reality §4): every URL HEAD-probed + checked against active spec usage before posting. `references/qa-environment-inventory.md` is the source of truth. |
| Stale CI tag names (`@gmail`, `@chat-hours-only`, `@live-email`) | Wrong CI suite assignment → `@sends-real-email` test runs in CI → real Zendesk tickets accumulate | Use canonical names: `@reads-real-email` / `@needs-online-agent` / `@sends-real-email`. Verify against `package.json` grep-invert pattern. |
| PR state cited from memory ("PR #122 still in DRAFT") | Stale assertion blocks a non-existent gate; wastes dev cycles; erodes QA's signal | Run `gh pr view <N> --json state,isDraft,mergedAt,updatedAt` before asserting any PR state. (Rule #16) |
| Pre-QA Handoff Checklist all `[x]` on emit | Checklist isn't a gate, it's noise; QA can't trust which boxes mean "confirmed" vs "auto-checked" | Items stay `[ ]` until the underlying condition is real (review approved, deploy timestamp recorded, test output attached). Rule #18. |
| TS lives in comments but description has no pointer | TS-in-comments invisible to anyone scanning description; v1 audit-class miss | Add 1-line description pointer: `## Testing Strategy — see comments <ID>, <ID>, ...`. Rule #17. |
| Architecture promise (cross-program / globally / permanent / always / all programs) without a TC that proves it | The most legally / compliance-sensitive behavior ships with zero coverage | Map every architectural promise to a TC step. If a promise is cross-cutting, write a cross-cutting TC (multi-program, multi-card, multi-tenant). |
| Body scrub coverage missing on `POST` while `PUT`/`PATCH` are tested | Different code paths = different attack surfaces; defense-in-depth is on by default for some, off for others | Test body scrub on every CRUD verb: POST, PUT, PATCH all need explicit "field-not-allowed-in-body" steps. |
| "What changes after merge" block missing or vague | QA has no Phase 3 / Phase 4 assertion target; tests pass on pre-merge code without anyone noticing | §1a is mandatory. State Pre-merge → Post-merge in literal assertion form (Ask #24). |
| Description body fix appended as another comment because of WAF size limit | Correction conflicts with description body; QA reads canonical text and applies wrong setup | Split the ticket per §0 instead of appending another comment. Description-body corrections only. (Rule #17) |

---

## 24. After QA Starts

- **Don't push to the same branch while QA is automating.** If you must, comment on the ticket with the new commit SHA + which TCs need re-running.
- **If QA finds a bug:** fix → deploy → comment `Fixed in <SHA>, deployed to <env>. Ready for re-test.` → re-run the §23 checklist for the changed area.
- **All Q&A on the JIRA ticket, not Slack.** Both Claudes can see the ticket; neither can see Slack.
- **If a TC turns out to be unautomatable** (e.g., relies on state QA can't observe): tag the dev. The fix is to add a §19 affordance, not drop the TC.

---

## Defect-report shape (for Bug-type tickets / FAILED reports)

When the ticket reports a defect, the report body satisfies this structure:

**ADF / panel order:**
- Index 0: Header panel (status — FAILED / PARTIAL / PASSED)
- **Index 1: Findings/Observations panel** (MANDATORY position — never anywhere else)
- Index 2: Coverage Traceability Matrix (or summary)
- Index 3+: Per-finding detail panels
- Index N: Manual Reproduction Steps
- Index N+1: Environment table
- Index N+2: Recommended Fix (file + line + suggested code)

**Required fields per defect:**

| Field | Format |
|---|---|
| Severity | `BLOCKER / HIGH / MEDIUM / LOW / INFO` (per §22). Auto-escalations from §21 applied. |
| Root Cause Hint | File + line if known, OR "unknown — investigation needed" |
| Manual Reproduction Steps | Numbered, executable cold (per below) |
| Workaround | If user-facing workaround exists; otherwise "no known workaround." |
| Recommended Fix | File path + line number from stack trace + broken snippet + suggested fix |
| Evidence | Screenshots (per §11 quality rules), API request/response (per §11), HAR/console-errors/trace |
| False Positive Analysis | Required for BLOCKER/HIGH or re-validation runs — why this is NOT flaky, NOT environmental, NOT misread spec |

**Manual Repro Steps (mandatory — NEVER "run the spec"):**
- For UI: numbered walkthrough — full URL, env-var creds, exact actions, screenshots showing the bug.
- For API: **Postman-ready table** (Method / URL / Headers / Body / Expected / Actual). NEVER curl-only. NEVER SQL — use secondary API endpoint or §19 affordance to prove data state.

**Atomic single-bug rule:** one bug = one ticket. Different root cause OR different code path OR different observable symptom = different ticket. Never bundle distinct bugs.

---

## Appendix A — Field Reference (30 fields by category)

Quick lookup of every field in the template, organized so devs can confirm completeness without re-scanning all 24 sections. Per the "I Don't Know" Protocol (rule #15), every field is in one of three states: a value, `Unknown — QA to verify <how>`, or `N/A — <why>`. Blank is never permitted.

### Always Required (every ticket, every type)

| # | Field | Section | Purpose |
|---|---|---|---|
| 1 | Behavior Change (Before / After / Primary Signal) | §1 | QA writes the right Playwright assertions |
| 2 | PR Reference + Coverage Map | §2 | Every changed function maps to a TC step or unit test |
| 3 | Newman collection (if API change) | §2 | Tier-1 contract; markdown Request/Response mirrors Newman exactly |
| 4 | Depends On (top-of-ticket) | §3 | QA pre-seeds upstream tickets before Phase 2 |
| 5 | QA Environment (URL, deploy status, brand-new-domain flag, refresh cadence) | §4 | QA targets the right host with the right deploy state |
| 6 | Test Data Required + Lifecycle | §5 | Dev seeds via API/admin/deploy hook (QA cannot DB-seed) |
| 7 | Regression Impact (downstream consumers, risk level) | §6 | Catches blast-radius blind spots |
| 8 | Dependencies & Deployment + Smoke Verification | §7a | One-shot check that the fix is actually live |
| 9 | Mocking Policy (REAL/SANDBOX/MOCK per dependency) | §7b | Failure-path TCs use MOCK; happy path prefers REAL/SANDBOX |
| 10 | Compliance Flags (PII/BIPA/PCI/HIPAA/COPPA/SOX/ADA) | §7c | Triggers extra TCs; missing flag is a ship-stopper |
| 11 | Acceptance Criteria (numbered, atomic, observable) | §8 | Every AC mapped via §10 |
| 12 | 35-row Test-Angles Matrix (§9a) | §9a | Forces enumeration of every coverage angle |
| 13 | AC ↔ TC two-way mapping | §10 | Forward + reverse; orphan TCs justified |
| 14 | OUT-OF-SCOPE Justifications | §18 | Each item: "why it cannot fail in prod" |
| 15 | Adversarial Pre-Mortem | §20 | Promote-or-justify per row; min ≥10 Story / ≥5 Bug / ≥15 Epic |
| 16 | Pre-QA Handoff Checklist | §23 | 22-item gate; every box checked before "Ready for QA" |

### Required for UI Test Cases

| # | Field | Section | Purpose |
|---|---|---|---|
| 17 | Auth flow (manual + Playwright `storageState`) | §11 Step 0 | Modal vs redirect vs API token; declare once, reuse |
| 18 | Locator Reference (Playwright-grade per element) | §11 Step 0 | Role + accessible name + `exact: true`; `data-testid` fallback rule |
| 19 | Wait Conditions (deterministic; no `waitForTimeout`) | §11 each step | URL pattern, element state, response status |
| 20 | Inline screenshot slots (Expected + Actual) per visually-relevant step | §11 each step | Pasted inline; NEVER ticket attachment |
| 21 | TC-level Visual Reference (3 dev-env screenshots: pre / success / failure) | §11 TC header | Sets QA expectation before execution |
| 22 | Console / Network / Accessibility policy | §11 each TC | axe-core scan; zero `console.error`; zero `requestfailed` |

### Required for API Test Cases

| # | Field | Section | Purpose |
|---|---|---|---|
| 23 | Newman canonical reference (collection / folder / request) | §11 each step | Markdown HTTP block matches Newman exactly |
| 24 | Full HTTP shape (method, URL, headers, body) | §11 each step | Required + optional fields with constraints |
| 25 | Expected response (single literal status, body shape, assertions) | §11 each step | No "or" / "likely" / "non-2xx" |
| 26 | Verification GET (mandatory for state-changing ops) | §11 Step 0 | Read-after-write proof; never SQL |
| 27 | Error cases (each error class as its own step) | §11 | 401 / 403 / 404 / 409 / 422 / 429 |

### Conditional (include when applicable, mark `N/A — <why>` otherwise)

| # | Field | Section | When it applies |
|---|---|---|---|
| 28 | Auth-Lockout Protection block | §13 | Any TC touches a login endpoint |
| 29 | Audit Log + Side-Effects mapping (QA-observable surface per side-effect) | §14 | State-changing feature |
| 30 | Migration Safety + Rollback | §16 | PR touches DB schema |

> **Also conditional but not separately numbered:** §15 Error Message Content (user-visible errors) · §17 API Versioning (modifies existing endpoint) · §19 Dev-Provided Test Affordances (any `[NOT-QA-TESTABLE]` mutation needs a §19 surface) · §12 Non-Functional Triggers (28-row PR-change matrix) · §11 Tags using CI-canonical names from `package.json` (`@regression` / `@smoke` / `@critical` / `@slow` / `@reads-real-email` / `@needs-online-agent` / `@sends-real-email` / `@wcag` / etc.).

---

## Appendix B — Ticket Type Quick Reference

Not every ticket needs all 30 fields. Minimum field set per issue type. When a field doesn't apply, write `N/A — <why>` (per rule #15) rather than omitting silently.

| Ticket type | Minimum fields | Notes |
|---|---|---|
| **UI Bug Fix** | Always-required (1–16) + UI fields (17–22) + applicable Conditional | Mark API fields N/A. §16 Migration N/A unless schema-touched. |
| **API Bug Fix** | Always-required (1–16) + API fields (23–27) + applicable Conditional | Mark UI fields N/A. Newman tier-1 mandatory. |
| **UI Feature** | Always-required + UI fields + §11 Roles Affected (RBAC matrix §9c if multi-role) + applicable Conditional | All 8 canonical roles in §9c if auth-gated. |
| **API Feature** | Always-required + API fields + §11 Roles Affected + applicable Conditional | §17 API Versioning mandatory. §19 Affordances likely needed. |
| **Mixed UI + API** | Always-required + UI fields + API fields + applicable Conditional | TC label: `[UI+API]` or `[E2E]`. UI/API surface alignment per §13. |
| **Refactor** | Always-required (1–16, with reduced AC since behavior unchanged) + applicable Conditional | Behavior shouldn't change → TCs verify nothing broke; §6 Regression Impact is the heaviest section. |
| **Config Change** | §1 Behavior · §2 PR · §4 QA Env · §6 Regression · §7a Smoke · §7c Compliance · §28 Cache (if cached) | Lightest variant; still requires §23 Pre-QA checklist. |
| **Migration / Schema** | Always-required + §16 Migration Safety + §17 API Versioning (if exposed) + applicable Conditional | §16 forward + rollback both ran in QA env by dev BEFORE handoff. |
| **Epic** | Always-required (no §11 TCs — TCs live on children) + decomposition rationale + cross-cutting risks | Children carry the actual TCs; Epic carries the architecture promise. |

> **When in doubt, fill the field.** An unnecessary `N/A — <why>` takes 2 seconds; a missing field costs ≥1 QA cycle.

---

## Why this template exists

Every section here exists because its absence caused a real prod incident or wasted QA cycle. The 35-row test-angles matrix in §9a forces enumeration so coverage doesn't depend on the dev's vigilance. §9e ensures every state change is QA-observable — closing the "QA can't see DB" gap. The unified Step format in §11 means UI and API tickets are interchangeable to read and to automate. §13 auth-lockout protects the entire QA team. §19 affordances close the gap §3.8's QA Capability Boundary opens. The cold-read check in §23 is the final gate: if a stranger can't run it, neither can QA.

**Testing is the first of three layers of defense — paired with staged rollouts and the prod log monitors. This template makes the first layer maximally rigorous; the other two layers catch what still slips through. Zero prod bugs is the target; no single layer reaches it alone.**
