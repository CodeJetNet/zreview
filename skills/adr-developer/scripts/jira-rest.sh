#!/usr/bin/env bash
set -euo pipefail

readonly JIRA_BASE='https://alldigitalrewards.atlassian.net'
readonly JIRA_LOGIN='jmuto@alldigitalrewards.com'
readonly USAGE='usage: jira-rest.sh [-o <response-file>] GET|POST|PUT|DELETE /rest/api/<2|3>/<path> [@<body.json> | upload:<file>]'

fail() {
  echo "jira-rest: $1" >&2
  exit 64
}

response_file=/dev/stdout
while getopts 'o:' option; do
  case "$option" in
    o) response_file=$OPTARG ;;
    *) fail "$USAGE" ;;
  esac
done
shift $((OPTIND - 1))

[[ $# -ge 2 && $# -le 3 ]] || fail "$USAGE"
[[ -n "${JIRA_API_TOKEN:-}" ]] || fail 'JIRA_API_TOKEN is not set'

method=$1
path=$2
body=${3:-}
lowercase_path=$(printf '%s' "$path" | tr '[:upper:]' '[:lower:]')

[[ "$method" =~ ^(GET|POST|PUT|DELETE)$ ]] || fail "unsupported method: $method"
[[ "$path" =~ ^/rest/api/[23]/[A-Za-z0-9/_.,=\&?%-]+$ && "$path" != *..* ]] || fail "not a Jira REST path: $path"
[[ "$lowercase_path" != *worklog* ]] || fail 'worklogs are never logged'
[[ "$method" != DELETE || "$path" =~ ^/rest/api/[23]/attachment/[0-9]+$ ]] || fail 'DELETE is only allowed on attachments'

curl_args=(-s -X "$method" -o "$response_file" -w '%{http_code}')
case "$body" in
  '') ;;
  @*)
    [[ -f "${body#@}" ]] || fail "no such body file: ${body#@}"
    curl_args+=(-H 'Content-Type: application/json' --data-binary "$body")
    ;;
  upload:*)
    [[ -f "${body#upload:}" ]] || fail "no such upload file: ${body#upload:}"
    [[ "$method" == POST && "$path" == */attachments ]] || fail 'uploads are POSTs to .../attachments'
    curl_args+=(-H 'X-Atlassian-Token: no-check' -F "file=@${body#upload:}")
    ;;
  *) fail "$USAGE" ;;
esac

status=$(curl --config <(printf 'user = "%s:%s"\n' "$JIRA_LOGIN" "$JIRA_API_TOKEN") "${curl_args[@]}" "$JIRA_BASE$path")
echo "HTTP $status" >&2
(( status >= 200 && status < 300 ))
