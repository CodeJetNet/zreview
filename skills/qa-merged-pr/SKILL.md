---
name: qa-merged-pr
description: Use when asked to QA a merged PR or a ticket that is in QA ("review merged PR", "QA DS-XXXXX", "test this on QA", "verify on QA"), or to set up QA testing ("set up qa-merged-pr", "set up the QA suite"), with the rewardstack-qa-playwright suite. Checks the machine is set up and walks the user through setup if not. Reads the ticket and its Testing Strategy, reuses or writes that ticket's Playwright spec, runs it against QA in Docker only after the user says yes or go, and reports PASS / FAIL / BLOCKED per test case.
---

# QA a merged PR

The QA suite is `alldigitalrewards/rewardstack-qa-playwright` (Playwright + TypeScript). Every ticket QA has tested has a spec named `ds<number>_*.spec.ts` under `tests/ui/<area>/` or `tests/api/<area>/`. This skill runs that suite the way QA does, with QA's safety rules, so any developer can verify a merged PR.

The scripts named below live in this skill's `scripts/` folder. Run them with `bash <skill base directory>/scripts/<name>.sh`.

## The gate (never skip)

- Nothing touches QA until the user answers **yes** or **go** to the run plan for this PR. A go covers that PR only. Stage is not supported yet.
- QA locks the caller's IP after 5 failed sign-ins, and that locks the whole team out. Never retry a refused login, never guess a password, never run with retries, `--repeat-each` or extra workers. The runner signs each admin role in once before anything else and stops at the first refusal.
- Never production. The preflight only accepts `*.adrqa.info`, and the suite's global setup aborts on any non-QA host.
- Test emails go only to addresses the `.env` already holds or to `suhrobu+<anything>@alldigitalrewards.com` (QA Mailpit). Never invent other addresses.
- Secrets never pass through chat. Never ask the user to paste a password, login or token. Never open, print or `cat` the QA `.env`, the draft, or the Keychain value (no `security ... -w`).

## 0. Check this machine is set up (every time, no QA contact)

Run these checks and report each as ready or missing:

1. **QA repo clone:** `git -C ~/Desktop/code/rewardstack-qa-playwright rev-parse --show-toplevel`. If it isn't there, ask the user where their clone is.
2. **Docker:** `docker info --format '{{.ServerVersion}}'`.
3. **QA `.env` in Keychain:** `security find-generic-password -s adr-qa-playwright -a qa-dotenv >/dev/null 2>&1 && echo stored || echo missing`. This checks the entry exists without reading it.
4. **Tools:** `gh auth status` and `command -v jq`.

If everything is ready, go to step 1. If anything is missing, walk the user through **Setup** below, one step at a time: say what the step does, run what you can, and wait where the user has to act.

## Setup (once per machine)

1. **Clone:** `gh repo clone alldigitalrewards/rewardstack-qa-playwright ~/Desktop/code/rewardstack-qa-playwright`.
2. **Docker Desktop running.** Nothing runs on the host. The runner pulls `mcr.microsoft.com/playwright:v<suite version>-noble` and keeps packages in the Docker volume `qa-pw-node-modules`.
3. **The QA `.env` goes into the user's macOS Keychain** (`adr-qa-playwright` / `qa-dotenv`). Every key in `scripts/required-env-keys.txt` needs a value, or the suite won't load. First ask the user one question: do you have the QA `.env` file, from the team password manager or a teammate?
   - **Yes:** they run `import-qa-env.sh <path to the file>`, then delete the file. It lists any missing keys by name.
   - **No: create their own QA admin accounts.**
     1. Run `new-qa-env.sh <their-name>@alldigitalrewards.com`. It writes `~/.config/adr-qa-playwright/qa.env.draft`, readable only by them. It puts the role addresses on their own plus-alias (`<name>+qa-super-admin@alldigitalrewards.com` and so on), generates the test passwords, and marks everything else `NOT-CONFIGURED`.
     2. They fill in the five empty lines at the top themselves, in their own Terminal: `nano ~/.config/adr-qa-playwright/qa.env.draft`, type each value right after the `=` with no quotes, then Ctrl+O, Enter, Ctrl+X.
        - `MAILPIT_URL`, `MAILPIT_USER` and `MAILPIT_PASSWORD`: QA's email catcher. Ask the team lead or infra for it.
        - `BOOTSTRAP_SU_EMAIL` and `BOOTSTRAP_SU_PASSWORD`: their own QA RewardSTACK Super Admin login. Only a Super Admin can invite admin accounts. It's used for one sign-in and never stored.
     3. Confirm the five lines are filled without reading them: `grep -qE '^KEY=.+'` for each key, reporting only filled or empty. Also check that the Mailpit host ends in `.adrqa.info`.
     4. This step writes to QA, so tell the user exactly what it does and wait for **yes** or **go**. Then run `create-qa-accounts.sh` from the QA repo root. Through the QA repo's `scripts/bootstrap-qa-admins.mjs`, it signs in once as their Super Admin, invites 9 admin accounts, reads each reset email from Mailpit, sets each password and signs each account in once. It also gives the Platform Admin account the `implementation.all` scope. Then it stores everything in Keychain and deletes the draft. If the Super Admin sign-in is refused, stop and have them fix the password before anything is run again.
     5. With accounts built this way, the Changemaker logins, the game-vendor and Amazon fulfillment JWT secrets, and the two marketplace passwords stay `NOT-CONFIGURED`. The runner refuses Changemaker, game-vendor, fulfillment and marketplace specs, and `--apps`, until those are real.
4. Re-run the step 0 checks and confirm everything is ready.

## 1. Read (no QA contact)

- Confirm the PR merged and its QA deploy finished: `gh pr view <N> -R alldigitalrewards/<repo> --json state,mergedAt,mergeCommit,title`, then `gh run list -R alldigitalrewards/<repo> --commit <merge sha>`. Not merged, or the deploy failed or is still running: stop and say so.
- Read the Jira ticket and its Testing Strategy attachment, using the Atlassian MCP or the adr-developer skill's `scripts/jira-rest.sh`.
- Find existing coverage from the QA repo root: `find tests -iname 'ds<number>*'`. The ticket number has no dash. Read each spec's header comment.
- Read what applies in `docs/standards/qa-jira-ticket-spec.md`: §3.8 (QA has no DB, Docker, kubectl or queue access; every step goes through the UI, the API or email), §4.4.8 for UI and §4.4.9 for API.

## 2. Write or update the spec (no QA contact)

- Name it `tests/<ui|api>/<area>/ds<number>_<what>_<ui|api>.spec.ts`. Copy the closest existing ticket spec in the same area and reuse its page objects (`pages/`), config (`@config`) and helpers (`utils/`).
- The header says the ticket, what is covered, and what QA cannot test and why.
- Each test case first sets up the condition that makes the changed code run, then checks that condition. If the check fails the case is BLOCKED, never PASS. A case passes only on an effect that a broken build would not produce.
- Never loosen an assertion or tag a real failure `@known-defect` to get green.
- Work on a local branch named after the ticket. Push or open a PR in the QA repo only when the user says.

## 3. Ask for the go

- Show the user the PR, the test cases and the spec files.
- Say which logins the run uses and what it creates or sends on QA: orders, participants, emails.
- Before asking, trace every login the specs make to where its credential comes from. Don't run a spec if any of its credentials is `NOT-CONFIGURED`, empty, or belongs to an account the user's `.env` doesn't hold (for example the QA team's own marketplace participants). Say why it was left out.
- Ask "Run this on QA? (yes/go)" and stop.

## 4. Run (only after yes/go)

From the QA repo root: `bash <skill base directory>/scripts/run-qa.sh tests/<area>/ds<number>_<what>.spec.ts`.

By default only the admin-portal roles sign in. Add `--apps` when the ticket needs the RA, Changemaker, LX Hausys or AK logins.

The runner does three things:

1. Checks offline that the suite loads, and prints the Chromium version.
2. Signs each admin role in once, stopping at the first refusal.
3. Runs the specs with one worker and no retries.

If step 2 stops, report the role and the HTTP status and do not run again. A 423 means the IP is locked: stop and tell the user.

## 5. Report

- For each test case: PASS, FAIL or BLOCKED, with the evidence (status codes, the assertion that decided it).
- For every FAIL, add numbered manual steps to reproduce it.
- Screenshots land in `reports/verify-states/screenshots/DS-<number>/` (gitignored). Share them only when asked.
- Post to Jira or move the ticket only when the user says.

## Browser safety

Playwright launches Chromium with its sandbox off. Playwright 1.64.1 pins Chromium 156.0.8078.4, which misses four Critical fixes shipped in Chrome 156.0.8078.73 on 2026-10-27 (CVE-2026-118306, -118331, -118348, -118344). The arm64 image this runner uses ships 156.0.8078.80, which has those fixes. If the runner's first step prints a Chromium version older than current Chrome stable, say so before running. Running in Docker keeps a browser compromise inside the container. Point this browser only at QA hosts and the specs' own link checks, never at sites you don't control.
