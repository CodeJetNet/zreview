---
name: testing-strategy
description: Use when authoring or updating a Testing Strategy for AllDigitalRewards work. Provides Stan's DS-13017 standard (QA Independence Contract — minimum content so QA can verify a change independently without asking dev follow-up questions). Delivery mechanism is a markdown file at `tests/TESTING_STRATEGY.md` on the branch + uploaded as a JIRA attachment + a minimal pointer block in the ticket description (Before/After + testing type + ready-to-test signal + GitHub link). Required reading before transitioning a ticket to "Ready for QA" or creating a PR via adr-developer. Triggers on phrases like "testing strategy", "write test cases", "add TCs", "pre-QA handoff", "ready for QA", "QA strategy", "Playwright test cases", "Newman collection".
---

# Testing Strategy (DS-13017 standard)

Canonical Testing Strategy mechanism for AllDigitalRewards JIRA tickets. **Mandatory in every ticket — no exceptions for "small," "config-only," or "obvious" changes.** If code changes, the ticket has a Testing Strategy.

**Source of truth:** [DS-13017 — Testing Strategy Developer Reference — QA Independence Contract](https://alldigitalrewards.atlassian.net/browse/DS-13017). Owner: Suhrob (Stan) Ulmasov. Stan edits DS-13017's description in place; treat that ticket as the living spec and re-read it if anything in this skill seems out-of-date.

## Delivery mechanism (this is NEW — read carefully)

The TS lives in three places simultaneously, with one source of truth:

1. **Source of truth: `tests/TESTING_STRATEGY.md` on the feature branch.** Versioned with the code, diff-able, atomic commits, no Atlassian WAF cap, reviewable in the PR. Updates land via commits on the branch.
2. **JIRA attachment.** The same file is uploaded to the ticket via JIRA's REST API so QA can read it without repo access. Re-upload on every TS revision (JIRA preserves attachment history; QA always sees the most recent at the top of the attachments panel).
3. **JIRA description: minimal pointer block.** Description carries only Before/After + testing type + ready-to-test signal + GitHub link to the file on the branch. The full TS does NOT live in the description. This keeps the description scannable in 30 seconds and avoids the v7-era WAF / append-update mess.

### The description pointer block (paste this verbatim under `## Testing Strategy`)

```markdown
## Testing Strategy

**Canonical TS:** [`tests/TESTING_STRATEGY.md`](https://github.com/alldigitalrewards/<repo>/blob/<branch>/tests/TESTING_STRATEGY.md) — also attached to this ticket (latest version at the top of the attachments panel)

**Testing type:** API · UI · API+UI-split (under epic DS-XXXXX)

**Before:** _one user-visible sentence — what they see today_
**After:** _one user-visible sentence — what they see after this change_

**Ready-to-test signal:** all PRs merged (links in Prerequisites) + latest deploy's smoke run green ([link])
```

### Uploading the attachment to JIRA

Atlassian MCP does not expose an attachment-upload tool; `jira` CLI v1.7.0 does not support `attach`. Use `curl` directly:

```bash
curl -s -u "<your-atlassian-email>:$JIRA_API_TOKEN" \
  -X POST -H "X-Atlassian-Token: no-check" \
  -F "file=@tests/TESTING_STRATEGY.md" \
  https://alldigitalrewards.atlassian.net/rest/api/3/issue/<TICKET-KEY>/attachments
```

`JIRA_API_TOKEN` must be set in the environment. Re-upload on every TS revision; do NOT delete prior attachment versions — JIRA preserves them as audit trail.

## The DS-13017 contract — what every TS SHALL include

The dev writes the Testing Strategy in `tests/TESTING_STRATEGY.md` by:

1. **Picking ONE testing type — API OR UI.** Cannot be both. PR spanning both surfaces = two cross-linked tickets under one Epic. WCAG audits fold into UI tickets.
2. Filling the **Prerequisites tables** (Universal + API or UI — see `references/template.md`).
3. Writing test cases using the **Step 0 + Request/Action templates**.
4. Ticking every **Pre-QA Handoff Checklist** box before marking the ticket **Ready for QA**.

**Any drafting tool is fine — the dev owns the final content.** Whoever drafts must follow the Drafting Rules below.

### Drafting Rules (absolute)

1. **Cite source** (PR diff file + line) for every endpoint, payload field, response shape, selector, page label. Never fabricate.
2. **Never publish a TS containing placeholders or invented values.** Literal strings like `<sandbox-name>`, `<flag-name>`, `<role-tbd>`, `<paste-curl-here>` mean the drafter (human or AI) didn't have the real answer — **find it before marking the ticket Ready for QA**. Sources: the PR diff, the dev who wrote the PR, the PM, the smoke deploy.
3. **Output format = the Prerequisites tables + Step 0 + Request/Action templates from `references/template.md`, verbatim.**

## Coverage rule

**ONE happy E2E + minimum negatives for THIS change.**

- Minimum negative categories: invalid input · wrong role (authorization) · empty/null. API tickets also: boundary values.
- API negatives = 4xx response with documented error body. UI negatives = error toast, inline validation message, blocked-submit, or redirect.
- Only negatives this ticket's PR(s) introduce — pre-existing failure paths belong on a separate ticket.
- **Typical TS: 2-5 test cases. If more than 5, the change is too big — split into 2 tickets.**
- For **multi-tenant or org-scoped** endpoints, also include a **cross-tenant negative** (admin of org A → 403/404 on org B's resource), unless the endpoint is explicitly global. Multi-tenant = URL path or JWT carries an organization scope (e.g., `/programs/{program_id}/…`, `/orgs/{org_id}/…`, or the token's `org_id` claim filters results).

## QA reality — facts the drafter must respect

- **QA verifies through:** HTTP API · browser UI · email inbox · Jira · GitHub. **No DB, queues, Docker, Kubernetes / GKE / Cloud Run consoles, internal logs, APM tools (Datadog / Sentry / Grafana), production environment.** QA cannot delete in QA env, so seed data uses unique-per-run naming.
- The TS lives in `tests/TESTING_STRATEGY.md` (branch + JIRA attachment) — **not** in JIRA description body, **not** in JIRA comments. QA's verdicts and reruns go in Jira comments; never edited into the TS.
- **No severity or priority classifications inside the TS** — those are Jira ticket fields, not TS content.
- On every UI ticket, QA verifies the page renders correctly at the **full standard width set (320, 375, 390, 414, 768, 1024, 1280, 1920 px)** — layout, text, content, images. Default coverage; no per-ticket declaration. If layout is intentionally locked to one width, dev flags it in `Risk notes`.

## Auth — name the specific role (never invent env-var names)

DS-13017's canonical role list lives in QA's `config/auth/roles.ts`. The 8 are:

```
superAdmin · admin · accounting · reporting · configuration · customerService · participantView · programAdmin
```

Generic terms ("admin-scoped", "any admin") don't count — name the specific role. If a new role is needed, extend QA's matrix first (in a separate ticket / PR).

The Step 0 line is: `Authenticate as <role> (QA supplies account + creds from its secure store)`. Never paste accountIds, usernames, passwords, or tokens in the TS — QA pulls from its secure store keyed by role name.

For tenant-specific roles outside the canonical 8 (LX Hausys admin, RA Payment service account, Changemaker SA), cite the tenant + role plainly. Don't invent env-var names; QA wires these too.

## Test data conventions

- **Synthetic data only.** Examples: `suhrobu+<spec>@alldigitalrewards.com`, `+1 (555) 010-0100`, `"Test User"`. No real customer/cardholder data.
- **Email recipient must be `suhrobu+<spec>@alldigitalrewards.com`.** Plus-addressing routes to Stan's Workspace inbox so Gmail readback works.
- **Unique-per-run entity naming.** Pattern: `qa_<spec>_<timestamp>` (e.g., `qa_otp_smoke_20260525_103000`). QA cannot delete in QA env; uniqueness prevents collision across runs.
- **No real user PII** in payload examples — synthetic test values throughout.

## When to use this skill

- Authoring a new JIRA ticket's Testing Strategy file
- Updating an existing ticket's TS before a PR or after a QA bounce
- Before transitioning a ticket to "Ready for QA"
- Before creating a PR via `adr-developer` — PR creation is BLOCKED until the Pre-QA Handoff Checklist in `tests/TESTING_STRATEGY.md` is complete and the file is uploaded to JIRA

## How to use it

1. **Read `references/template.md`** — copy it verbatim into a new `tests/TESTING_STRATEGY.md` on the branch.
2. **Pick ONE testing type** — API or UI. If the PR genuinely spans both, stop and split the ticket before writing.
3. **Look up factual references** in `references/qa-environment-inventory.md` — QA URLs, the 8 roles list, webhook.site usage, pre-existing test participants (`stan12121212` PROTECTED). Never invent.
4. **Fill out Prerequisites tables** (Universal + API-or-UI subset) top-to-bottom. No placeholders; if you don't know a value, find it (PR diff / dev / PM / smoke deploy) before continuing.
5. **Run the recipes you write.** For API tickets: paste a literal `curl` + observed 2xx for each method+path under test, citing the PR HEAD SHA tested (e.g., `verified at HEAD <commit-sha>`). For UI tickets: walk the recipe in a browser and paste a post-login screenshot showing the role badge. Self-attestation without pasted proof is not acceptable.
6. **Write test cases** — Step 0 (Preconditions) + Step N (Request/Action + Expected + Actual). 2-5 TCs total.
7. **Tick every box** in the Pre-QA Handoff Checklist (Universal + API-only or UI-only subset). Boxes stay `[ ]` until the underlying condition is met; never pre-check decoratively.
8. **Cold-read gate:** a stranger reading the TS with zero prior context must be able to manually execute every TC top-to-bottom without asking a question. If they would need to ask, fix the gap.
9. **Commit the TS file** with the implementation diff on the branch.
10. **Upload as JIRA attachment** via the `curl` command above. Re-upload on every revision.
11. **Add the description pointer block** to the JIRA ticket (replaces any prior TS content in the description; never leave stale TS content there).
12. **Only then transition the ticket to "Ready for QA".**

## What this skill does NOT carry (compared to the prior v7 standard)

DS-13017 is a clean break from the v7 standard the skill used to encode. The following v7 elements are intentionally not present — Stan removed them or never required them:

- No section numbering (no §0 / §1a / §4a / §11). Output format is just Prerequisites tables + Step 0 + Step N + checklist.
- No 35-row test-angles matrix.
- No mandatory 8-role RBAC matrix per ticket (only a cross-tenant negative for multi-tenant endpoints).
- No Severity (BLOCKER/HIGH/MEDIUM/LOW/INFO) or Priority (P1/P2/P3) fields inside the TS — those are Jira ticket fields.
- No CI tag taxonomy (`@smoke` / `@reads-real-email` / `@needs-online-agent` / `@sends-real-email` / etc.) inside the TS.
- No `plrt` test-data marker (replaced by `qa_<spec>_<timestamp>` for entities + `suhrobu+<spec>@alldigitalrewards.com` for emails).
- No 40-Ask Contract, 7 Anchor Rules, Adversarial Pre-Mortem, 9-convention Style Linter, or 35-item Pre-Submit Self-Check.
- No Per-TC browser/device matrix (default coverage across the full standard width set is assumed).
- No mixed UI+API TSes (split into two cross-linked tickets under one Epic).
- No TS-in-comments fallback (TS is an attached file; the description carries the pointer).

If you find yourself reaching for one of these, you're writing v7 — stop and re-read DS-13017.

## Reference files

- **`references/template.md`** — the DS-13017 verbatim template. Copy into `tests/TESTING_STRATEGY.md` on the branch and fill out.
- **`references/qa-environment-inventory.md`** — factual lookup data DS-13017 references but doesn't restate: verified QA URLs (allowlist / denylist), webhook.site usage, pre-existing test participants (`stan12121212` PROTECTED), date/time/locale/currency conventions.

## Why this exists

DS-13017's goal is **QA independence**: QA verifies the change without asking dev a single follow-up question. Every section of the template exists because its absence forced QA to interrupt a dev (mid-flow, mid-meeting, mid-sleep) to get a missing endpoint / payload / role / flag / seed step / verification curl. The contract is a one-time cost at write-time that buys QA a week of unblocked verification.

The branch-attached + JIRA-attached + description-pointer delivery mechanism solves the operational pain v7 ran into: TS too large to fit in JIRA's editor, append-only updates that get lost in comment threads, no diff history when content changes. Markdown in git solves all three.
