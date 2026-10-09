#!/usr/bin/env bash
# Synthetic diff: SendMailHandler turns a temporary 4xx SMTP answer into a
# RecoverableMessageHandlingException with a longer retry delay. Retries and
# backoff inside a message handler are correct async behaviour and are listed
# under "Do not flag" in guides/platform-scope.md.
# Expectation: no scope finding and no SCOPE- rule id. Other lenses (for example
# a minor test remark) may still comment; they are not this guide's concern.
set -uo pipefail

source "$(dirname "$0")/_lib.sh"

load_output
check_schema_persona

check "persona-attribution"   '.persona == "maintainer"'
require "no-scope-category"   '[(.findings // [])[] | select(.category == "scope")] | length == 0'
require "no-scope-rule-id"    '[(.findings // [])[] | select((.rule_id // "") | startswith("SCOPE-"))] | length == 0'
check "no-blocking"           '[(.findings // [])[] | select(.severity == "blocking")] | length == 0'

emit_result
