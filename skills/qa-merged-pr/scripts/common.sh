readonly KEYCHAIN_SERVICE='adr-qa-playwright'
readonly KEYCHAIN_ACCOUNT='qa-dotenv'
readonly NODE_MODULES_VOLUME='qa-pw-node-modules'
readonly UNCONFIGURED='NOT-CONFIGURED'
readonly DRAFT_ENV="$HOME/.config/adr-qa-playwright/qa.env.draft"
REQUIRED_KEYS="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/required-env-keys.txt"
readonly REQUIRED_KEYS

fail() {
  echo "$(basename "$0" .sh): $1" >&2
  exit "${2:-64}"
}

qa_repo() {
  local repo
  repo="$(git rev-parse --show-toplevel 2>/dev/null)" || fail 'run this from your rewardstack-qa-playwright clone'
  [[ -f "$repo/fixtures/auth/auth.setup.ts" && -f "$repo/playwright.config.ts" ]] || fail "$repo is not a rewardstack-qa-playwright clone"
  printf '%s' "$repo"
}

playwright_image() {
  local version
  version="$(sed -nE 's/.*"@playwright\/test": *"[~^]?([0-9]+\.[0-9]+\.[0-9]+)".*/\1/p' "$1/package.json")"
  [[ -n "$version" ]] || fail 'cannot read the @playwright/test version from package.json'
  printf 'mcr.microsoft.com/playwright:v%s-noble' "$version"
}

exclude_from_git() {
  local repo=$1 exclude_file path
  shift
  exclude_file="$(git -C "$repo" rev-parse --git-path info/exclude)"
  [[ "$exclude_file" = /* ]] || exclude_file="$repo/$exclude_file"
  for path in "$@"; do
    grep -qxF "$path" "$exclude_file" 2>/dev/null || printf '%s\n' "$path" >> "$exclude_file"
  done
}

store_qa_env() {
  local env_file=$1 key
  local -a missing=()
  while IFS= read -r key; do
    [[ -n "$key" ]] || continue
    grep -qE "^${key}=.+" "$env_file" || missing+=("$key")
  done < "$REQUIRED_KEYS"
  if (( ${#missing[@]} > 0 )); then
    printf '  %s\n' "${missing[@]}" >&2
    fail "nothing stored; the keys above have no value in $env_file" 65
  fi
  security add-generic-password -U -s "$KEYCHAIN_SERVICE" -a "$KEYCHAIN_ACCOUNT" -w "$(base64 < "$env_file" | tr -d '\n')"
  [[ "$(security find-generic-password -s "$KEYCHAIN_SERVICE" -a "$KEYCHAIN_ACCOUNT" -w | base64 -d)" == "$(cat "$env_file")" ]] \
    || fail 'Keychain read-back does not match the file' 70
  echo "$(basename "$0" .sh): stored $(grep -cE '^[A-Z0-9_]+=.+' "$env_file") values in your Keychain ($KEYCHAIN_SERVICE / $KEYCHAIN_ACCOUNT)."
}
