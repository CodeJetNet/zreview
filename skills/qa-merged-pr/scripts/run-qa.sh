#!/usr/bin/env bash
set -euo pipefail

readonly KEYCHAIN_SERVICE='adr-qa-playwright'
readonly KEYCHAIN_ACCOUNT='qa-dotenv'
readonly NODE_MODULES_VOLUME='qa-pw-node-modules'
OVERLAY="$(cd "$(dirname "$0")/../overlay" && pwd)"
readonly OVERLAY
readonly USAGE='usage: run-qa.sh [--apps] tests/<area>/<file>.spec.ts [more specs]   (run from your rewardstack-qa-playwright clone)'

fail() {
  echo "run-qa: $1" >&2
  exit "${2:-64}"
}

repo="$(git rev-parse --show-toplevel 2>/dev/null)" || fail "$USAGE"
[[ -f "$repo/fixtures/auth/auth.setup.ts" && -f "$repo/playwright.config.ts" ]] || fail "$repo is not a rewardstack-qa-playwright clone"

playwright_version="$(sed -nE 's/.*"@playwright\/test": *"[~^]?([0-9]+\.[0-9]+\.[0-9]+)".*/\1/p' "$repo/package.json")"
[[ -n "$playwright_version" ]] || fail 'cannot read the @playwright/test version from package.json'
readonly IMAGE="mcr.microsoft.com/playwright:v${playwright_version}-noble"

auth_admin_only=1
if [[ "${1:-}" == '--apps' ]]; then
  auth_admin_only=''
  shift
fi
[[ $# -gt 0 ]] || fail "$USAGE"
for spec in "$@"; do
  [[ "$spec" =~ ^tests/[A-Za-z0-9_./-]+\.spec\.ts$ && "$spec" != *..* ]] || fail "refusing spec path: $spec"
  [[ -f "$repo/$spec" ]] || fail "no such spec: $spec" 66
done

exclude_file="$(git -C "$repo" rev-parse --git-path info/exclude)"
[[ "$exclude_file" = /* ]] || exclude_file="$repo/$exclude_file"
for mounted in playwright.container.config.ts qa-preflight/; do
  grep -qxF "$mounted" "$exclude_file" 2>/dev/null || printf '%s\n' "$mounted" >> "$exclude_file"
done

QA_DOTENV_B64="$(security find-generic-password -s "$KEYCHAIN_SERVICE" -a "$KEYCHAIN_ACCOUNT" -w 2>/dev/null)" \
  || fail 'the QA .env is not in your Keychain yet; run import-qa-env.sh first' 78
export QA_DOTENV_B64

in_container() {
  local network=$1
  shift
  docker run --rm --network "$network" \
    -v "$repo":/work \
    -v "$NODE_MODULES_VOLUME":/work/node_modules \
    -v "$OVERLAY/playwright.container.config.ts":/work/playwright.container.config.ts:ro \
    -v "$OVERLAY/qa-preflight":/work/qa-preflight:ro \
    -w /work \
    -e QA_DOTENV_B64 \
    -e DOTENV_CONFIG_PATH=/tmp/qa.env \
    -e AUTH_ADMIN_ONLY="$auth_admin_only" \
    -e JIRA_REPORT= -e JIRA_AUTO_POST= -e JIRA_AUTO_CREATE= -e JIRA_USER= -e JIRA_TOKEN= \
    "$IMAGE" \
    bash -c 'printf %s "$QA_DOTENV_B64" | base64 -d > /tmp/qa.env && unset QA_DOTENV_B64 && exec "$@"' run-qa "$@"
}

lock_sha="$(shasum -a 256 "$repo/package-lock.json" | cut -d' ' -f1)"
in_container bridge bash -c '
  [[ "$(cat node_modules/.qa-lock-sha 2>/dev/null)" == "$1" ]] && exit 0
  find node_modules -mindepth 1 -maxdepth 1 -exec rm -rf {} +
  npm ci --ignore-scripts --no-audit --no-fund && printf %s "$1" > node_modules/.qa-lock-sha
' install "$lock_sha"

echo 'run-qa: 1/3 offline check that the suite loads (no network)'
in_container none npx playwright test -c playwright.container.config.ts --list --reporter=list "$@"
in_container none node -e "require('playwright-core').chromium.launch().then(async (browser) => { console.log('run-qa: browser is Chromium ' + browser.version()); await browser.close(); })"

echo 'run-qa: 2/3 sign in each admin role once, stopping at the first refusal'
in_container bridge npx playwright test -c qa-preflight/playwright.config.ts

echo 'run-qa: 3/3 run the specs against QA (1 worker, no retries)'
in_container bridge npx playwright test -c playwright.container.config.ts --workers=1 --retries=0 --reporter=list "$@"
