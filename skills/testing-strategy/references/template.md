# Testing Strategy — JIRA Ticket Section (v3 Template)

> **MANDATORY IN EVERY JIRA TICKET.** No ticket advances to "Ready for QA" without a complete Testing Strategy. No exceptions for "small," "obvious," or "config-only" changes. If the ticket touches code, it has a Testing Strategy.

> **The cold-read rule:** a reader with **zero prior context** — no Slack, no PR, no codebase — must be able to manually execute every TC top-to-bottom from this section alone. If they would need to ask a question, the TC is incomplete. Once manually executable, Playwright (UI + API) automates the same steps in the QA environment.

---

## QA Reality (constraints baked into this template)

1. **QA's Playwright automation framework can verify ONLY:** browser DOM/URL/console/network and public HTTP API responses. **No DB queries. No container shell. No log access. No queue inspection.**
2. **Newman collection is MANDATORY for every API test case.** Lives at `tests/newman/<service>/`. **Source of truth for HTTP shape.** Dev runs pre-merge; CI runs on every PR.
3. **Playwright QA framework reads `tests/newman/<service>/` directly to author API test cases.** The Newman collection is the canonical HTTP source the Playwright engineer (human or Claude) translates into Playwright API tests. The markdown API steps in this ticket are the human-readable mirror + manual-execution guide. **The markdown shape and the Newman request MUST match exactly — drift between them is a defect.**
4. **Every TC in this ticket WILL be automated in Playwright.** Steps must be locator-precise and assertion-precise.

---

## Absolute Rules (non-negotiable)

1. **Every TC starts at Step 0** — zero-knowledge setup. URL, role, account, credentials, auth flow, prerequisites. No assumed context from prior TCs (cross-references allowed but the Step 0 slot is required).
2. **Every step has these conceptual components: Step 0 (setup) → Action → Expected Result → Wait Condition → blank Actual Result.** API uses domain-appropriate labels: `Request` (= Action) and `Response` (= Expected Result). Both labelings fulfill the same rule. No step exists without every component filled.
3. **Linear block format for every execution step — never tables.** Tables don't fit screenshots (UI) or multi-line request/response bodies (API). Each step is a sequence of labeled blocks. **Each visually-relevant UI step has its OWN inline "Expected screenshot" + "Actual screenshot" slots within that step's block — NEVER uploaded as a generic ticket attachment.** UI steps with purely textual assertions (URL pattern, DOM attribute, response field) skip the screenshot. API Request/Response are full code blocks (multi-line, mirror Newman exactly) — no screenshots; the response IS the evidence. Tables remain only for reference/coverage data (Step 0 setup, Locator Reference, matrices in Section 5, mappings in Section 6).
4. **Every UI Action names a precise Playwright locator** (`getByRole`/`getByLabel`/`getByTestId` + exact name). No vague "click the button."
5. **Every API Action names full HTTP shape:** method, URL, headers, body — AND the Newman collection/folder/request name that mirrors it.
6. **Every Wait Condition names a deterministic signal** (URL pattern, element state, response status). Never `waitForTimeout` / `sleep`.
7. **Every Expected Result is QA-observable** (DOM, URL, console, network response, public API). Anything verifiable only via DB or container shell goes in the per-TC "Dev-Only Verification" block — NOT a TC.
8. **Every AC maps to at least one TC step.** Unmapped ACs block the ticket.
9. **Every changed function in the PR diff** is referenced by a TC step OR a unit test in this PR. Coverage gaps block the ticket.
10. **Every OUT-OF-SCOPE item argues why it cannot fail in production.** "Different ticket" is not an argument.

---

## 0. Ticket Size Gate (run BEFORE writing the rest)

| Estimated TCs | Verdict | Action |
|---|---|---|
| 1–2 | Small | Proceed |
| 3–5 | Medium | Proceed |
| 6+ | Too big | **Stop. Split the ticket** before writing the strategy. |

**Splitting strategies:** by role · by layer (API + UI + Migration) · by flow (Create + Approve + Cancel) · by state path

> **Additional split trigger:** if the change produces state effects with **no UI or public-API surface** to observe them, EITHER add a verification surface in this PR OR split into two tickets: one ships the change, the next ships the surface. Unverifiable changes do not ship.

> **My count:** _<N>_ TCs → _<Proceed | Split into N sub-tickets: list keys>_

---

## 1. Behavior Change

- **Before:** _<exact prior behavior>_
- **After:** _<exact new behavior>_
- **Primary Signal (Playwright-observable):** _<one assertion: URL pattern, response status, response field, DOM element state>_

---

## 2. PR Reference & Coverage Map

- **PR:** _<org/repo#NNN>_
- **Default branch under test:** _<master | main>_
- **QA environment URLs (reachable from Playwright runner):**
  - UI base: _<https://...>_
  - API base: _<https://...>_
- **Migrations included:** _<file names | no>_

**Changed files → coverage:**

| Changed file | Function/method changed | Covered by |
|---|---|---|
| _<path>_ | _<symbol>_ | _<TC#.Step# | unit test name>_ |

> Empty cells in "Covered by" = coverage gaps = block the ticket.

**Dev unit tests in this PR:**
- _<test name> (<file>) — covers <what>_

**Newman collection (MANDATORY for any API change — Playwright reads this to author API tests):**
- Path: `tests/newman/<service>/<collection>.json` (must be the canonical, stable path the Playwright framework expects — no env-specific or branch-specific suffixes)
- Folders modified/added: _<Auth · Validation · Happy path · Error handling · Webhooks>_
- New requests added: _<list — each is referenced by name in TCs below; each must be self-contained enough that the Playwright engineer can author the equivalent test from the Newman request alone>_
- Playwright-readability check: every new Newman request has populated `headers`, `body`, `auth`, and at least one `test` script asserting the expected status — so it serves as a complete spec, not just a smoke ping

---

## 3. Regression Impact

- **Components/services touched:** _<list every module modified>_
- **Downstream consumers** (verified via grep, not guessed):
  - _<file/route/job> — uses <symbol>_
- **Existing Playwright specs that might break:** _<spec file names>_
- **Existing Newman folders that might break:** _<collection/folder names>_
- **Risk level:** _LOW | MEDIUM | HIGH_ — _<one-line reason>_

---

## 4. Dependencies & Deployment

- **Blocking dependencies:** _<other ticket/PR that must merge + deploy first | None>_
- **Deployment verification check (Playwright-runnable smoke):** _<one fast UI/API check confirming the fix is live before running the full TC suite>_
- **What to do if smoke check fails:** _<wait | comment back to dev | check feature flag>_
- **Feature flag:** _<flag name + state per env | N/A>_
- **Test data seeding:** _<how the data referenced in Step 0 gets into QA env: deploy hook | admin UI | API call by dev | already exists>_ — **QA cannot seed via DB; dev owns this.**

---

## 5. Coverage Matrices (mandatory — fill every cell)

### 5a. Input Partitions (per user input field or API parameter)

| Field | Valid partitions | Boundary values | Invalid forms | Covered by |
|---|---|---|---|---|
| _<field>_ | _<list>_ | _<min, min-1, max, max+1>_ | _<empty, null, wrong type, injection, overflow, unicode>_ | _<TC#.Step#>_ |

### 5b. Role × Action (mandatory if multi-role)

| Action | Participant | Admin | Org Admin | Super Admin |
|---|---|---|---|---|
| _<action>_ | _<TC#.Step# | ✗ TC#.Step# (must 403)>_ | _<...>_ | _<...>_ | _<...>_ |

### 5c. Failure-Mode Checklist (every item: `Yes — TC#.Step#` · `No — <reason>` · `N/A — <why>`)

**Data:** empty collection · single item · pagination boundary · max size · unicode/emoji · NULL vs empty string · duplicate submission

**State integrity:** illegal state transitions rejected — every status field that changes has a TC proving the API returns 4xx when given an invalid From → To pair (e.g., Approved → Draft must reject with 409)

**Concurrency:** simultaneous edits · double-submit · two tabs/devices · request retry (idempotency) · read/write race

**Auth:** expired token mid-flow · revoked token · role change mid-session · cross-tenant access (workspace A user reaches workspace B) · direct URL bypassing UI

**Time / locale:** non-UTC timezone (DST) · midnight boundary · multi-currency rounding · future/past dates

**Failure / degraded:** external service 5xx · external service timeout · malformed payload · partial write rollback · email/webhook send fail (does primary action still succeed?)

**Migration / deploy:** mid-deploy old/new code interaction · migration on prod-shaped data · rollback path · backfill for existing records

**ADR-specific:** audit log written for every state change · PII handling unchanged · queue consumer is idempotent on replay · dead-letter outcome verified

**QA-observability (mandatory):** every state change above has a UI element OR public API response that exposes it · every error condition produces a status code + response body Playwright can assert · every external side effect (email, webhook) has a Playwright-reachable inspection surface OR is moved to dev-only verification

### 5d. Externally-Observable State Mapping (mandatory)

> Every state mutation this PR introduces must be observable through one of: UI element, API response field, network call body, response status, response header. If no surface exposes a given mutation, dev MUST add one in this PR — or move that mutation to "Dev-Only Verification" and explicitly accept it cannot be QA-tested.

| State mutation | DB-only? | QA-observable surface | Covered by |
|---|---|---|---|
| _<e.g., activity.status set to APPROVED>_ | yes | `GET /api/v1/activities/<id>` → `status: "APPROVED"` | TC2.Step3 |
| _<e.g., audit log entry created>_ | yes | `GET /api/v1/audit?entity=activity&id=<id>` → row with `action: APPROVED` | TC2.Step4 |
| _<e.g., email queued>_ | yes | mailtrap UI at `<URL>` shows email to `<recipient>` with subject `<X>` | TC2.Step5 |

---

## 6. AC → Test Case Mapping

| AC# | Acceptance Criterion | Covered by | Evidence type |
|---|---|---|---|
| AC1 | _<text>_ | _<TC#.Step#>_ | _<URL assertion | response field | DOM state | network response>_ |

- **Unmapped ACs:** _None_ | _<list with reason — blocks "Ready for QA">_

---

## 7. Test Cases — Unified Step Format

> **Linear block format. Same conceptual components for UI and API — domain-appropriate labels.** Every TC has Step 0 (Setup) + numbered Steps. Every step is a sequence of labeled blocks (no tables for execution rows; tables don't fit screenshots or multi-line request bodies).
>
> **Screenshot placement rule (read once, applies to every UI step that has a screenshot):**
>
> **Screenshots are per-relevant-step, not blanket on every step.** Include screenshot slots only on steps where the assertion is partly visual — element layout/position/styling, modal or banner appearance, error state rendering, or the proof-of-fix moment for the TC's Primary Signal. Skip the screenshot on steps where the only assertion is a URL pattern, status code, response field, or DOM attribute (textual evidence is sufficient).
>
> **When a step DOES include a screenshot, it has its own pair**, both pasted inline within that step's block:
> - **Expected screenshot** — dev provides reference image at handoff
> - **Actual screenshot** — QA pastes observed image during execution
>
> **Screenshots are ALWAYS pasted inline within the step block, NEVER uploaded as generic ticket attachments.** Inline placement keeps assertion + visual evidence side-by-side; attachments break the cold-read flow. To paste inline in JIRA: copy the image to clipboard, click into the description at the placeholder line, paste — JIRA embeds the image at the cursor.
>
> **API steps never have screenshots.** The Response body is the evidence.
>
> **UI step format:**
> ```
> #### Step N — <short imperative description>
>
> **Action:**
>   1. <numbered exact instruction — navigate / click / type, with the Playwright locator from the Locator Reference>
>   2. <next instruction>
>
> **Expected Result:**
>   - <observable assertion — URL state, DOM attribute, element visibility, network response, console state>
>   - <observable assertion>
>
>   **Expected screenshot** (include only if step is visually relevant — see Screenshot placement rule above):
>   _Paste reference screenshot inline here._
>
> **Wait Condition (Playwright):**
>   <deterministic signal: `await expect(page).toHaveURL(...)` / `await expect(locator).toBeVisible()` / `await page.waitForResponse(predicate)`. NEVER `waitForTimeout`.>
>
> **Actual Result (QA fills during execution):**
>   - Pass / Fail:
>   - Observed state:
>
>   **Actual screenshot** (only if Expected screenshot above; QA pastes observed image inline within this step):
>   _Paste actual screenshot inline here during execution._
> ```
>
> **API step format:**
> ```
> #### Step N — <short imperative description>
>
> **Newman canonical:** `<collection> / <folder> / <request name>` — the Playwright engineer authors the API test from this Newman request. The markdown Request/Response below MUST match the Newman request exactly.
>
> **Request:**
>   ```
>   <METHOD> <full URL>
>   <Header: value>
>   <Header: value>
>
>   <body JSON, multi-line>
>   ```
>   - Required fields: <list with type + constraints>
>   - Optional fields: <list with default if omitted>
>
> **Response (Expected):**
>   - Status: `<code>`
>   - Body shape:
>     ```
>     { ... full JSON shape ... }
>     ```
>   - Assertions (Playwright):
>     - `expect(response.status()).toBe(<code>)`
>     - `expect(body.<field>).toBe(<value>)`
>     - <every assertion the test must make>
>   - Read-after-write (if applicable): `GET <path>` returns the same body
>
> **Wait Condition:** Synchronous endpoint — single-shot `await request.<method>(...)`. For async endpoints, name the polling URL + terminal status + max wait.
>
> **Actual Response (QA fills during execution):**
>   - Status:
>   - Body:
>   - Pass / Fail:
> ```
>
> **Source-of-truth rule for API:** the Newman request is canonical. The markdown Request/Response is a human-readable copy. If they disagree, Newman wins and the markdown is a defect. CI must include a Newman-vs-markdown drift check (or this is enforced manually at PR review).

---

### TC1 — _<descriptive title>_

**Type:** _UI | API | Mixed_
**Goal:** _<one sentence — what this TC proves>_
**Tags:** _<pick all that apply: `@smoke` (runs every commit), `@regression` (runs nightly), `@slow` (>30s), `@flaky-quarantine` (known flaky — must include reason)>_

#### Step 0 — Setup (Zero-Knowledge Baseline)

| Item | Value |
|---|---|
| UI base URL (QA env) | _<https://...>_ |
| API base URL (QA env) | _<https://...>_ |
| Preview override (if pre-merge) | _<URL | N/A>_ |
| User role | _<role + permission level>_ |
| Test account | _<email>_ |
| Credentials | Email: `<ENV_VAR_EMAIL>` · Password: `<ENV_VAR_PASSWORD>` (in Playwright `.env`) |
| Auth flow (manual) | _<modal | redirect | API token>_ — _<one-line how it works>_ |
| Auth flow (Playwright) | _<storageState file path if reusable | full login per test if isolation required>_ |
| Test data preconditions | _<entity IDs OR API GET to discover them>_ — example: `GET /api/v1/workspaces/acme-corp/challenges` returns at least one challenge with `enrolledByCurrentUser: true` |
| External services mocked in this TC | _<service name + fixture path, e.g., RewardSTACK token → `tests/fixtures/token.json`>_ OR _none — all calls go live_. Mock when the service is flaky in QA, has rate limits, or costs money per call. |
| If preconditions missing | _<dev seeds via deploy hook / API / admin UI BEFORE handoff — QA does NOT seed>_ |
| Rate limit | _<N attempts → M min lockout — Playwright must back off, do NOT retry past lockout>_ |
| Browser context | _<fresh per TC | shared via storageState — and why>_ |

**Locator Reference (UI elements this TC touches — Playwright-grade):**

| Element label | Playwright locator | Component (file) |
|---|---|---|
| "Get Started" button | `page.getByRole('button', { name: 'Get Started', exact: true })` | `SubmitActivityBanner` (`submit-activity-banner.tsx`) |
| "Activities" tab | `page.getByRole('tab', { name: 'Activities', exact: true })` | `ChallengeTabs` (`page.tsx`) |
| Tab active assertion | tab locator + `toHaveAttribute('aria-selected', 'true')` | — |

> **Locator preference order:** `getByRole` (with accessible name + `exact: true`) → `getByLabel` → `getByPlaceholder` → `getByText` → `getByTestId`. Reach for `getByTestId` only if no accessible name exists.
>
> **`data-testid` fallback rule (mandatory):** If a UI element has no accessible name (icon-only buttons, generic divs, third-party widgets without ARIA), the dev **MUST add `data-testid="<descriptive-name>"` in the same PR**. Never ship a TC whose locator depends on `nth-child`, class names, or XPath — those break on the next markup tweak and create false QA failures.

**Parameterization:** _<variant list — Playwright `for...of` loop | None>_

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

**Expected screenshot (reference image — what it should look like):**
_Paste reference screenshot inline here._

**Wait Condition (Playwright):**
- Step 4: `await expect(page).toHaveURL(/\/participant\/dashboard$/)`
- Step 5: `await expect(page.getByRole('tablist')).toBeVisible()` AND `await expect(page.getByRole('button', { name: 'Get Started', exact: true })).toBeVisible()`

**Actual Result (QA fills during execution):**
- Pass / Fail:
- Observed state:

**Actual screenshot (QA pastes observed image inline within this step):**
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

**Expected screenshot (reference image — what it should look like):**
_Paste reference screenshot inline here._

**Wait Condition (Playwright):**
`await expect(page).toHaveURL(/\?tab=activities$/)` AND `await expect(page.getByRole('tab', { name: 'Activities', exact: true })).toHaveAttribute('aria-selected', 'true')`

**Actual Result (QA fills during execution):**
- Pass / Fail:
- Observed state:

**Actual screenshot (QA pastes observed image inline within this step):**
_Paste actual screenshot inline here during execution._

---

#### Step 3 — Submit activity (API example)

**Newman canonical:** `tests/newman/changemaker/activities.json` → folder `Happy path` → request `Submit activity (participant)`. The Playwright engineer authors the API test from this Newman request. The Request/Response below MUST match the Newman request exactly.

**Request:**
```
POST <API base>/api/v1/workspaces/acme-corp/challenges/clg_abc123/activities
Authorization: Bearer <token from Step 0a>
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

**Wait Condition:** Synchronous endpoint — single-shot `await request.post(...)`. (For async endpoints, name the polling URL + terminal status + max wait.)

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

> Repeat the format for every error class the endpoint supports: 401 (no auth), 403 (wrong role), 404 (missing entity), 409 (conflict), 422 (business rule violation), 429 (rate limit). Every error is its own step in the same format.

---

#### Data Impact (QA-observable side effects only)

- _**Read-only:** "Nothing created or changed."_ — OR
- _**Creates:** `<entity>` — verify via `GET /api/v1/<path>/<id>` returns the new record. Fields needing uniqueness per Playwright run: `<field>` — use timestamp suffix._
- _**Modifies shared state:** `<setting>` — Playwright `afterEach` reverts via `<API call OR admin UI step>`._
- _**Triggers email:** verify via mailtrap inbox at `<URL>`; Playwright queries mailtrap API at `<endpoint>` and asserts subject + recipient._
- _**Triggers webhook:** verify via webhook.site bin at `<URL>`; Playwright polls bin API and asserts payload._

#### Dev-Only Verification (pre-handoff — NOT a QA TC; NOT automated)

> Effects QA cannot observe. Dev runs these inside Docker before transitioning to Ready for QA. Document them so we know they were checked.

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
- **Accessibility (mandatory for UI TCs):** Playwright runs `@axe-core/playwright` scan at end of TC — assert zero violations. Allowlist for known pre-existing violations (must be tracked in a follow-up ticket): _<list specific rules | none>_.
- **Known pre-existing console noise to ignore:** _<list specific messages | none>_
- **Container logs:** NOT in QA scope — see Dev-Only Verification.

---

### TC2 — _<title>_

> Repeat the full structure. Step 0 may say "Same env, auth, and locator reference as TC1 Step 0" — but the structural slot stays. The unified Step format applies to every step regardless of UI/API mix.

---

> **5+ TCs?** Return to Section 0 and split the ticket.

---

## 8. OUT-OF-SCOPE Justifications

> Every item answers: **"Why can it not fail in production?"** Acceptable answers: "Component X is not touched and is covered by spec Y," "Code path Z requires permission this user role cannot obtain," "Behavior W is gated behind flag F which is OFF in prod and removal is tracked in ticket K."

| Excluded behavior | Why it cannot fail in prod | Existing test/safeguard that proves it |
|---|---|---|
| _<case>_ | _<argument>_ | _<spec/component/flag reference>_ |

**DEFERRED (tag <reviewer> to decide before sign-off):**
- _<case — reason>_

---

## 9. Adversarial Pre-Mortem (mandatory before "Ready for QA")

> Run a separate Claude session with this prompt:
>
> > Given the PR diff and Testing Strategy below, generate 10 ways this code could fail in production that the strategy does NOT cover. Critical constraint: QA's Playwright framework can verify ONLY browser DOM/URL/console/network and public HTTP API responses — NOT database state, NOT container logs, NOT queue contents. Prioritize failure modes that would be INVISIBLE to QA's verification surface (DB-only state corruption, silent log errors, queue message loss, audit trail gaps). For each finding: trigger, failure mode, user impact, and either (a) the new TC that catches it, (b) the new public surface needed to make it observable, or (c) a written argument that automated tests in this PR catch it.
>
> Paste the output below. Each finding becomes a new TC, a new public surface in this PR, or a written justification.

| # | Failure scenario | Trigger | Impact | QA-visible? | Resolution |
|---|---|---|---|---|---|
| 1 | _<scenario>_ | _<trigger>_ | _<user impact>_ | yes/no | _<new TC# | new surface added | unit test <name> covers it>_ |

---

## 10. Pre-QA Handoff Checklist

> Every box checked before transitioning to "Ready for QA".

**Code & deploy (dev):**
- [ ] PR code-reviewed and approved
- [ ] Code is deployed to QA environment (verified via Section 4 smoke check from Playwright runner)
- [ ] All blocking dependencies merged AND deployed
- [ ] Feature flags set correctly per environment
- [ ] Clean stack verification ran locally — `docker compose down -v`, rebuild no-cache, full test suite green, container logs zero errors
- [ ] PHPCS + PHPUnit + **Newman (mandatory)** all green inside Docker
- [ ] `git diff` reviewed — only intended changes
- [ ] Test data exists in QA env (dev seeded via deploy hook / API / admin UI — QA cannot seed)

**Strategy completeness (dev):**
- [ ] Section 0 ticket-size gate cleared (≤5 TCs OR split performed)
- [ ] Behavior Change has a Playwright-observable Primary Signal
- [ ] Section 2 Coverage Map has every changed function mapped (no empty cells)
- [ ] **Newman collection updated** — every API change has a Newman request; folder structure preserved; collection lives at the canonical path the Playwright framework reads from
- [ ] **Newman-as-spec readiness** — every new Newman request has populated headers, body, auth, and `test` scripts. A Playwright engineer reading only the Newman request could author the equivalent test without consulting the markdown
- [ ] **Newman ↔ markdown parity** — for every API step in this ticket, the markdown HTTP block matches the named Newman request exactly (URL, method, headers, body, expected status)
- [ ] Regression Impact lists actual downstream consumers (grep-verified, not guessed)
- [ ] All matrices in Section 5 filled (no blanks): 5a Input Partitions · 5b Role × Action · 5c Failure-Mode Checklist
- [ ] **Section 5d Externally-Observable State Mapping complete** — every state mutation has a QA-visible surface OR is in Dev-Only Verification with written acceptance
- [ ] Every AC mapped to a TC step (Section 6)

**Per-TC completeness (dev) — applies UNIFORMLY to UI and API:**
- [ ] Every TC has a Step 0 zero-knowledge baseline
- [ ] **Every step is linear block format (no tables for execution rows)**
- [ ] **UI step has all required components:** `Action` · `Expected Result` · `Wait Condition` · blank `Actual Result`. **Screenshot slots (Expected + Actual) included on every visually-relevant step** — pasted inline within the step block, NEVER uploaded as a generic ticket attachment. Steps with purely textual assertions (URL pattern, DOM attribute, response field) skip the screenshot.
- [ ] **API step has all components:** `Newman canonical` reference · `Request` · `Response` (Expected) · `Wait Condition` · blank `Actual Response`
- [ ] Every UI Action names a precise Playwright locator (role + name + `exact: true`)
- [ ] **Every API Request names full HTTP shape AND the matching Newman request name** (`collection / folder / request`)
- [ ] Every Wait Condition names a deterministic signal (URL pattern, element state, response status — NOT `waitForTimeout`)
- [ ] Every Expected Result has concrete Playwright assertions (URL pattern, status code, response field, DOM attribute)
- [ ] Every TC has a Dev-Only Verification block (or "N/A — no DB/log/queue effects")
- [ ] Console / Network / Accessibility policy stated per TC (browser console = 0 errors, network failures = 0, axe scan = 0 violations on UI TCs)
- [ ] Locator Reference table populated; every interactive element this TC touches is listed
- [ ] **Every UI locator uses role/label/text/testid — NEVER `nth-child`, class names, or XPath.** If an element lacks an accessible name, dev added `data-testid` in this PR.
- [ ] **Step 0 declares external services mocked in this TC** (with fixture path) OR explicitly states "none — all calls go live"
- [ ] **Tags declared per TC** (`@smoke` / `@regression` / `@slow` / `@flaky-quarantine`)

**QA-observability hard checks (dev):**
- [ ] No Expected Result requires DB query, container shell, or log grep
- [ ] Every state mutation in Section 5d has a UI element OR public API response that exposes it
- [ ] Test account exists in QA env and dev logged in with it from a clean browser
- [ ] `.env` variable names exist in Playwright project's `.env.example` OR dev told QA to add them

**Cold-read check (dev does this last):**
- [ ] **Re-read the entire Testing Strategy as if you knew nothing about this ticket.** Could a stranger manually execute every TC top-to-bottom without asking a question? If no — fix the gap. This is the bar.

**Adversarial review:**
- [ ] Pre-mortem (Section 9) ran; findings either resolved (new TC / new surface / unit test) or justified

**Once all boxes checked:** Backlog → Analysis → Selected for Development → Development in Progress → Ready for QA. Walk every transition; do not skip.

---

## 11. After QA Starts

- **Don't push to the same branch while QA is automating.** If you must, comment on the ticket with the new commit SHA + which TCs need re-running.
- **If QA finds a bug:** fix → deploy → comment `Fixed in <SHA>, deployed to <env>. Ready for re-test.` → re-run Section 10 checklist for the changed area.
- **All Q&A on the JIRA ticket, not Slack.** Both Claudes can see the ticket; neither can see Slack.
- **If a TC turns out to be unautomatable** (e.g., relies on state QA can't observe): tag the dev. The fix is to add a verification surface, not to drop the TC.

---

## Why this template exists

Every section here exists because its absence caused a real prod incident or wasted QA cycle. The matrices in Section 5 force enumeration so coverage doesn't depend on the dev's vigilance. Section 5d ensures every state change is QA-observable — closing the "QA can't see DB" gap. The unified Step format in Section 7 means UI and API tickets are interchangeable to read and to automate. The cold-read check in Section 10 is the final gate: if a stranger can't run it, neither can QA.

**Testing is the first of three layers of defense — paired with staged rollouts and the prod log monitors. This template makes the first layer maximally rigorous; the other two layers catch what still slips through. Zero prod bugs is the target; no single layer reaches it alone.**
