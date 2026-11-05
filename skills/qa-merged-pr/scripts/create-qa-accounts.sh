#!/usr/bin/env bash
set -euo pipefail
readonly SCRIPTS="$(cd "$(dirname "$0")" && pwd)"
source "$SCRIPTS/common.sh"

repo="$(qa_repo)"
image="$(playwright_image "$repo")"
command -v jq >/dev/null || fail 'jq is required (brew install jq)'
[[ -f "$DRAFT_ENV" ]] || fail 'run new-qa-env.sh first'
for key in MAILPIT_URL MAILPIT_USER MAILPIT_PASSWORD BOOTSTRAP_SU_EMAIL BOOTSTRAP_SU_PASSWORD; do
  grep -qE "^${key}=.+" "$DRAFT_ENV" || fail "fill in $key in $DRAFT_ENV"
done

work="$(mktemp -d)"
trap 'rm -rf -- "${work:?}"' EXIT
grep -vE '^BOOTSTRAP_SU_(EMAIL|PASSWORD)=' "$DRAFT_ENV" > "$work/suite.env"
grep -E '^BOOTSTRAP_SU_(EMAIL|PASSWORD)=' "$DRAFT_ENV" > "$work/seed.env"
: > "$work/bootstrap-result.json"
exclude_from_git "$repo" scripts/.bootstrap-result.json

docker run --rm \
  -v "$repo":/work \
  -v "$work/suite.env":/work/.env:ro \
  -v "$work/bootstrap-result.json":/work/scripts/.bootstrap-result.json \
  --env-file "$work/seed.env" \
  -w /work \
  "$image" \
  node scripts/bootstrap-qa-admins.mjs

jq -r 'to_entries[] | "\(.key)=\(.value)"' "$work/bootstrap-result.json" > "$work/passwords.env"
awk -F= 'NR == FNR { password[$1] = substr($0, length($1) + 2); next } ($1 in password) { print $1 "=" password[$1]; next } { print }' \
  "$work/passwords.env" "$work/suite.env" > "$work/qa.env"

missing="$(grep -E '^[A-Z0-9_]+_PASSWORD=$' "$work/qa.env" | cut -d= -f1 | tr '\n' ' ' || true)"
[[ -z "$missing" ]] || fail "accounts not ready, nothing stored; re-run once the cause above is fixed: $missing" 70

store_qa_env "$work/qa.env"
rm -- "$DRAFT_ENV"
echo "create-qa-accounts: removed $DRAFT_ENV; your QA Super Admin login was used once and not stored."
