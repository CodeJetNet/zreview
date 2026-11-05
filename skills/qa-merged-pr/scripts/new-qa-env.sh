#!/usr/bin/env bash
set -euo pipefail
source "$(cd "$(dirname "$0")" && pwd)/common.sh"

email="${1:-}"
[[ "$email" =~ ^[a-z0-9._-]+@alldigitalrewards\.com$ ]] || fail 'usage: new-qa-env.sh <your-name@alldigitalrewards.com>'
[[ ! -e "$DRAFT_ENV" ]] || fail "$DRAFT_ENV already exists; finish or delete it first"
readonly MAILBOX="${email%@*}"

generated_password() {
  printf 'Qa!%sZz9' "$(openssl rand -hex 9)"
}

role_address() {
  local slug
  slug="$(printf '%s' "$1" | tr '[:upper:]_' '[:lower:]-')"
  [[ "$slug" == 'qa-superadmin-user' ]] && slug='platform-admin'
  printf '%s+qa-%s@alldigitalrewards.com' "$MAILBOX" "$slug"
}

draft_line() {
  local key=$1
  case "$key" in
    CM_* | *_JWT_SECRET | TEST_PASSWORD_MARKETPLACE | TEST_PASSWORD_LANGUAGE) printf '%s=%s\n' "$key" "$UNCONFIGURED" ;;
    DEFAULT_PASSWORD | TEST_PASSWORD_*) printf '%s=%s\n' "$key" "$(generated_password)" ;;
    QA_SUPERADMIN_USER_EMAIL) printf '%s=%s\n' "$key" "$(role_address QA_SUPERADMIN_USER)" ;;
    *_USERNAME) printf '%s=%s\n' "$key" "$(role_address "${key%_USERNAME}")" ;;
    *_PASSWORD) printf '%s=\n' "$key" ;;
    *) fail "no rule for required key $key" ;;
  esac
}

umask 077
mkdir -p "$(dirname "$DRAFT_ENV")"
{
  printf '%s\n' 'BASE_URL=https://admin.adrqa.info' 'MAILPIT_URL=' 'MAILPIT_USER=' 'MAILPIT_PASSWORD=' 'BOOTSTRAP_SU_EMAIL=' 'BOOTSTRAP_SU_PASSWORD='
  while IFS= read -r key; do
    [[ -n "$key" ]] && draft_line "$key"
  done < "$REQUIRED_KEYS"
} > "$DRAFT_ENV"

echo "new-qa-env: wrote $DRAFT_ENV (only you can read it)."
echo 'new-qa-env: fill in the first five empty values: MAILPIT_URL, MAILPIT_USER, MAILPIT_PASSWORD, and your own QA Super Admin login as BOOTSTRAP_SU_EMAIL / BOOTSTRAP_SU_PASSWORD.'
echo 'new-qa-env: then run create-qa-accounts.sh from your rewardstack-qa-playwright clone.'
