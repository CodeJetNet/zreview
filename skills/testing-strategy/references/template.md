# Testing Strategy — <TICKET-KEY> <ticket summary>

> **Standard:** [DS-13017 — Testing Strategy Developer Reference — QA Independence Contract](https://alldigitalrewards.atlassian.net/browse/DS-13017)
> **Branch:** [`<branch-name>`](https://github.com/alldigitalrewards/<repo>/tree/<branch-name>)
> **PRs:** [#<N>](https://github.com/alldigitalrewards/<repo>/pull/<N>) (+ any cross-repo PRs)
> **Author:** <dev-name>, <YYYY-MM-DD>

---

## Testing type

**Pick exactly ONE.** A PR spanning both surfaces → split into two cross-linked tickets under one Epic. WCAG folds into UI.

- [ ] **API** — backend; no browser
- [ ] **UI** — browser-driven; Chrome unless declared

---

## Prerequisites — Universal (always required)

A **recipe** = the instructions (an API call or a UI walkthrough) QA follows to create users, data, or events. Dev provides recipes; QA runs them.

| Item | Recipe |
| --- | --- |
| **Before / After** | **Before:** _<one sentence — what the user sees today>_. **After:** _<one sentence — what the user sees after this change>_. |
| **QA env URL** | _<the approved QA URL for the app under test — verify against `references/qa-environment-inventory.md`>_ |
| **Observable side effects** | _<surfaces QA can observe: API response, UI state, or email>_. When email: declare subject, sender, body phrase, link target. **DB/queue/log-only effects are untestable — dev must expose them via API or UI before the TS is publishable.** |
| **PRs in scope** | _<all PR URLs delivering this coherent change, across all repos>_ |
| **Depends on** | _<other tickets that must be merged or completed before this is testable, or `none`>_ |
| **Epic** (if split) | _<link to parent Epic, or `none`>_ |
| **Risk notes** | _<areas the dev isn't 100% certain about — flag for QA. Or `none`>_ |
| **Ready-to-test signal** | All PRs merged ([GitHub link]) AND latest deploy's smoke run green ([link to smoke run]). |

---

## Prerequisites — If API ticket

QA creates all data via API.

| Item | Recipe |
| --- | --- |
| **Auth** | Authenticate as `<superAdmin / admin / accounting / reporting / configuration / customerService / participantView / programAdmin>` (per `config/auth/roles.ts`). Token-mint endpoint: `POST /token` (or service-specific). QA supplies account + creds from its secure store — never paste accountIds / usernames / passwords / tokens. |
| **Roles** | Pre-provisioned QA accounts cover the matrix roles. **New role introduced by this PR?** Recipe: API method + path + payload. E.g., `POST /users/{id}/roles` with `{ "role": "read-only-auditor" }`. Otherwise: `none`. |
| **Seed data** | API method + path + payload per entity, with unique-per-run naming `qa_<spec>_<timestamp>`. Or `none` if the TC uses an existing entity. |
| **Feature flags** | Flag name + admin API endpoint QA hits to confirm it's ON. E.g., `GET /admin/feature-flags/<flag>` returns `{ "enabled": true }`. Or `none`. |
| **Async triggers** | API endpoint to FIRE the background work on demand + endpoint to FETCH the result. If cron-scheduled, the fire endpoint must bypass the schedule. Or `none` if synchronous. |
| **Endpoint(s) under test** | HTTP method + path of the endpoint this PR introduces or modifies. |
| **Dependencies** | External services this endpoint calls (payment, CRM, queue, partner API, email provider) — name + expected behavior if the dependency fails (e.g., "returns 503 with retry-after header" or "writes to DLQ and returns 202"). Or `none`. |
| **QA-reachable path** | HTTP method + URL **QA actually calls**, which may differ from the PR's internal route. If proxied behind a gateway, cite the proxied URL (e.g., PR has `POST /` but QA calls `POST https://admin.adrqa.info/program-content/`). Declare both when they differ. |
| **Pre-existing or new?** | `new` = QA tests only the new contract. `pre-existing (modified)` = QA also verifies the old contract still holds for existing callers (no breaking change). State which. |
| **Full request payload** | Every field + type + valid example + invalid example. No `etc.`, no "obvious fields". |
| **Auth header** | Exact header name + token source. E.g., `Authorization: Bearer <token from POST /token>`. |
| **Auth runtime verification** | **Mandatory.** Paste a literal `curl` + observed 2xx for each distinct method+path, citing the PR HEAD SHA tested. Re-run on force-push or new commits. Example: <br>`$ curl -i -H "Authorization: Bearer $TOKEN" https://admin.adrqa.info/api/<path>` <br>`HTTP/1.1 200 OK` <br>`verified at HEAD <commit-sha>` |
| **Expected response** | For **every** status code returned (happy AND every negative): status code + body shape + key fields + error message format. |
| **Write-side observability** | If QA can't see the write directly, where does proof show up? Read-side surface (e.g., `GET /api/content/{id}` after PUT). Negatives covered only by dev unit tests: list as `(covered by PHPUnit: <Test::method>)`. Sibling tickets owning write-path E2E: linked with `?focusedCommentId=N`. |
| **Idempotent?** | `yes` / `no`. If no, QA uses fresh unique inputs each run. |
| **Rate limits** | Calls per minute + retry behavior. Or `none known`. |

---

## Prerequisites — If UI ticket

QA creates all data via UI.

| Item | Recipe |
| --- | --- |
| **Auth** | Login URL + role from `<superAdmin / admin / accounting / reporting / configuration / customerService / participantView / programAdmin>` (per `config/auth/roles.ts`), or SSO entry point. No usernames or passwords in the ticket — QA pulls from its secure store. |
| **Roles** | Pre-provisioned QA accounts cover the matrix roles. **New role introduced by this PR?** UI grant steps. E.g., "Admin Panel → Users → select user → `Add Role` → pick `read-only-auditor` → Save". Otherwise: `none`. |
| **Seed data** | Step-by-step UI walkthrough to create each required entity, with unique-per-run naming. E.g., "Programs page → `New Program` → Name = `qa_program_<timestamp>` → Save". |
| **Feature flags** | Admin panel URL + UI toggle steps to verify ON. Or `none`. |
| **Navigation path** | URL → click X → click Y to reach the feature. |
| **Data preconditions** | Which seed-data UI flows must run first. Or `none`. |
| **External sandbox** | Third-party test service this feature integrates with (payment, CRM, fulfillment, SSO) — name + creds-store reference (not the creds themselves). Or `none`. |
| **WCAG scope** | WCAG criteria this PR affects (e.g., contrast, keyboard nav, ARIA labels, focus order, screen-reader text) — or `none`. |

---

## Test cases

Written so **anyone** can execute manually with **zero prior knowledge**. No "Navigate / Verify" shorthand.

**Coverage rule: ONE happy E2E + minimum negatives for THIS change. 2-5 TCs total. If you'd need 6+, split the ticket.**

Minimum negative categories: invalid input · wrong role (authorization) · empty/null. API tickets also: boundary values. API negatives = 4xx + documented error body. UI negatives = error toast / inline validation / blocked submit / redirect. Only negatives this ticket's PR introduces — pre-existing failure paths belong on a separate ticket.

For multi-tenant or org-scoped endpoints, also include a **cross-tenant negative** (admin of org A → 403/404 on org B's resource), unless the endpoint is explicitly global.

### TC1 — <Happy path name>

#### If API ticket — Step 0 + Request / Expected / Actual

```
Step 0 — Preconditions
  - Base URL: <full canonical API base URL>
  - Auth: Authenticate as <role> via POST /token (QA supplies account + creds from its store)
  - Feature flag: <name> = ON (verify via GET /admin/feature-flags/<flag>)
  - Seed data: <entities> created via <POST endpoints>, with unique-per-run naming qa_<spec>_<timestamp>

Step 1
  Request:  <method + path + headers + payload>
  Expected: <status code + body shape + key field values>
  Actual:   <filled by tester at run time>

Step 2
  Request:  ...
  Expected: ...
  Actual:   <filled by tester at run time>
```

#### If UI ticket — Step 0 + Action / Expected / Actual

```
Step 0 — Preconditions
  - URL: <full canonical app URL>
  - Role / login: <role name + login URL; QA supplies account + creds from its store>
  - Feature flag: <name> = ON (verify by <admin panel UI step>)
  - Seed data: <entities> created via <UI walkthrough>, with unique-per-run naming qa_<spec>_<timestamp>

Step 1
  Action:   <exact click target with selector or label>
  Expected: <observable UI state — text visible, element enabled, page change, etc.>
  Actual:   <filled by tester at run time>

Step 2
  Action:   ...
  Expected: ...
  Actual:   <filled by tester at run time>
```

### TC2 — <Negative case name>

_Same format. Reproduce one negative category this PR introduces (invalid input · wrong role · empty/null · boundary)._

### TC3 — <Negative case name>

### TC4 — <Negative case name>

### TC5 — <Negative case name (optional)>

_If you'd need a 6th TC, split the ticket._

### Cross-tenant negative (multi-tenant endpoints only)

_Admin of org A → 403/404 on org B's resource. Skip if the endpoint is explicitly global; if skipped, state `Cross-tenant negative: N/A — endpoint is explicitly global`._

---

## Pre-QA Handoff Checklist

Dev confirms **every box** before marking the ticket **Ready for QA**. Jira status = `Ready for QA` **AND** every box below checked. If a box can't be checked, the TS is not ready. Boxes stay `[ ]` until the underlying condition is met — never pre-check decoratively.

### Universal

```
[ ] Testing type declared (UI or API, not both)
[ ] QA-only host — no prod, no client-facing domain, no Vercel preview
[ ] No credentials in the ticket (no accountIds, usernames, passwords, tokens, API keys)
[ ] No real user PII in payload examples — synthetic test data only (suhrobu+<spec>@alldigitalrewards.com, +1 (555) 010-0100, "Test User")
[ ] Before / After: one user-visible sentence each
[ ] All PRs delivering this coherent change linked in Prerequisites
[ ] Depends on tickets declared (or 'none')
[ ] If split into multiple tickets, all linked under a single Epic
[ ] No unrelated changes bundled in this ticket
[ ] Risk notes declared (or 'none')
[ ] All recipes verified by running them — pasted curl / gh api / UI walkthrough with actual response or screenshot in the TS. Self-attestation without pasted proof is not acceptable.
[ ] If email is a verification surface: subject / sender / body / link target declared
[ ] Ready-to-test signal: all PRs merged + smoke run green link
[ ] Test cases executable manually with zero prior knowledge
[ ] Out-of-scope keys/fields named explicitly (e.g., `{ "newField": "tested", "legacyField": "out-of-scope — see DS-XXXXX" }`)
[ ] Negatives limited to failure paths introduced by THIS change
[ ] Repro steps for the pre-fix bug (zero prior knowledge)
[ ] TS lives in tests/TESTING_STRATEGY.md on the branch (not in the JIRA description body, not in a comment)
[ ] tests/TESTING_STRATEGY.md uploaded to the JIRA ticket as an attachment (via REST curl)
[ ] JIRA description carries the minimal pointer block (Before/After + testing type + ready-to-test signal + GitHub link to the file)
[ ] TS readable in 5 minutes — split the ticket if longer
[ ] If this TS references write/clone/update paths NOT observable via QA's surfaces, the sibling ticket(s) and test file(s) owning those paths are linked in Write-side observability
```

### If API

```
[ ] Auth recipe = token-mint endpoint + role name(s)
[ ] Role grant recipe (only if this PR adds a new role type or permission)
[ ] Seed data API recipe with unique-per-run naming
[ ] Feature flag admin API + verify call
[ ] Async trigger API + fetch-result API (with on-demand trigger for time/schedule-based work)
[ ] Pre-existing or new endpoint declared; if pre-existing, backwards-compat verification included
[ ] Dependencies declared (external services + failure behavior, or 'none')
[ ] QA-reachable path declared and may differ from PR's internal route (gateway-proxied or direct ingress)
[ ] Full payload (no "etc.", no "obvious fields")
[ ] Exact auth header + token source
[ ] Auth runtime verification pasted (curl + observed 2xx status) for each method+path under test, citing PR HEAD SHA
[ ] Response shape for EVERY status code returned
[ ] If endpoint is multi-tenant or org-scoped, cross-tenant negative test case included (or noted as 'endpoint is explicitly global')
[ ] Idempotent? declared (yes / no)
[ ] Rate limits declared (calls/min + retry behavior, or 'none known')
[ ] Test cases use Step 0 + Request / Expected / Actual
[ ] No browser screenshots (move them to a UI ticket)
```

### If UI

```
[ ] Login URL + role name(s); creds-store reference only (no inline credentials)
[ ] Post-login screenshot pasted in the TS showing the role badge or dashboard for the cited QA account, proving the login recipe lands at the right place with the right role
[ ] Role grant UI walkthrough (only if this PR adds a new role type or permission)
[ ] UI seed-data walkthrough with unique-per-run naming
[ ] Feature flag verify steps via admin panel UI
[ ] Step-by-step nav path with exact selectors or labels
[ ] Data preconditions declared (which seed-data flows must run first)
[ ] External sandbox declared (name + creds-store reference, or 'none')
[ ] WCAG scope declared (criteria affected, or 'none')
[ ] Browser declared if other than Chrome
[ ] Test cases use Step 0 + Action / Expected / Actual
[ ] No API calls used for setup or assertions (move to API ticket)
```

---

## Cold-read check (final gate)

A stranger reading this TS with zero prior context must be able to manually execute every TC top-to-bottom without asking a question. If they would need to ask, the TS is incomplete — fix the gap before marking Ready for QA.
