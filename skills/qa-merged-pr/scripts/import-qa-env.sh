#!/usr/bin/env bash
set -euo pipefail

readonly KEYCHAIN_SERVICE='adr-qa-playwright'
readonly KEYCHAIN_ACCOUNT='qa-dotenv'
readonly REQUIRED_KEYS="$(cd "$(dirname "$0")" && pwd)/required-env-keys.txt"

env_file="${1:?usage: import-qa-env.sh <path to the filled-in QA .env file>}"
[[ -f "$env_file" ]] || { echo "import-qa-env: no such file: $env_file" >&2; exit 66; }

missing=()
while IFS= read -r key; do
  [[ -n "$key" ]] || continue
  grep -qE "^${key}=.+" "$env_file" || missing+=("$key")
done < "$REQUIRED_KEYS"
if (( ${#missing[@]} > 0 )); then
  echo "import-qa-env: nothing stored; these keys have no value in $env_file:" >&2
  printf '  %s\n' "${missing[@]}" >&2
  exit 65
fi

security add-generic-password -U -s "$KEYCHAIN_SERVICE" -a "$KEYCHAIN_ACCOUNT" -w "$(base64 < "$env_file" | tr -d '\n')"
[[ "$(security find-generic-password -s "$KEYCHAIN_SERVICE" -a "$KEYCHAIN_ACCOUNT" -w | base64 -d)" == "$(cat "$env_file")" ]] \
  || { echo 'import-qa-env: Keychain read-back does not match the file' >&2; exit 70; }

echo "import-qa-env: stored $(grep -cE '^[A-Z0-9_]+=.+' "$env_file") filled-in values in your Keychain ($KEYCHAIN_SERVICE / $KEYCHAIN_ACCOUNT)."
echo "import-qa-env: delete $env_file once the team password manager holds a copy."
