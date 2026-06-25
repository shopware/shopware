#!/usr/bin/env bash
set -euo pipefail

if [ -n "${DEBUG:-}" ]; then
  set -x
fi

# Posts a per-shard summary of the acceptance jobs of a workflow run to Slack.
# Required env:
#   REPO                    owner/repo (github.repository)
#   RUN_ID                  workflow run id (github.run_id)
#   GH_TOKEN                token for `gh api` (github.token)
#   SLACK_ATS_WORKFLOW_URL  Slack incoming-webhook URL
: "${REPO:?REPO is required}"
: "${RUN_ID:?RUN_ID is required}"
: "${SLACK_ATS_WORKFLOW_URL:?SLACK_ATS_WORKFLOW_URL is required}"

# Collect every job of the run (the API returns at most 100 per page).
jobs="[]"
page=1
while true; do
  response=$(gh api "/repos/${REPO}/actions/runs/${RUN_ID}/jobs?per_page=100&page=${page}")
  jobs=$(jq --argjson new "$(jq '.jobs' <<<"$response")" '. + $new' <<<"$jobs")

  if [ "$(jq '.jobs | length' <<<"$response")" -lt 100 ]; then
    break
  fi
  page=$((page + 1))
done

# Keep only the acceptance jobs (named "acceptance (...)") and sort them for
# stable, readable output: non-major before major, then PHP/shard, Install last.
acceptance_jobs=$(jq -r '
    .[]
    | select(.name | contains("acceptance ("))
    | . as $job
    | ($job.name | capture("acceptance \\((?<name>[^,]+), (?:(?<major>major), )?(?<php>[^,]+), (?<shard>\\d+), (?<shardCount>\\d+), (?<currents>true|false)\\)")) as $parsed
    | {
        id: $job.id,
        name: $job.name,
        conclusion: $job.conclusion,
        sort: [
          ($parsed.name == "Install"),          # Install last
          ($parsed.major == "major"),           # non-major first
          ($parsed.php),                        # PHP version
          ($parsed.shard | tonumber),           # shard order
          ($parsed.currents == "false")         # currents=false last
        ]
      }
    | @json
    ' <<<"$jobs" |
  jq -s -r 'sort_by(.sort)[] | "\(.id);\(.name);\(.conclusion)"')

message=""
while IFS=';' read -r job_id job_name job_conclusion; do
  [ -z "$job_name" ] && continue
  case "$job_conclusion" in
    success)           message="${message}✅ ${job_name}\n" ;;
    failure|cancelled) message="${message}❌ ${job_name} ( https://github.com/${REPO}/actions/runs/${RUN_ID}/job/${job_id} )\n" ;;
    skipped*)          message="${message}⏭️ ${job_name}\n" ;;
    *)                 message="${message}❓ ${job_name} ( https://github.com/${REPO}/actions/runs/${RUN_ID}/job/${job_id} )\n" ;;
  esac
done <<<"$acceptance_jobs"

payload=$(jq --null-input --arg message "$(printf '%b' "$message")" '{"message": $message}')
curl --silent --request POST --url "${SLACK_ATS_WORKFLOW_URL}" --header "Content-Type: application/json" --data "${payload}"
