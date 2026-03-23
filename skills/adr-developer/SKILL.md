---
name: adr-developer
description: Use when given a JIRA ticket key to autonomously implement — reads ticket, clones repo, writes code with TDD, runs tests in Docker, creates PR, and updates JIRA
---

# ADR Developer

## Overview

Autonomous developer agent for AllDigitalRewards. Given a JIRA ticket key, it handles the full lifecycle: read the ticket, clone the repo, implement with TDD, test in Docker, push, open a PR, and update JIRA.

## Ticket Intake

1. **Fetch the JIRA ticket** via Atlassian MCP tools — read summary, description, acceptance criteria, and linked repo URL
2. **Parse the repo URL** from the description (GitHub link under `https://github.com/alldigitalrewards`)
3. **Clone or pull** the repo
4. **Assess clarity** — determine if the ticket has enough context to begin

### Hard Blockers — Stop and Comment on JIRA

- No repo link in the ticket description
- Repo doesn't exist or access is denied
- Docker environment won't start / no `docker-compose.yml` found

### Soft Blockers — Comment Assumptions, Continue Working

- Unclear or ambiguous acceptance criteria
- Missing context on business logic
- Conflicting information in the ticket

Comment on the JIRA ticket listing specific questions or gaps. Note assumptions clearly. Proceed with best judgment. Document all assumptions in the PR description.

## Development Workflow

### Setup

1. **Create branch** named after the ticket key (e.g., `DS-12397`) off the repo's default branch
2. **Set up environment** — if `.env.example` exists, copy to `.env` and populate required values. The Docker stack often won't start without this. Check `docker-compose.yml` for `env_file` references or `${VAR}` interpolation to identify what's needed.
3. **Discover the tech stack** — read `docker-compose.yml`, `composer.json`, `package.json`, `Makefile`, etc.
3. **Review the codebase** — understand architecture, patterns, conventions, and relevant existing code
4. **Classify the ticket and plan investigation approach** — determine what kind of work this is, then follow the corresponding approach:
   - **Bug fix** — Reproduce the issue first. Understand the current (broken) behavior by reading the relevant code path, checking logs, and identifying the root cause. Do not jump to a fix. Enter TDD with a failing test that captures the bug before writing any fix.
   - **New feature** — Map where the feature fits in the existing architecture. Identify all layers that need changes (entities, services, controllers, routes, migrations, tests). Enter TDD with a test that defines the feature's expected contract.
   - **Infrastructure/config** — For tickets involving Docker, environment variables, CI, queue setup, or similar. These may have minimal unit test surface but must be verified through clean stack verification and Newman tests where applicable.
   - **Upgrade** — If the ticket is a stack upgrade, invoke the corresponding skill to guide the implementation plan:
     - PHP 8.5 / full stack upgrade / Slim 4 / Doctrine ORM 3 / PHPUnit 12 → invoke `/php85-fullstack-upgrade`
     - Angular upgrade / Angular migration → invoke `/angular-latest-upgrade`
     - When an upgrade skill applies, its phases and verification gates become the implementation plan — do not create a separate plan that ignores the skill's structure
   - **Multi-repo** — When a ticket touches multiple repositories with the same change pattern, do one repo thoroughly first (full TDD, clean stack verification, all checks). Only after that first repo is verified clean, replicate across the remaining repos. Do NOT blast changes across all repos simultaneously.
5. **Create an implementation plan** — tailored to the ticket type. For upgrades, follow the invoked skill's phase structure. For bugs, the plan centers on root cause and reproduction. For features, the plan maps the changes across architectural layers.

### Self-Verification Checkpoint (Mandatory Before Writing Code)

Before writing any code, you MUST:

1. Review the plan in totality
2. Generate 3-5 verification questions that would expose errors in the plan
3. Answer each verification question independently
4. Produce a final revised plan based on the verification

### Test-Driven Development (Mandatory)

All implementation follows strict TDD:

- **RED** — Write tests first. Watch them fail.
- **GREEN** — Write minimal code to make tests pass.
- **REFACTOR** — Clean up while keeping tests green.

If code is written before its test — delete it and start over. No exceptions for "simple" changes.

**For dependency version bumps / SDK upgrades:** The SDK's own test suite is NOT a substitute for TDD. Write a test in the consuming repo that exercises the new constructor/API first (RED with old SDK), then bump the dependency (GREEN). This catches integration issues the SDK tests can't — like factory singleton patterns, env var requirements, and constructor signature mismatches.

### Implementation

- **All commands run inside Docker containers** — never execute runtimes on the host machine
- Use `docker compose exec` or `docker exec` for everything: tests, builds, migrations, composer/npm installs
- Only run builds when explicitly needed — not after every small change

### Code Standards

- Clean, DRY, SRP-compliant code
- Methods: 25-30 lines max
- Early returns over nested conditionals
- Reduce cyclomatic complexity — no noise or bloat
- Readable naming over clever naming
- No `doThisAndThat()` — if naming suggests multiple responsibilities, split it
- Defensive at boundaries (user input, external APIs) — not for internal invariants
- No redundant null checks for values that must exist
- Inheritance for IS-A, composition for HAS-A
- Domain-driven design when applicable: clear bounded contexts, public APIs between domains
- Explicit over implicit — no magic numbers/strings
- Always `use` import classes — never use fully qualified `\ClassName` inline (e.g., `use Throwable;` then `catch (Throwable $e)`, not `catch (\Throwable $e)`)
- Latest PHP language constructs (PHP 8.5+: readonly classes, enums, fibers, named arguments, match expressions, etc.)
- Unix line endings (LF)
- OWASP security practices
- Testable code

### MCP Tools

Use these freely and heavily during implementation. They are paid for — use them liberally when they add value.

- **Sequential thinking** — multi-step reasoning where order matters: debugging chains, architectural decisions with tradeoffs, dependency conflict resolution
- **Gemini** — large context analysis and cross-referencing: reviewing multiple files simultaneously, understanding complex inheritance chains, analyzing migration impacts across a codebase. Always use the latest/most powerful model available.
- **Codex** — second opinion on implementation approach: validating architectural choices, sanity-checking unfamiliar patterns, confirming edge case handling

### Testing

All of the following MUST pass before pushing. Run them in this order:

1. **Code style** — `vendor/bin/phpcs -p --standard=phpcs.xml src` (fix with `phpcbf` if needed)
2. **Unit tests** — `vendor/bin/phpunit --testsuite unit` (with baseline if external deprecations exist)
3. **Newman tests** — `tests/newman/run.sh` (if the repo has API endpoints)
4. **Container logs** — check for errors after each test phase (see Container Log Inspection below)

If any step fails, fix and re-run. Do not push with failures, warnings, notices, or deprecations. External package deprecations must be baselined, not ignored.

### Newman/Postman Regression Tests

For services with API endpoints, create a Newman test collection in `tests/newman/` with:

1. **Collection file** (`<service-name>.postman_collection.json`) — organized by folder:
   - Health Check
   - Validation (missing/invalid inputs)
   - Happy path (CRUD operations)
   - Webhooks/callbacks (if applicable)
   - Error handling (404, 405)
2. **Environment file** (`<service-name>.postman_environment.json`) — base_url, secrets
3. **Runner script** (`run.sh`) — detects newman/npx, passes env vars, runs collection

Use the reference implementation at `galileo-fulfillment/tests/newman/` as the structural template.

Run Newman tests after Docker stack is up and migrations are complete. All assertions must pass before pushing.

### Container Log Inspection (Mandatory)

After running any tests (unit tests, Newman tests, smoke tests), **always inspect container logs for errors**:

```bash
docker compose logs --tail 50 <service-name> 2>&1 | grep -iE "error|fatal|exception|warning"
```

- This catches runtime errors that tests may not surface (e.g., unhandled exceptions that return 200/500 but log errors)
- If errors are found, investigate and fix before pushing
- A passing test suite with container log errors is NOT a passing build
- Run this check after every test phase, not just at the end

### Clean Stack Verification (Mandatory Before PR)

Before creating a PR, destroy and rebuild the entire stack from scratch to prove it works on a fresh environment:

```bash
docker compose down -v                                    # destroy containers, volumes, DB
docker compose build --no-cache <service-name>            # rebuild image
docker compose up -d                                      # start fresh
# install composer inside container, configure git HTTPS + GitHub token
# wait for MySQL to be ready — poll, don't sleep:
# docker compose exec <service-name>-mysql mysqladmin ping --wait=30 -h localhost
# then restart container to trigger migrations if needed
```

Then run the full verification checklist:
1. `docker compose ps` — all containers Up, 0 restarts
2. Check logs — migrations executed successfully, zero errors
3. `phpcs` — code style clean
4. `phpunit` — all tests pass, 0 failures/warnings/notices/deprecations
5. Newman — all API assertions pass
6. `docker compose logs` — zero errors/exceptions/warnings after all tests

If any step fails, the build is not ready. Fix and re-verify. This proves the branch works from a completely clean state — no leftover data, no stale cache, no manual setup.

### Pre-PR Checklist (Mandatory — Do Not Skip)

Before creating ANY pull request, verify every item. If you skip one, you will be caught.

```
- [ ] JIRA description updated (Overview, Specification, Risk Analysis, Testing Strategy with QA manual steps)
- [ ] Self-verification checkpoint completed (3-5 questions answered)
- [ ] `git diff` reviewed — ONLY intended changes present, no test config, no docker-compose overrides
- [ ] All tests run INSIDE DOCKER: phpcs, phpunit, newman (if applicable)
- [ ] Container logs inspected after tests — zero errors/exceptions/warnings
- [ ] Env vars complete — TOKEN_ENDPOINT always paired with TOKEN_USERNAME + TOKEN_PASSWORD
- [ ] composer.lock updated inside Docker (not on host, not with --ignore-platform-reqs)
- [ ] Branch is clean from master/main — no unrelated changes from other branches
```

### Docker-Compose Testing Isolation

**Never modify `docker-compose.yml` on a PR branch for local testing.** Port changes, TOKEN_ENDPOINT overrides, network `external: true` — none of this belongs in a PR. Use one of these instead:

- `docker compose run -e TOKEN_ENDPOINT=http://admin/token -e RA_ENDPOINT=http://ra/api/` (env overrides)
- `docker-compose.override.yml` (must be in `.gitignore`)
- A separate local testing script

If you commit docker-compose test config to a PR branch, you will pollute the diff and the PR will be rejected.

## Delivery

### Push & PR

1. **Commit** with atomic, logical commits referencing the ticket key:
   - One commit per logical unit of work (e.g., `DS-12397: add order entity and migration`, `DS-12397: add order service with validation`, `DS-12397: add API endpoint for order creation`)
   - For upgrade tickets following a phased skill, one commit per phase
   - Never squash everything into a single monolith commit — reviewers need to follow the progression
2. **Push the branch** to the repo
3. **Create a Pull Request:**
   - **Title:** The JIRA ticket title
   - **Body:** Use this exact template:
     ```
     ## Description
     [Summary of changes]

     ## Assumptions
     [List any assumptions made due to unclear requirements. Remove this section if none.]

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
   - **Reviewers:** Assign jhoughtelin and zwalden as reviewers

### JIRA Update

1. Transition the ticket to **"Code Review"** (or nearest available status)
2. Add a comment with a link to the PR
3. **Log time** — add a worklog to the ticket with the approximate time spent on implementation

## Implementation Failures

- **Tests fail after repeated attempts** → comment on JIRA with what was tried, create a **draft PR** with the work so far, note the failures
- **Merge conflicts with default branch** → attempt to rebase. If conflicts are non-trivial, comment on JIRA and create the PR as-is noting the conflicts
- **Docker won't build** (e.g., PECL extension fails, base image unavailable) → comment on JIRA with the exact error, do NOT attempt workarounds that compromise the target stack (e.g., downgrading PHP to make it build)
- **Scope exceeds the ticket** → if implementation reveals the ticket requires significantly more work than described (e.g., ticket says "update endpoint" but the endpoint's entire dependency chain needs rewriting), stop and comment on JIRA with a scope assessment before continuing. Do not silently expand scope.
- **Environment setup fails** → if `.env.example` is missing, required services aren't in `docker-compose.yml`, or the repo has no working Docker setup, treat as a hard blocker. Comment on JIRA with what's missing.

## Things the Agent Must Never Do

- Force push
- Push to the default branch directly
- Modify CI/CD pipelines unless the ticket explicitly asks for it
- Delete branches it didn't create
- Merge its own PR
- Write implementation code before its test
- Execute runtimes on the host machine outside Docker

## Lessons Learned — Read This Before Every Task

These are hard-won lessons from real implementations. Do not skip or rationalize around them.

### Read the skill fully before starting

Every section of this skill exists because something was missed. Read every word. Read the Global Memory section. Read the Delivery section. If you think "I already know this" — read it again. Skipping sections leads to rework.

### The JIRA ticket IS part of the deliverable

Updating the JIRA ticket description (Overview, Specification, Risk Analysis, Testing Strategy) is not an afterthought — it's a core delivery step. Do it BEFORE or AT THE SAME TIME as creating PRs. The ticket description must reflect what was actually built for THIS ticket, not copy-pasted reference content from another ticket.

### Reference tickets are guides, not specs

When a ticket says "cloned from" or references another ticket's spec, that content is a pattern guide — not the actual spec. Assess the actual repo independently. Different repos have different states (e.g., one may already be on Slim 4 while the reference migrated from Slim 3).

### Doctrine ORM 2→3 / DBAL 3→4 gotchas

- `Doctrine\DBAL\Exception` became an **interface** in DBAL 4 — you cannot `throw new Exception()` with it. Use `RuntimeException` or `\Exception` for throw targets.
- `Doctrine\ORM\Exception\NotSupported` is deprecated in ORM 3 — remove all imports and `@throws` docblocks. Catch `\Throwable` at boundaries instead.
- `Doctrine\Common\Proxy\AbstractProxyFactory` moved to `Doctrine\ORM\Proxy\ProxyFactory`.
- Entity ORM attributes must use **named parameters** in ORM 3 (positional was silently allowed in ORM 2). E.g., `#[ORM\ManyToOne("Batches")]` must become `#[ORM\ManyToOne(targetEntity: Batches::class)]`.
- `symfony/var-exporter` v8 removed `LazyGhostTrait` — pin to `^7.2` for Doctrine ORM 3 until Doctrine supports PHP 8.4 native lazy objects natively.

### PHPUnit 9→12 gotchas

- `->willReturn(null)` on void methods is now an error — remove the `willReturn(null)` call entirely.
- `$this->onConsecutiveCalls()` is removed — use `willReturnCallback()` with a counter and `match` expression.
- `createMock()` without `expects()` triggers notices — use `createStub()` for dependencies that aren't being verified.
- Remove all deprecated xml attributes from phpunit.xml: `backupGlobals`, `backupStaticAttributes`, `convertErrorsToExceptions`, `convertNoticesToExceptions`, `convertWarningsToExceptions`, etc.
- `<coverage>` element replaced by `<source>`. Coverage reports moved to CLI flags.

### Composer VCS repos + Docker = SSH won't work

Docker containers don't have SSH keys. When running `composer install/update` inside containers with VCS repos that use `git@github.com:`, configure git to redirect SSH to HTTPS:
```bash
git config --global url."https://github.com/".insteadOf "git@github.com:"
composer config --global github-oauth.github.com $TOKEN
```

### GitHub PAT scopes matter

- Pushing changes to `.github/workflows/` requires the `workflow` scope on the PAT
- `gh auth login` requires `read:org` scope
- If push fails with "refusing to allow a Personal Access Token", check scope — don't retry blindly
- The token is in `~/.zshrc` as `GITHUB_PERSONAL_ACCESS_TOKEN` — source it with `source ~/.zshrc`

### Dependency conflicts are puzzles, not blockers

When `composer update` fails with conflicts, read the full error. Common patterns:
- Package A needs library v5, Package B needs library v6 → check if Package A is actually used. If unused, remove it.
- Security advisory blocking install → add audit ignore config in composer.json.
- Lock file stale → use `composer update`, not `composer install`.

### Container logs are test output too

A test suite can pass 100% while the application logs fatal errors (e.g., catching exceptions and returning 200). Always check `docker compose logs` after running tests. Container errors that don't surface as test failures are still bugs.

### Check the default branch name

Not all repos use `master`. Many use `main`. Always verify with the GitHub API or `git remote show origin` before creating PRs.

### Don't skip JIRA ticket description updates

Updating the JIRA ticket description (Overview, Specification, Risk Analysis, Testing Strategy) is a **blocking delivery step** — do it BEFORE or AT THE SAME TIME as creating the PR. Do not create a PR and then forget to update the ticket description. If a ticket is sent back from QA with a second bug, update the description to cover BOTH bugs, not just the original. The ticket description must always reflect the full scope of what was actually fixed.

### Existing work on a branch doesn't mean the ticket is done

When you find an existing branch with commits and a merged PR, do NOT assume the ticket is complete. The ticket may have been sent back from QA because the fix was insufficient or a second bug was discovered. Always ask the user for context about the current state before declaring work done or transitioning the ticket.

### Investigate the full code path, not just the reported line

The original bug report pointed to line 67 of `DashboardService.php`. The first fix addressed that line but missed a second bug in the controller (`Get.php:32`) where `json_decode()` returning `null` was passed to a method expecting `array`. When fixing a bug, trace the entire data flow — from the HTTP request through the controller to the service — not just the single line mentioned in the error log. Boundary code (controllers, API handlers) is where null/invalid data enters the system and is often where the real fix belongs.

### No repo URL is a soft blocker, not a hard blocker

If the JIRA ticket description doesn't contain a GitHub repo URL, search the AllDigitalRewards org on GitHub using `gh repo list` before treating it as a hard blocker. Most repos can be found by name (e.g., "dashboard-service" for a Dashboard Service ticket).

### Read JIRA ticket comments before starting work

When picking up a ticket, read ALL comments — not just the description. QA verification reports, blockers, and callouts live in comments. On DS-12396, two detailed QA reports from Stan (pre-merge and post-merge) were in the comments with critical information about blocked tests, security observations, and the KD-008 write-crash defect. Failing to read comments wastes time investigating things QA has already documented.

### PR title must be the JIRA ticket title

The skill says `Title: The JIRA ticket title`. Use the exact ticket summary as the PR title — don't write a custom title. For DS-12396, the title should be "Image service Production error", not "DS-12396: Fix silent 500 on image write operations".

### Don't change existing code patterns to make tests work

When existing code uses a pattern (like `!isset()` on typed static properties), understand WHY before changing it. On DS-12411, `private static TransactionFetcher $transactionFetcher;` with `!isset()` was the intentional pattern — uninitialized typed properties return false for `isset()`. The agent changed it to `?TransactionFetcher $transactionFetcher = null` to make tests work, then to `unset()`, then back again — three rounds of fixes. If you can't test something without modifying the source's design pattern, change the test approach, not the source.

### Review your own diff before pushing

Run `git diff --stat` and `git diff` before every push. On DS-12411, this would have caught: galileo's 51-file pollution from a dirty branch, missing TOKEN_USERNAME/TOKEN_PASSWORD in RewardStack's docker-compose, and accidentally deleted test files. A 10-second diff review prevents hours of fix-up commits.

### Env vars come in complete sets

TOKEN_ENDPOINT, TOKEN_USERNAME, and TOKEN_PASSWORD are always a set of three. When adding or replacing env vars, always add all three together. On DS-12410, TOKEN_ENDPOINT was added without TOKEN_USERNAME/TOKEN_PASSWORD next to it — the user caught this. Never add one without the others.

### Don't disable CI checks to make PRs pass

When Newman tests or other CI checks fail, investigate and fix the root cause. On DS-12411, Newman tests were changed from `pull_request` trigger to `workflow_dispatch` instead of being fixed. Disabling checks is hiding problems, not solving them. If the test genuinely can't run in CI (e.g., needs external services), document why in a comment and get approval before disabling.

### Multi-repo tickets: one clean repo first, then replicate

On DS-12411 (12 repos), changes were blasted across all repos simultaneously. This led to: factory test failures across all repos, composer.lock issues in all repos, and docker-compose pollution in galileo. The correct approach: pick one repo, implement fully with TDD, clean stack verification, all checks passing. Only then replicate the proven pattern to the remaining repos.

### Always branch from the default branch, never from an existing feature branch

On DS-12411, the galileo-fulfillment branch was created from a branch that already had 50+ unrelated changes. This polluted the PR with changes from other tickets. Always verify with `git log --oneline master..HEAD` that only your changes are present before pushing.

## Global Memory

These memories persist across conversations and inform agent behavior:

- **GitHub access:** GitHub token for private repos is in zshrc as `GITHUB_PERSONAL_ACCESS_TOKEN`
- **JIRA ticket quality:** Always fill Overview, Specification, Risk Analysis, Testing Strategy sections on JIRA tickets
- **Plan execution:** Always choose subagent-driven development for plan execution

