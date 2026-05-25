# QA Environment Inventory

> **Purpose:** factual lookup data that the DS-13017 standard references but doesn't restate inline (URLs, roles, webhook receiver, pre-existing test participants, cross-cutting conventions). Use this when filling out a `tests/TESTING_STRATEGY.md`.
>
> **Authority:** the DS-13017 standard (`https://alldigitalrewards.atlassian.net/browse/DS-13017`) supersedes anything here that conflicts. If something feels stale, re-read DS-13017 first.

---

## URL verification rule (absolute)

Before posting any URL in a TS, dev MUST verify it points to a QA environment — not prod. Same rule applies to env-var values, test-data values, payloads, and webhook targets. There is no fixed allowlist; verification IS the rule.

**Hostname allowlist (default-safe):** `*.adrqa.info`, `localhost`, `webhook.site`.
**Hostname denylist (production-pattern, never write):** `*.alldigitalrewards.com`, `*.adrewards.com`, `*.rewardstack.com`, `*.rewardstack.net`. The denylist is the failsafe — even with verification, a hostname matching these patterns is automatically wrong and SHALL be replaced.

The cost of a single accidental prod hit (data corruption, real-money charges, customer-facing side effects) is far higher than the cost of the verification step.

---

## Verified QA URL inventory

### Core admin / auth surfaces

| Service | Real QA URL |
| --- | --- |
| Admin portal (RewardSTACK) base | `https://admin.adrqa.info` |
| Admin dashboard | `https://admin.adrqa.info/#/admin/dashboard` |
| Admin organization | `https://admin.adrqa.info/#/admin/organization` |
| Admin program | `https://admin.adrqa.info/#/admin/program` |
| Admin catalog | `https://admin.adrqa.info/#/admin/catalog` |
| Admin participant | `https://admin.adrqa.info/#/admin/participant` |
| Admin report | `https://admin.adrqa.info/#/admin/report` |
| Admin user | `https://admin.adrqa.info/#/admin/user` |
| Admin vendors | `https://admin.adrqa.info/#/admin/vendors` |
| Admin event emails | `https://admin.adrqa.info/#/admin/event-emails` |
| Admin AVS | `https://admin.adrqa.info/#/admin/avs` |
| Admin CSR | `https://admin.adrqa.info/#/admin/csr` |
| Admin default content | `https://admin.adrqa.info/#/admin/program/content/default` |

### Service domains

| Service | Real QA URL | Notes |
| --- | --- | --- |
| Reward Attribution (RA) | `https://ra.adrqa.info` | |
| RA return webhook | `https://ra.adrqa.info/api/return` | |
| FBM (Fastblock manager) | `https://fbm.adrqa.info` | |
| Chat service | `https://chat.adrqa.info` | POST endpoints only — root HEAD returns 404. |
| Card site (also serves claim-card) | `https://adr-cards.adrqa.info` | `claim-card` deploys here, NOT a separate subdomain. |
| Vendor API service | `https://vendor-api-service.adrqa.info` | |
| AVS service (proxy) | `https://admin.adrqa.info/api/avs` | |
| Marketplace | `https://stan1212test.mydigitalrewards.com` | Stan's QA tenant — safe to test against (NOT prod despite `mydigitalrewards.com` domain). |
| Sharecare account domain | `https://sharecare-account.adrqa.info` | |
| Redeem domain | `https://redeem.adrqa.info` | |
| Stan QA campaign redeem | `https://stanqa.redeem.adrqa.info/` | |

### Path-based services (NOT separate subdomains)

| Service | Real QA URL | Notes |
| --- | --- | --- |
| api-batch-facilitator | `https://ra.adrqa.info/api/batch/*` | Path-based on RA admin host. |
| catalog | `https://admin.adrqa.info/api/product/catalog/` | Path-based on admin host. |
| claim-card | `https://adr-cards.adrqa.info` | Deploys to existing card-account host. |

### Fulfillment URLs

| Service | Real QA URL |
| --- | --- |
| Galileo fulfillment | `https://galileo-fulfillment.adrqa.info` (replaces hardcoded prod `https://galileo-fulfillment.alldigitalrewards.com`) |
| Amazon Business fulfillment | `https://amazon-business-fulfillment.adrqa.info` |
| Amazon Business maintenance | `https://amazon-business.adrqa.info` |
| PayPal fulfillment | `https://paypal-fulfillment.adrqa.info` |
| Game vendor | `https://game-vendor.adrqa.info` |
| InComm fulfillment | `https://incomm-fulfillment.adrqa.info` |
| NeoCurrency fulfillment | `https://neocurrency-fulfillment.adrqa.info` |
| Replink fulfillment | `https://replink-fulfillment.adrqa.info` |

### Card account domains (live tenants)

| Domain | Status |
| --- | --- |
| `prepaid-bc.adrqa.info` | live |
| `pcr-account.adrqa.info` | live |
| `sharecare-account.adrqa.info` | live |
| `virtual-sharecare-account.adrqa.info` | live |
| `virtual-pcr-account.adrqa.info` | live |
| `virtual-adr-account.adrqa.info` | live |

### Account site URLs

| Service | Real QA URL |
| --- | --- |
| Virtual ADR account | `https://virtual-adr-account.adrqa.info` |
| EzePulse ATM account | `https://atm-ezepulse-account.adrqa.info` |

### Stale URLs (configured but 404 — skip in TS until QA cleanup PR lands)

- `redemption-fulfillment.adrqa.info`
- `charity-account.adrqa.info`
- `netskope-account.adrqa.info`
- `lg-account.adrqa.info`
- `admin.adrqa.info/health` (no `/health` route — use `curl -I https://admin.adrqa.info` for liveness; root returns 200)

---

## Webhook receiver

For TS that need to verify webhook delivery, use `webhook.site` (free, ephemeral, on the QA-only allowlist):

```
https://webhook.site/<token>
```

Tests poll `https://webhook.site/token/<token>/requests` for the captured webhook payload — array of captured requests with full payload + headers + timestamp. Assert on `body[0]` (latest) within a wait-loop bounded by 5s.

- **Token rotation:** one token per test (or per test group); receiver-side log persists ~7 days. Rotate tokens between major test runs.
- **Custom-response feature:** the webhook.site dashboard lets the receiver return any HTTP status — use for 4xx/5xx tests.
- **Connection-failure simulation:** point the webhook at `https://does-not-exist-<TICKET>.invalid/webhook` for guaranteed-broken delivery.

---

## The 8 canonical roles

Per QA's `config/auth/roles.ts`. Use these exact names in TS Step 0 lines (`Authenticate as <role>`):

```
superAdmin · admin · accounting · reporting · configuration · customerService · participantView · programAdmin
```

QA's `auth.setup.ts` wires each role to credentials and maintains pre-authenticated `storageState` files per role. **Dev TS cites the role name; QA supplies the credentials from its secure store.**

**Forbidden:** generic terms ("admin-scoped", "any admin"), invented env-var names (`BATCH_ADMIN_TOKEN`, `QA_SUPERADMIN_TOKEN`), literal accountIds / usernames / passwords / tokens. Inventing plausible-looking env-var names is the #1 most common defect; they don't exist and even if they did, dev shouldn't have to know them.

**New role needed?** Extend QA's matrix first (separate ticket / PR). Then cite the new role name in the TS.

### Tenant-specific roles (outside the canonical 8)

If a TC needs LX Hausys admin, AK Admin, Changemaker SA, or an RA service-account login, cite the tenant + role plainly: `"Authenticate as LX Hausys admin"` / `"Authenticate as RA Payment service account"`. Don't invent env-var names; QA wires these too.

---

## Pre-existing test data (READ-ONLY — never mutate; never DELETE; never PATCH balance)

Before seeding new test data, check whether QA already has a fixture or pre-existing entity that fits.

### Pre-existing test participants

| Participant | Where | Purpose | Notes |
| --- | --- | --- | --- |
| `stan12121212` | RewardSTACK admin | Stan's personal QA participant; has reward history | **PROTECTED** — never modify. GET-only. Past incident: a destructive scanner wiped this; recovered from backup. |
| `stan1234` | RewardSTACK admin | Stan's secondary QA participant | Read-only by default |
| `sharecare-stan` | sharecare-account.adrqa.info | Sharecare card-account testing | Read-only |

**Rule:** if a TC needs to mutate participant state (balance change, tier change, status change), seed a NEW participant with `qa_<spec>_<timestamp>` unique-per-run naming. Never mutate `stan12121212` or any other listed read-only participant.

### Pre-existing test orgs / programs / tenants

| Entity | Identifier | Purpose |
| --- | --- | --- |
| Stan's marketplace tenant | `stan1212test.mydigitalrewards.com` | Stan's personal QA marketplace tenant; safe to test against (NOT prod despite `mydigitalrewards.com` domain) |
| Stan's QA campaign redeem | `stanqa.redeem.adrqa.info` | Per-campaign redeem testing |
| Real QA programs / orgs / SKUs | dev supplies | Dev SHALL list specific UUIDs for any program/org/SKU referenced in TCs |

**Rule:** if a TC references a program / org / SKU by `<placeholder>`, replace with a concrete UUID before marking Ready for QA. Don't ship `<placeholder>` to QA — QA can't guess.

---

## Cross-cutting conventions

DS-13017 doesn't restate these; they apply to every TS unless explicitly overridden.

### Email inbox

- Every test email recipient SHALL be `suhrobu+<purpose>@alldigitalrewards.com`. Plus-addressing routes to Stan's Workspace inbox so Gmail readback works.
- Email subject is the primary filter — keep subjects stable across releases, OR explicitly note the subject change in the TS.
- Single-use URLs in emails (password reset, SSO invite) are CONSUMED on first use — generate fresh URLs per test run.
- Never assume an email arrives instantly — bounded wait loop (default 30s, max 60s for slow SMTP paths).

### Date / time

- All API timestamps are UTC (`Z` suffix or `+00:00`). Tests SHALL not rely on local timezone.
- Tests SHALL not depend on wall-clock time of day unless explicitly testing business-hours behavior (e.g., chat CSR availability).
- Date parsing: ISO 8601 (`YYYY-MM-DDThh:mm:ssZ`); MM/DD/YYYY only when explicitly testing US-formatted user input.
- For "now" assertions: allow ±5s tolerance against server time (clock skew between test runner and server).
- For scheduled / cron behavior: dev SHALL provide a manual-trigger API endpoint (Async triggers row in the Prerequisites table) — never "wait until X seconds after enqueue".

### Locale

- Default: `en_US`. Every TS unless otherwise stated tests against `en_US`.
- For locale-specific tickets (i18n changes, currency formatting, date formatting): dev SHALL declare which locales QA tests (e.g., `en_US, es_ES, fr_FR`) and what changes per locale.
- URL `?lang=` query param controls locale on most QA endpoints.
- Catalog API returns localized strings — TCs assert on language-agnostic identifiers (SKU, ID) when possible, not on translated text.

### Currency / amount

- API request/response amounts are CENTS as integers for most ADR endpoints (e.g., `"amount": 100` = $1.00). Confirm per service — some legacy endpoints use dollars as floats. Dev SHALL declare the format in the TC.
- Currency code defaults to USD; non-USD requires explicit `currency` field.
- Display strings (UI) format with currency symbol + 2 decimals (e.g., `$1.00`) — UI TCs assert on the display string, not the underlying value.

### Idempotency keys

- Client-generated (RA convention) — typically `Idempotency-Key` HTTP header per request.
- Format: UUID v4 OR client-namespaced string (e.g., `qa_otp_smoke_${Date.now()}`).
- Same key + same body within window → same response (replay-safe).
- Same key + different body → 409 Conflict (mismatch).
- For TCs that intentionally replay: generate the key ONCE, fire request twice, assert second response is identical.

### Webhook retry / back-off

- RA → fulfillment webhook delivery: retries with exponential back-off on 5xx / connection failure (default: 3 retries at 5s, 30s, 5min).
- Idempotency: receiver SHALL handle duplicate delivery (same `event_id` + same payload) without side-effect amplification.
- For TCs that exercise retry behavior: use webhook.site custom-response to fail N times then succeed; verify retry attempt count + final success.

### Browser / viewport (UI tickets — implicit default coverage)

QA verifies UI pages render correctly at the full standard width set: **320, 375, 390, 414, 768, 1024, 1280, 1920 px** — layout, text, content, images. Default coverage, no per-ticket declaration. If layout is intentionally locked to one width, dev flags it in `Risk notes`. Default browser = Chrome.

---

## Process lessons (internalize these)

1. **HEAD probe alone is insufficient.** POST-only or auth-required endpoints reject HEAD and return 404/405, but work in production. Cross-check candidate URLs against active spec usage in the consumer repo's `tests/`.
2. **Subdomain-pattern guessing fails for path-based services.** Catalog and api-batch-facilitator look "missing" by `<service>.adrqa.info` heuristic but live as routes on parent admin hosts. Before declaring a service "needs new infra," check if it's path-based.
3. **Repo with no `.github/workflows/` = no preview deploys.** A brand-new repo isn't QA-testable just because the code exists; check the repo's `.github/` before assuming a PR preview will be available.
4. **Closed PRs don't deploy.** "Ready for QA" + closed PR = QA has nothing to test. Check PR `state` (not just `mergedAt`) before transitioning tickets.
5. **Inventory drift is real.** Some URLs in `config/core/urls.ts` 404 today. Trust the live probe, not the config file alone.
6. **Don't trust memory or prior conversation for git/PR/deploy state.** Run `gh pr view <N> -R alldigitalrewards/<repo> --json isDraft,mergedAt,state,updatedAt` before claiming any PR's state in a TS.
7. **Env-var names MUST come from real `.env.example`** — grep before naming. Never invent plausible-looking names.
