#!/usr/bin/env bash
set -euo pipefail
source "$(cd "$(dirname "$0")" && pwd)/common.sh"

env_file="${1:-}"
[[ -n "$env_file" ]] || fail 'usage: import-qa-env.sh <path to the filled-in QA .env file>'
[[ -f "$env_file" ]] || fail "no such file: $env_file" 66

store_qa_env "$env_file"
echo "import-qa-env: delete $env_file once the team password manager holds a copy."
