# QA Environment Inventory (verified 2026-04-29 by Stan)

> **Source:** Stan (QA team lead) v7 universal standard file `2026-04-29-qa-ticket-writing-standard-FINAL.md` (supersedes v6 `2026-04-28-feedback-for-joe-FINAL.md`). All URLs verified live via HEAD probe + active spec usage in `alldigitalrewards/rewardstack-qa-playwright`. All env-var names verified against the QA repo's `.env.example`. All CI tag names verified against the QA repo's `package.json`.
>
> **Read this file before authoring any URL, env-var, role, or tag in a Testing Strategy.** Inventing plausible-looking names ("BATCH_ADMIN_TOKEN", "qa-batch.alldigitalrewards.com") is the single most common defect in dev-authored tickets and the one Stan calls out the most.

---

## The URL verification rule (absolute)

Before posting any URL in a ticket, dev MUST verify it points to a QA environment — not prod. Same rule applies to env-var values, test-data values, payloads, and webhook targets — every external destination referenced by a TC. **There is no fixed allowlist.** The rule is the verification itself: confirm the destination is QA before placing it in the ticket. If a destination cannot be verified as QA, it does not belong in the ticket.

The cost of a single accidental prod hit (data corruption, real-money charges, customer-facing side effects) is far higher than the cost of the verification step. Dev owns this verification before handoff.

**Hostname allowlist (default-safe):** `*.adrqa.info`, `localhost`, `webhook.site`. **Hostname denylist (production-pattern, never write):** `*.alldigitalrewards.com`, `*.adrewards.com`, `*.rewardstack.com`, `*.rewardstack.net`. The denylist is the failsafe — even with the verification rule above, a hostname matching the denylist patterns is automatically wrong and SHALL be replaced before handoff.

---

## Verified QA URL inventory

### Core admin / auth surfaces

| Service | Constant in `config/core/urls.ts` | Real QA URL | Probe |
|---|---|---|---|
| Admin portal (RewardSTACK) base | `baseUrl` (env `BASE_URL`) | `https://admin.adrqa.info` | ✅ 200 |
| Admin dashboard | `adminUrls.dashboard` | `https://admin.adrqa.info/#/admin/dashboard` | ✅ |
| Admin organization | `adminUrls.organization` | `https://admin.adrqa.info/#/admin/organization` | ✅ |
| Admin program | `adminUrls.program` | `https://admin.adrqa.info/#/admin/program` | ✅ |
| Admin catalog | `adminUrls.catalog` | `https://admin.adrqa.info/#/admin/catalog` | ✅ |
| Admin participant | `adminUrls.participant` | `https://admin.adrqa.info/#/admin/participant` | ✅ |
| Admin report | `adminUrls.report` | `https://admin.adrqa.info/#/admin/report` | ✅ |
| Admin user | `adminUrls.user` | `https://admin.adrqa.info/#/admin/user` | ✅ |
| Admin vendors | `adminUrls.vendors` | `https://admin.adrqa.info/#/admin/vendors` | ✅ |
| Admin event emails | `adminUrls.eventEmails` | `https://admin.adrqa.info/#/admin/event-emails` | ✅ |
| Admin AVS | `adminUrls.avs` | `https://admin.adrqa.info/#/admin/avs` | ✅ |
| Admin CSR | `adminUrls.csr` | `https://admin.adrqa.info/#/admin/csr` | ✅ |
| Admin default content | `adminUrls.defaultContent` | `https://admin.adrqa.info/#/admin/program/content/default` | ✅ |

### Service domains

| Service | Constant | Real QA URL | Notes |
|---|---|---|---|
| Reward Attribution (RA) | `raAdminUrl` (env `RA_URL`) | `https://ra.adrqa.info` | ✅ |
| RA return webhook | `webhookUrls.returnApi` (env `WEBHOOK_RETURN_URL`) | `https://ra.adrqa.info/api/return` | ✅ |
| FBM (Fastblock manager) | `fbmUrl` (env `FBM_URL`) | `https://fbm.adrqa.info` | ✅ |
| Chat service | `chatBaseUrl` (env `CHAT_BASE_URL`) | `https://chat.adrqa.info` | POST endpoints only — root HEAD returns 404 (no GET handler). 4 active specs prove it works. |
| Card site (also serves claim-card) | `cardSiteUrl` (env `CARD_SITE_URL`) | `https://adr-cards.adrqa.info` | ✅ — `claim-card` deploys here, NOT a separate subdomain |
| Vendor API service | `vendorApiServiceUrl` (env `VENDOR_API_SERVICE_URL`) | `https://vendor-api-service.adrqa.info` | ✅ |
| AVS service (proxy) | `avsServiceUrl` (env `AVS_URL`) | `https://admin.adrqa.info/api/avs` | ✅ |
| Marketplace | `marketplaceUrl` (env `HOST_URL`) | `https://stan1212test.mydigitalrewards.com` | host-configurable |
| Sharecare account domain | `domains.sharecareAccount` | `https://sharecare-account.adrqa.info` | ✅ |
| Redeem domain | `domains.redeemDomain` | `https://redeem.adrqa.info` | ✅ |
| Stan QA campaign redeem | `campaignUrls.stanQaRedeem` (env `CAMPAIGN_REDEEM_URL`) | `https://stanqa.redeem.adrqa.info/` | ✅ |

### Path-based services (NOT separate subdomains)

| Service | Real QA URL | Notes |
|---|---|---|
| **api-batch-facilitator** | `https://ra.adrqa.info/api/batch/*` | Path-based on RA admin host. ✅ HEAD 401 = exists. NOT a separate subdomain. |
| **catalog** | `https://admin.adrqa.info/api/product/catalog/` | Path-based on admin host. 5 active specs. NOT a separate subdomain. |
| **claim-card** | `https://adr-cards.adrqa.info` | Deploys to existing card-account host. NOT a new subdomain. |

### Fulfillment URLs

| Service | Constant | Real QA URL |
|---|---|---|
| Galileo fulfillment ⚠️ **DS-12806 F1 fix** | `fulfillmentUrls.galileo` (env `GALILEO_FULFILLMENT_URL`) | `https://galileo-fulfillment.adrqa.info` (replaces hardcoded prod `https://galileo-fulfillment.alldigitalrewards.com` — 1-char fix: `alldigitalrewards.com` → `adrqa.info`, or use `{{galileo_url}}` env variable) |
| Amazon Business fulfillment | `fulfillmentUrls.amazonBusiness` | `https://amazon-business-fulfillment.adrqa.info` |
| Amazon Business maintenance | `fulfillmentUrls.amazonBusinessMaintenance` | `https://amazon-business.adrqa.info` |
| PayPal fulfillment | `fulfillmentUrls.paypal` | `https://paypal-fulfillment.adrqa.info` |
| Game vendor | `fulfillmentUrls.gameVendor` | `https://game-vendor.adrqa.info` |
| InComm fulfillment | `fulfillmentUrls.incomm` | `https://incomm-fulfillment.adrqa.info` |
| NeoCurrency fulfillment | `fulfillmentUrls.neocurrency` | `https://neocurrency-fulfillment.adrqa.info` |
| Replink fulfillment | `fulfillmentUrls.replink` | `https://replink-fulfillment.adrqa.info` |

### Card account domains (live tenants)

| Domain | Status |
|---|---|
| `prepaid-bc.adrqa.info` | ✅ 200 |
| `pcr-account.adrqa.info` | ✅ 200 |
| `sharecare-account.adrqa.info` | ✅ 200 |
| `virtual-sharecare-account.adrqa.info` | ✅ 200 |
| `virtual-pcr-account.adrqa.info` | ✅ 200 |
| `virtual-adr-account.adrqa.info` | ✅ 200 |

### Account site URLs

| Service | Constant | Real QA URL |
|---|---|---|
| Virtual ADR account | `accountSiteUrls.virtualAccount` (env `ACCOUNT_SITE_URL`) | `https://virtual-adr-account.adrqa.info` |
| EzePulse ATM account | `accountSiteUrls.ezepulseAtm` (env `EZEPULSE_ATM_URL`) | `https://atm-ezepulse-account.adrqa.info` |

### Webhook receiver

For tests that need to verify webhook delivery, use `webhook.site` (free, ephemeral, on the QA-only allowlist):

```
https://webhook.site/<token>
```

Tests poll `https://webhook.site/token/<token>/requests` for the captured webhook payload.

### Stale URLs (configured but currently 404 — skip in tickets until QA cleanup PR lands)

- `fulfillmentUrls.redemption` → `https://redemption-fulfillment.adrqa.info`
- `domains.charityAccount` → `charity-account.adrqa.info`
- `domains.netskopeAccount` → `netskope-account.adrqa.info`
- `domains.lgAccount` → `lg-account.adrqa.info`
- `admin.adrqa.info/health` (no `/health` endpoint exists on admin — use `curl -I https://admin.adrqa.info` for liveness; root returns 200)

---

## Authentication: cite the ROLE, not env-var names

QA's `auth.setup.ts` already wires the **8 canonical roles** to credentials and maintains pre-authenticated `storageState` files per role. **Dev tickets cite the role name; QA handles credentials.** Inventing env-var names like `BATCH_ADMIN_TOKEN`, `QA_SUPERADMIN_TOKEN`, `ENV_SUPERADMIN_EMAIL` is forbidden — they don't exist, and even if they did, dev shouldn't have to know them.

### The 8 canonical roles (use these names verbatim in tickets)

| Role | Use case |
|---|---|
| **Super Admin** | Full admin access — default for most TCs |
| **Org Admin** | Organization-scoped admin |
| **Admin View Only** | Read-only admin |
| **Accounting** | Accounting-scoped admin |
| **Configuration** | Configuration-scoped admin |
| **Customer Service** (CSR) | CSR / chat-agent flows |
| **Participant View** | Participant-side / non-admin user |
| **Reporting** | Reporting-scoped admin |

### Step 0 form (use this exact pattern, not env-var names)

```
| Auth | Authenticate as Super Admin (QA's auth.setup.ts handles credentials) |
| Role | Super Admin                                                          |
```

For RBAC matrices that loop roles, just list the role names. QA loops with the right `storageState` per role:

```
| Role             | Expected status | Expected response |
|------------------|-----------------|-------------------|
| Super Admin      | 200             | Full participant data |
| Org Admin        | 200             | Org-scoped data only  |
| Customer Service | 403             | Forbidden             |
| Participant View | 401             | Unauthenticated       |
```

### Tenant-specific roles (not in the canonical 8 — cite them plainly)

If a TC needs LX Hausys, AK, Changemaker, or an RA service-account login, cite the tenant + role plainly: `"Authenticate as LX Hausys admin"` / `"Authenticate as RA Payment service account"`. Don't invent env-var names — QA wires these too.

### Reference: env-var names backing each role (QA-side use only — dev tickets don't cite these)

This is what QA's `auth.setup.ts` reads under the hood. Keep this only as a reference if a ticket genuinely needs to expose the env-var (rare).

| Role | Username env var | Password env var |
|---|---|---|
| Super Admin | `SUPER_ADMIN_USERNAME` | `SUPER_ADMIN_PASSWORD` |
| Org Admin | `ORGANIZATION_ADMIN_USERNAME` | `ORGANIZATION_ADMIN_PASSWORD` |
| Reporting | `REPORTING_USERNAME` | `REPORTING_PASSWORD` |
| Accounting | `ACCOUNTING_USERNAME` | `ACCOUNTING_PASSWORD` |
| Configuration | `CONFIGURATION_USERNAME` | `CONFIGURATION_PASSWORD` |
| Participant View | `PARTICIPANT_VIEW_USERNAME` | `PARTICIPANT_VIEW_PASSWORD` |
| Customer Service | `CUSTOMER_SERVICE_USERNAME` | `CUSTOMER_SERVICE_PASSWORD` |
| Admin View Only | `ADMIN_VIEW_ONLY_USERNAME` | `ADMIN_VIEW_ONLY_PASSWORD` |
| RA Admin | `RA_ADMIN_EMAIL` | `RA_ADMIN_PASSWORD` |
| RA API | (no separate user) | `RA_API_PASSWORD` |
| RA Payment | `RA_PAYMENT_USER` | `RA_PAYMENT_PASSWORD` |
| RA Order | `RA_ORDER_USER` | `RA_ORDER_PASSWORD` |
| LX Hausys | `LXHAUSYS_ADMIN_EMAIL` | `LXHAUSYS_ADMIN_PASSWORD` |
| AK Admin | `AK_ADMIN_EMAIL` | `AK_ADMIN_PASSWORD` |
| Changemaker (Stan SA) | `CM_STAN_EMAIL` | `CM_STAN_PASSWORD` |
| Changemaker Admin | `CM_ADMIN_EMAIL` | `CM_ADMIN_PASSWORD` |

### URL-bearing env-vars (already wired in QA's `.env.example`)

| QA URL | Env-var name |
|---|---|
| `https://admin.adrqa.info` | `BASE_URL` |
| `https://ra.adrqa.info` | `RA_URL` |
| `https://fbm.adrqa.info` | `FBM_URL` |
| `https://chat.adrqa.info` | `CHAT_BASE_URL` |
| `https://adr-cards.adrqa.info` | `CARD_SITE_URL` |
| `https://vendor-api-service.adrqa.info` | `VENDOR_API_SERVICE_URL` |
| `https://galileo-fulfillment.adrqa.info` | `GALILEO_FULFILLMENT_URL` |
| `https://stan1212test.mydigitalrewards.com` | `HOST_URL` (configurable per-tenant) |
| `https://virtual-adr-account.adrqa.info` | `ACCOUNT_SITE_URL` |
| `https://atm-ezepulse-account.adrqa.info` | `EZEPULSE_ATM_URL` |
| `https://stanqa.redeem.adrqa.info/` | `CAMPAIGN_REDEEM_URL` |
| RA return webhook | `WEBHOOK_RETURN_URL` |

### Protocol when dev needs an env-var QA doesn't have yet

If dev's ticket needs a value QA's `.env.example` doesn't have (new service URL, test-data UUID, feature flag), DO NOT silently invent a plausible name. Follow this protocol:

1. **Name the env-var explicitly** in the ticket description — under a section called `New env-vars required by this ticket`. State: variable name (`SCREAMING_SNAKE_CASE`), purpose (one sentence), expected value format (e.g., `UUID v4`, `https://<host>`, bearer token), who populates it.
2. **Mark `[BLOCKED-DEV-CONFIRM]` in any TC step** that uses it if the value isn't supplied yet — never reference an undefined env-var as if it exists.
3. **State the QA action explicitly:** "QA SHALL add `<NAME>=` (empty) to `.env.example`, and populate the actual value in local `.env` based on dev-supplied value."
4. **Naming convention:** username/password pairs end `_USERNAME` / `_PASSWORD` (or `_USER` / `_PASSWORD` for RA flavors); URL-bearing vars end `_URL`; test-data identifiers prefix `QA_`; webhook receivers end `_WEBHOOK_URL` or `_RECEIVER_URL`.

---

## CI tag inventory (verified against `package.json`)

The real `--grep-invert` patterns from QA's `package.json`:

```
test:api:ci  --grep-invert="@reads-real-email|@known-defect|@needs-online-agent|@sends-real-email|@slow"
test:ui:ci   --grep-invert="@wcag|@reads-real-email|@known-defect|@needs-online-agent|@sends-real-email|@slow"
test:wcag:ci --grep="@wcag" --grep-invert="@known-defect|@needs-online-agent|@sends-real-email|@slow"
```

Default (non-CI) test runs only exclude `@sends-real-email` — so devs can run `@reads-real-email` / `@needs-online-agent` / `@slow` locally if they want, but real-inbox-sending tests require explicit opt-in.

| Tag | CI behavior | When to use |
|---|---|---|
| `@regression` | NO (default suite) | Most TCs — default tag |
| `@smoke` | NO | Critical-path subset, signals run-first; not CI-excluded but conventionally used |
| `@critical` | NO | End-to-end SSO + checkout flows; not CI-excluded but used in some specs |
| `@batch` | NO | Batch upload tests; longer runtime |
| `@wcag` | NOT in default; runs via `npm run test:wcag` | Accessibility tests |
| `@known-defect(DS-NNNNN)` | YES (excluded) | TC depends on an unfixed dev-side bug |
| `@reads-real-email` | YES (excluded) | TC verifies email content via Gmail API readback (rate-limited Workspace API) — replaces stale `@gmail` |
| `@needs-online-agent` | YES (excluded) | TC interacts with live chat (CSR availability + business hours) — replaces stale `@chat-hours-only` |
| `@sends-real-email` | YES (excluded from default + CI) | TC POSTs to a real human-monitored inbox (Zendesk / Pipedrive). Most dangerous — accumulates real tickets if run repeatedly. Replaces stale `@live-email` |
| `@slow` | YES (excluded) | Tests with runtime >5min |
| `@e2e` | NO | End-to-end multi-surface flows |
| `@idor` | NO | IDOR (cross-tenant data leak) negative tests |
| `@cors`, `@audit-log`, `@info-disclosure`, `@cleanup`, `@fix-verification` | NO | Domain-specific tags |
| `@<service>` (e.g., `@catalog`, `@campaign`, `@dashboard`, `@marketplace`, `@ak`, `@amazon-biz`, `@changemaker`) | NO | Service / feature-area scope tag |

**Stale tag names — do NOT use:**

| Stale tag | Replace with |
|---|---|
| `@gmail` | `@reads-real-email` |
| `@chat-hours-only` | `@needs-online-agent` |
| `@live-email` | `@sends-real-email` |

---

## Real QA artifacts every ticket SHOULD reference

Verified from the QA repo (`alldigitalrewards/rewardstack-qa-playwright`):

| Artifact | Path | Use in tickets |
|---|---|---|
| Centralized config | `import * as qa from '@config'` (193 usages); `import { TIMEOUT, DELAY, adminUrls } from '@config'` | All URLs / timeouts / delays — `qa.adminUrls.*`, `qa.TIMEOUT.*`, `qa.DELAY.*` |
| Credentials | `config/auth/credentials.ts` exports `superAdmin`, `admin`, `reporting`, `accounting`, `configuration`, `participantView`, `customerService`, `adminViewOnly`, `raApi`, `raPayment`, `raOrder`, `raAdmin` | TC steps cite the role name — never the literal credentials |
| Role matrix | `config/auth/role-matrix.ts` defines `RoleKey` + `Access` + `ModulePermissions` + `STORAGE_PATHS[roleKey]` | RBAC TCs reference `getRole(roleKey)` for credentials + `STORAGE_PATHS.<roleKey>` for storage state |
| Test-data tracking | `utils/ui/core/test-data-manager.ts` exports `class TestDataManager` with `track()` + `cleanup()` | Reference for cleanup mechanism (NEVER SQL DELETE) |
| Centralized locators | `utils/ui/locators/*.locators.ts` (20 files) | If a locator file exists for the service, reference its export; e.g., `import { CATALOG_LOCATORS } from '@utils/ui/locators/catalog.locators'` |
| Newman wrapper | `scripts/run-newman.mjs` with QA-URL verification | npm scripts: `test:newman` / `test:newman:rs` / `test:newman:ra` / `test:newman:sync` |
| Auth setup | `auth.setup.ts` + `npx playwright test --project=auth-setup` | Pre-authenticates each role; reuses `storageState` files |

---

## Process lessons (Stan's distilled rules — internalize these)

1. **HEAD probe alone is insufficient.** POST-only or auth-required endpoints reject HEAD and return 404/405, but work in production. Always cross-check candidate URLs against active spec usage in the consumer repo's `tests/`. If specs hit it successfully in CI, it works.
2. **Subdomain-pattern guessing fails for path-based services.** Catalog and api-batch-facilitator look "missing" by `<service>.adrqa.info` heuristic but live as routes on parent admin hosts. Before declaring a service "needs new infra," check if it's path-based.
3. **Repo with no `.github/workflows/` = no preview deploys.** A brand-new repo isn't QA-testable just because the code exists; it needs deploy automation first. Check the repo's `.github/` before assuming a PR preview will be available.
4. **Closed PRs don't deploy.** "Ready for QA" + closed PR = QA has nothing to test. Check PR `state` (not just `mergedAt`) before promoting tickets.
5. **Inventory drift is real.** Some URLs in `config/core/urls.ts` 404 today. Trust the live probe, not the config file alone.
6. **Don't trust memory or prior conversation for git/PR/deploy state.** Run `gh pr view <N> -R alldigitalrewards/<repo> --json isDraft,mergedAt,state,updatedAt` before claiming any PR's state in a ticket.
7. **Env-var names MUST come from real `.env.example`** — grep before naming. Never invent plausible-looking names like `BATCH_ADMIN_TOKEN`.
8. **CI tag names MUST come from real `package.json` grep-invert/grep-only patterns** — `@gmail`, `@chat-hours-only`, `@live-email` are stale and removed.

---

## Test data conventions (absolute)

- **Every generated value SHALL contain the substring `plrt`** so QA can `WHERE field LIKE '%plrt%'` and bulk-clean. Pattern: any value that lands in OUR DB must contain `plrt` (participant IDs, program IDs, session IDs, claim codes, etc.).
- **Every test email SHALL start with `suhrobu+`** and end with `@alldigitalrewards.com`. Real recipient routes through Workspace Gmail; arbitrary local-parts route to unknown recipients and break Gmail-readback tests.
- Forbidden: `@example.com`, `qa-superadmin@alldigitalrewards.com` (no `suhrobu+` prefix), `participant_random_123` (no `plrt` marker).
