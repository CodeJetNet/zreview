#!/usr/bin/env bash
set -euo pipefail

ticket="${1:?Usage: ticket-tokens.sh <TICKET-KEY>}"
projects="$HOME/.claude/projects"

files_mentioning() {
  find "$projects" "$@" -name '*.jsonl' -print0 | xargs -0 grep -lE -- "${ticket}([^0-9]|\$)" || true
}

events_in() {
  printf 'F\t%s\t%s\n' "$1" "$2"
  jq -r --arg key "$ticket" '
    def mentions: tostring | test($key + "(?![0-9])");
    if .type == "user" and .origin.kind == "human" then
      "P", (if .message | mentions then "K" else empty end)
    elif .type == "user" and .isMeta != true and .isCompactSummary != true then
      (if .message | mentions then "K" else empty end)
    elif .type == "assistant" and .message.usage != null then
      (if .message.content | mentions then "K" else empty end),
      (["U", .message.id, .message.model,
        .message.usage.input_tokens // 0,
        .message.usage.cache_creation_input_tokens // 0,
        .message.usage.cache_read_input_tokens // 0,
        .message.usage.output_tokens // 0] | @tsv)
    else empty end' "$2"
}

{
  while IFS= read -r file; do events_in session "$file"; done < <(files_mentioning -mindepth 2 -maxdepth 2)
  while IFS= read -r file; do events_in subagent "$file"; done < <(files_mentioning -path '*/subagents/*')
} | awk -F'\t' -v ticket="$ticket" '
  function commas(n,   s) {
    s = sprintf("%d", n)
    while (s ~ /[0-9][0-9][0-9][0-9]/) sub(/[0-9][0-9][0-9]($|,)/, ",&", s)
    return s
  }
  function flush(   id) {
    if (hit) {
      for (id in turn) {
        if (id in counted) continue
        counted[id] = 1
        fresh += input[id]; writes += created[id]; reads += read[id]; output += written[id]
        if (model[id] !~ /^</) models[model[id]] = 1
      }
      prompts += prompted
      if (kind == "session") sessions[file] = 1; else subagents[file] = 1
    }
    split("", turn); hit = 0; prompted = 0
  }
  $1 == "F" { flush(); kind = $2; file = $3; next }
  $1 == "P" { flush(); prompted = 1; next }
  $1 == "K" { hit = 1; next }
  $1 == "U" {
    turn[$2] = 1; model[$2] = $3
    if ($4 > input[$2]) input[$2] = $4
    if ($5 > created[$2]) created[$2] = $5
    if ($6 > read[$2]) read[$2] = $6
    if ($7 > written[$2]) written[$2] = $7
  }
  END {
    flush()
    for (f in sessions) sessionCount++
    for (f in subagents) subagentCount++
    for (m in models) modelList = modelList (modelList ? ", " : "") m
    total = fresh + writes + reads + output
    print "h4. Claude Code token usage"
    print "||Tokens||Count||"
    print "|New input|" commas(fresh) "|"
    print "|Cache writes|" commas(writes) "|"
    print "|Cache reads|" commas(reads) "|"
    print "|Output|" commas(output) "|"
    print "|*Total*|*" commas(total) "*|"
    print ""
    printf "Counted from %d prompt(s) in %d Claude Code session(s) and %d subagent run(s) whose work mentions %s. Model: %s. A prompt that also covered another ticket counts toward both. Cache reads are earlier context re-read on each step.\n", prompts, sessionCount, subagentCount, ticket, (modelList ? modelList : "none")
  }'
