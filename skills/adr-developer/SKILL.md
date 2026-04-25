---
name: adr-developer
description: Use when given a JIRA ticket key (like DS-12397, DS-11904) OR when the user needs to create a new JIRA ticket and implement code changes. Handles the full development lifecycle for AllDigitalRewards — reads/creates JIRA tickets, clones repos, writes code with TDD inside Docker, runs tests, creates PRs, and updates JIRA. Use this skill whenever the user mentions a ticket key, asks to implement a JIRA task, wants to fix a bug from QA, needs to create and implement a new ticket, or says anything like "work on DS-XXXXX", "pick up this ticket", "implement this feature", or "fix this bug in [ADR repo]".
---

# ADR Developer

Autonomous developer agent for AllDigitalRewards. Handles the full lifecycle: JIRA ticket to merged PR. Works with existing tickets or creates new ones.

## Ticket Intake

### Step 0 — Existing or New Ticket?

Ask: **"Do you have an existing JIRA ticket, or do we need to create one?"**

- **Existing ticket** → user provides key (e.g., `DS-12397`). Go to Step 1.
- **New ticket** → follow New Ticket Creation below, then Step 1.

### New Ticket Creation

1. **Ask: "What's the task?"** — understand what needs to change, which repo, acceptance criteria, priority
2. **Identify the JIRA project** — determine project key from repo/service. If unclear, list projects via Atlassian MCP and confirm.
3. **Fetch issue types** via `getJiraProjectIssueTypesMetadata`
4. **Fetch existing labels** — query recent tickets: `searchJiraIssuesUsingJql(jql: "project = <KEY> AND labels IS NOT EMPTY ORDER BY updated DESC", fields: ["labels"], maxResults: 50)`. Deduplicate into a flat list. **Never invent new labels** — only use existing ones. If none fit, leave labels empty.
5. **Create the ticket** via `createJiraIssue` with structured description (Overview, Specification, repo URL)
6. **Confirm** with user, then proceed to Step 1.

### Step 1 — Fetch and Assess

1. **Fetch ticket** via Atlassian MCP — summary, description, acceptance criteria, repo URL
2. **Read ALL comments** — QA reports, blockers, prior implementation notes. Skip for new tickets.
3. **Gather context** — linked tickets, parent epic, status history. "Returned from QA" is fundamentally different from "To Do."
4. **Find the repo** — parse GitHub URL from description. Fallback: `gh repo list alldigitalrewards --limit 100 | grep <service-name>`
5. **Check local repos first** — look in `~/Desktop/code/<repo-name>`. If it exists, `cd` into it and `git pull`. Only clone if absent. Never create duplicates with suffixes.
6. **Check for prior work** before creating a branch:
   ```bash
   git branch -r | grep <TICKET-KEY>
   gh pr list --search "<TICKET-KEY>" --state all
   ```
   - **Branch + no PR** → inspect commits, continue from there
   - **Branch + merged PR** → QA return. Read QA comments. Do NOT assume done.
   - **Branch + open PR** → read PR comments for reviewer feedback
   - **No branch** → fresh work
7. **Verify default branch name** — `git remote show origin | grep 'HEAD branch'`. Not all repos use `master`.

### Blockers

**Hard (stop and comment on JIRA):** repo doesn't exist/access denied, Docker environment won't start

**Soft (comment assumptions, continue):** unclear acceptance criteria, missing business logic context, conflicting information

## Development Workflow

### Setup

1. **Create branch** named after ticket key (e.g., `DS-12397`) off the default branch. **Never branch from an existing feature branch** — verify with `git log --oneline <default>..HEAD` that only your changes are present.
2. **Set up environment** — if `.env.example` exists, copy to `.env`. Check `docker-compose.yml` for `env_file` references. Env vars come in complete sets: TOKEN_ENDPOINT always needs TOKEN_USERNAME + TOKEN_PASSWORD alongside it.
3. **Discover the tech stack** — read `docker-compose.yml`, `composer.json`, `package.json`, `Makefile`
4. **Review the codebase** — read 2-3 existing files in each layer before writing new code. Match existing patterns.

### Planning

**Classify the ticket:**
- **Bug fix** — reproduce first. Trace the entire data flow from HTTP request through controller to service — not just the reported line. For async/queue systems, trace: data entry → storage → queue → consumer → failure point.
- **New feature** — map where it fits. Identify all layers needing changes (entities, services, controllers, routes, migrations, tests).
- **Infrastructure/config** — Docker, env vars, CI, queues. May have minimal test surface but must pass clean stack verification.
- **Upgrade** — invoke the corresponding skill: `/php85-fullstack-upgrade` or `/angular-latest-upgrade`. The skill's phases become the plan.
- **Multi-repo** — complete one repo fully first (TDD, clean stack, all checks). Only then replicate to remaining repos.

**Create an implementation plan**, then self-verify:
1. Review the plan in totality
2. Generate 3-5 verification questions that would expose errors
3. Answer each independently
4. Produce a revised plan

### Test-Driven Development (Mandatory)

- **RED** — write tests first, watch them fail
- **GREEN** — minimal code to pass
- **REFACTOR** — clean up, keep tests green

No exceptions for "simple" changes. If code is written before its test — delete it and start over.

**For dependency upgrades:** the SDK's own test suite is not a substitute. Write a test exercising the new API (RED with old dep), then bump the dep (GREEN). This catches integration issues SDK tests can't.

**Respect existing patterns:** if you can't test something without modifying the source's design pattern, change the test approach, not the source. Don't refactor production code just to make it testable — find a testing strategy that works with the existing design.

### Implementation

All commands run inside Docker — `docker compose exec` for everything. Don't overengineer: if a file permission error occurs, the fix might be `chmod`, not a rewrite. Understand WHY a pattern exists before replacing it.

**Error handling principle:** don't silently swallow failures. If catching exceptions at data boundaries, ask: "will the user know something is wrong?" If no, throw with a clear message. When null-coalescing, don't guess defaults — read the entity property, DB column default, and downstream consumers.

**MCP tools** — use freely: sequential thinking for debugging chains, Gemini for large-context analysis, Codex for second opinions.

### Testing

All must pass before pushing, in this order:

1. **Code style** — `vendor/bin/phpcs -p --standard=phpcs.xml src` (fix with `phpcbf`)
2. **Unit tests** — `vendor/bin/phpunit --testsuite unit`
3. **Newman tests** — `tests/newman/run.sh` (if repo has API endpoints)
4. **Container logs** — after EVERY test phase:
   ```bash
   docker compose logs --tail 50 <service-name> 2>&1 | grep -iE "error|fatal|exception|warning"
   ```
   A passing test suite with container log errors is NOT a passing build.

Fix any failures. Do not push with failures, warnings, notices, or deprecations.

### Newman/Postman Tests

For services with API endpoints, create a collection in `tests/newman/` with:
- Collection file organized by folder: Health Check, Validation, Happy path, Webhooks, Error handling
- Environment file with base_url and secrets
- Runner script detecting newman/npx

Use `galileo-fulfillment/tests/newman/` as the structural template.

### Clean Stack Verification (Mandatory Before PR)

Destroy and rebuild from scratch:

```bash
docker compose down -v
docker compose build --no-cache <service-name>
docker compose up -d
# configure git HTTPS + GitHub token inside container
# poll for MySQL: docker compose exec <service-name>-mysql mysqladmin ping --wait=30 -h localhost
```

Full checklist:
1. `docker compose ps` — all Up, 0 restarts
2. Logs — migrations clean, zero errors
3. `phpcs` — clean
4. `phpunit` — all pass, 0 failures/warnings/notices/deprecations
5. Newman — all assertions pass
6. `docker compose logs` — zero errors after all tests

### Pre-PR Checklist

```
- [ ] JIRA description updated (Overview, Specification, Risk Analysis, Testing Strategy with QA manual steps)
- [ ] Self-verification checkpoint completed
- [ ] `git diff` reviewed — ONLY intended changes, no test config, no docker-compose overrides
- [ ] All tests run INSIDE DOCKER: phpcs, phpunit, newman
- [ ] Container logs inspected — zero errors/exceptions/warnings
- [ ] Env vars complete (TOKEN_ENDPOINT + TOKEN_USERNAME + TOKEN_PASSWORD)
- [ ] composer.lock updated inside Docker (not on host, not with --ignore-platform-reqs)
- [ ] Branch clean from default — no unrelated changes
```

**Never modify `docker-compose.yml` on a PR branch for testing.** Use `docker compose run -e ...` overrides, `docker-compose.override.yml` (in `.gitignore`), or a local testing script.

## Delivery

### JIRA Ticket Description (Do This BEFORE Creating the PR)

The JIRA description is a core deliverable, not an afterthought. Update it with: Overview, Specification (acceptance criteria), Risk Analysis, and the **Testing Strategy v3 template** (canonical source: `~/Downloads/DS-12514-testing-strategy-v3-template.md`).

**Hard rule: PR creation is BLOCKED until the Section 10 Pre-QA Handoff Checklist is complete.** Every box must be checked. Testing Strategy is mandatory for every ticket — no exceptions for "small," "config-only," or "obvious" changes. If code changes, the ticket has a Testing Strategy.

**Non-negotiables from the v3 template:**
- Every TC starts at **Step 0** (zero-knowledge baseline — env, role, account, credentials, auth, prerequisites, mocked services, tags). Cross-references allowed but the slot is required per TC.
- Every step has all four labeled components: **Action** · **Expected Result** · **Wait Condition** · blank **Actual Result**. API uses `Request` / `Response` (equivalent labels). Linear block format — never tables for execution rows.
- **Newman collection mandatory** for any API change. Lives at `tests/newman/<service>/`. The Playwright QA framework reads Newman directly to author API tests; markdown Request/Response in the ticket must mirror the Newman request exactly.
- **QA verification surface is restricted:** browser DOM/URL/console/network + public HTTP API responses ONLY. No DB queries, no container shell, no log access. State changes that are DB-only must either get a public surface added in this PR (Section 5d Externally-Observable State Mapping) or move to per-TC Dev-Only Verification.
- **Visually-relevant UI steps include inline Expected + Actual screenshot slots** within the step block — never as generic ticket attachments. Skip screenshot slots on steps with purely textual assertions.
- **`data-testid` fallback rule:** if a UI element lacks an accessible name, dev MUST add `data-testid` in the same PR. Never ship a TC with `nth-child`, class-name, or XPath locators.
- **Tags per TC:** `@smoke` · `@regression` · `@slow` · `@flaky-quarantine`.
- **Accessibility scan default-on** for UI TCs (axe-core); zero violations except an explicitly tracked allowlist.
- **Ticket size capped at 5 TCs.** If you'd need 6+, split the ticket before writing the strategy.
- **Cold-read rule (final gate):** a stranger reading the ticket with zero prior context must be able to manually execute every TC top-to-bottom without asking a question. If they would need to ask, the TC is incomplete.

The description must reflect what was actually built for THIS ticket. Reference tickets are pattern guides, not specs — assess the actual repo independently.

### Push & PR

1. **Commit** with atomic, logical commits referencing the ticket key. One per logical unit of work. Never squash into a single monolith.
2. **Push the branch**
3. **Review your own diff** — `git diff --stat` and `git diff` before pushing. A 10-second review prevents hours of fix-ups.
4. **Create PR:**
   - **Title:** the exact JIRA ticket summary (not a custom title)
   - **Body:**
     ```
     ## Description
     [Summary of changes]

     ## Assumptions
     [List assumptions from unclear requirements. Remove if none.]

     ## Jira Issue Link
     https://alldigitalrewards.atlassian.net/browse/[TICKET-KEY]

     ## Has the README / documentation been extended if necessary?
     Yes/No

     ## Does this update require changes to public API documentation?
     Yes/No

     ## Tests
     ### Are there created tests which fail without the change (if possible)?
     Yes/No

     ### Have the changes been verified to comply with the security policy requirements?
     Yes
     ```
   - **Reviewers:** jhoughtelin and zwalden

### Post-PR: CI Monitoring

```bash
gh pr checks <PR-number> --watch --fail-fast
```
If CI fails: read logs (`gh run view <run-id> --log-failed`), fix inside Docker, push. Never disable CI checks.

### JIRA Update

1. **Transition to "Ready for QA"** — walk through all intermediate transitions (Backlog → Analysis → Selected for Development → Development in Progress → Ready for QA). Don't leave tickets in an intermediate state.
2. **Comment** with PR link
3. **Log time** — add worklog with approximate time spent

## QA Returns

When a ticket is returned from QA:

1. **Read ALL QA comments** — what failed, what was tested, expected behavior
2. **Read the merged PR diff** — `gh pr diff <PR-number>`
3. **Identify the regression** — same bug incompletely fixed, new bug from the fix, or pre-existing?
4. **Update JIRA description** to cover BOTH original AND QA-reported issues
5. **Branch from default** (not old feature branch) — `git checkout -b <TICKET-KEY>-v2`
6. **Write a failing test reproducing the QA issue** — must fail against current default branch
7. **Fix with TDD**, run full verification including original PR's tests
8. **Create a new PR** referencing both the original PR and QA feedback. Do not reopen the old PR.

## Failure Modes

- **Tests fail after repeated attempts** → comment on JIRA, create **draft PR** with work so far
- **Merge conflicts** → attempt rebase, if non-trivial comment on JIRA and note in PR
- **Docker won't build** → comment on JIRA with exact error, do NOT downgrade the target stack
- **Scope exceeds ticket** → stop, comment with scope assessment before continuing
- **Environment setup fails** → hard blocker, comment on JIRA

## Guardrails

Never: force push, push to default branch, modify CI/CD unless ticket asks, delete branches you didn't create, merge your own PR, write code before its test, execute runtimes on the host outside Docker, disable CI checks to make PRs pass.

## Reference Material

Consult `references/gotchas.md` when working with: Doctrine ORM 2→3, PHPUnit 9→12, Composer dependency conflicts, GitHub PAT scopes, PHP operator edge cases, or Xdebug + process isolation issues.
