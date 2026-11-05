---
name: qa-merged-pr
description: Use when asked to QA a merged PR or a ticket that is in QA ("review merged PR", "QA DS-XXXXX", "test this on QA", "verify on QA") with the rewardstack-qa-playwright suite. Reads the ticket and its Testing Strategy, reuses or writes that ticket's Playwright spec, runs it against QA in Docker only after the user says yes or go, and reports PASS / FAIL / BLOCKED per test case.
---

# QA a merged PR

The QA suite is `alldigitalrewards/rewardstack-qa-playwright` (Playwright + TypeScript). Every ticket QA has tested has a spec named `ds<number>_*.spec.ts` under `tests/ui/<area>/` or `tests/api/<area>/`. This skill runs that suite the way QA does, with QA's safety rules, so any developer can verify a merged PR.

## The gate (never skip)

- Nothing touches QA until the user answers **yes** or **go** to the run plan for this PR. A go covers that PR only. Stage is not supported yet.
- QA locks the caller's IP after 5 failed sign-ins, and that locks the whole team out. Never retry a refused login, never guess a password, never run with retries, `--repeat-each` or extra workers. The runner signs each admin role in once before anything else and stops at the first refusal.
- Never production. The preflight only accepts `*.adrqa.info`, and the suite's global setup aborts on any non-QA host.
- Test emails go only to `suhrobu+<anything>@alldigitalrewards.com` (QA Mailpit). Never invent other addresses.

## One-time setup

1. Clone the suite: `gh repo clone alldigitalrewards/rewardstack-qa-playwright ~/Desktop/code/rewardstack-qa-playwright`.
2. Docker Desktop must be running. Nothing runs on the host. The runner uses `mcr.microsoft.com/playwright:v<suite version>-noble` and keeps packages in the Docker volume `qa-pw-node-modules`.
3. Get the QA `.env` from the team password manager. It is a filled-in copy of the repo's `.env.example`, and every key in `scripts/required-env-keys.txt` needs a value, or the suite won't load. Then run `bash <this skill>/scripts/import-qa-env.sh <path to .env>`. It stores the file in your macOS Keychain (`adr-qa-playwright` / `qa-dotenv`) and lists any missing keys. Delete the file afterwards. The values never go into the repo or the container's disk.

## Steps

### 1. Read (no QA contact)

- Confirm the PR merged and its QA deploy finished: `gh pr view <N> -R alldigitalrewards/<repo> --json state,mergedAt,mergeCommit,title`, then `gh run list -R alldigitalrewards/<repo> --commit <merge sha>`. Not merged, or the deploy failed or is still running: stop and say so.
- Read the Jira ticket and its Testing Strategy attachment, using the Atlassian MCP or the adr-developer skill's `scripts/jira-rest.sh`.
- Find existing coverage from the QA repo root: `find tests -iname 'ds<number>*'`. The ticket number has no dash. Read each spec's header comment.
- Read what applies in `docs/standards/qa-jira-ticket-spec.md`: §3.8 (QA has no DB, Docker, kubectl or queue access; every step goes through the UI, the API or email), §4.4.8 for UI and §4.4.9 for API.

### 2. Write or update the spec (no QA contact)

- Name it `tests/<ui|api>/<area>/ds<number>_<what>_<ui|api>.spec.ts`. Copy the closest existing ticket spec in the same area and reuse its page objects (`pages/`), config (`@config`) and helpers (`utils/`).
- The header says the ticket, what is covered, and what QA cannot test and why.
- Each test case first sets up the condition that makes the changed code run, then checks that condition. If the check fails the case is BLOCKED, never PASS. A case passes only on an effect that a broken build would not produce.
- Never loosen an assertion or tag a real failure `@known-defect` to get green.
- Work on a local branch named after the ticket. Push or open a PR in the QA repo only when the user says.

### 3. Ask for the go

Show the user the PR, the test cases and the spec files. Say which logins the run uses and what it creates or sends on QA: orders, participants, emails. Ask "Run this on QA? (yes/go)" and stop.

### 4. Run (only after yes/go)

From the QA repo root: `bash <this skill>/scripts/run-qa.sh tests/<area>/ds<number>_<what>.spec.ts`.

By default only the admin-portal roles sign in. Add `--apps` when the ticket needs the RA, Changemaker, LX Hausys or AK logins.

The runner does three things:

1. Checks offline that the suite loads, and prints the Chromium version.
2. Signs each admin role in once, stopping at the first refusal.
3. Runs the specs with one worker and no retries.

If step 2 stops, report the role and the HTTP status and do not run again. A 423 means the IP is locked: stop and tell the user.

### 5. Report

- For each test case: PASS, FAIL or BLOCKED, with the evidence (status codes, the assertion that decided it).
- For every FAIL, add numbered manual steps to reproduce it.
- Screenshots land in `reports/verify-states/screenshots/DS-<number>/` (gitignored). Share them only when asked.
- Post to Jira or move the ticket only when the user says.

## Browser safety

Playwright launches Chromium with its sandbox off. Playwright 1.64.1 pins Chromium 156.0.8078.4, which misses four Critical fixes shipped in Chrome 156.0.8078.73 on 2026-10-27 (CVE-2026-118306, -118331, -118348, -118344). The arm64 image this runner uses ships 156.0.8078.80, which has those fixes. If step 1 prints a version older than current Chrome stable, say so before running. Running in Docker keeps a browser compromise inside the container. Point this browser only at QA hosts and the specs' own link checks, never at sites you don't control.
