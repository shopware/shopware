#!/usr/bin/env bash
# Synthetic diff: a new daily scheduled task (CleanupUserRecoveryTask) with a final
# handler extending ScheduledTaskHandler, batched DELETE, DI registration with the
# shopware.scheduled.task tag and a unit test asserting the effect. This is the
# correct async pattern from guides/scheduled-tasks-and-queue.md.
# Expectation: no QUEUE- finding, no scope finding, nothing major or blocking.
set -uo pipefail

source "$(dirname "$0")/_lib.sh"

load_output
check_schema_persona

check "persona-attribution"     '.persona == "architecture"'
require "no-queue-rule-id"      '[(.findings // [])[] | select((.rule_id // "") | startswith("QUEUE-"))] | length == 0'
check "no-scope-category"       '[(.findings // [])[] | select(.category == "scope")] | length == 0'
require "no-major-or-blocking"  '[(.findings // [])[] | select(.severity == "major" or .severity == "blocking")] | length == 0'

emit_result
