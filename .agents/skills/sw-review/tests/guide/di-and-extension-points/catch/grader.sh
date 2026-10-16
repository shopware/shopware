#!/usr/bin/env bash
# Plant: base trunk. (1) A new static PriceRoundingHelper copies the logic of
# the CashRounding service and is called from AbsolutePriceCalculator instead
# of injecting CashRounding (DIX-007). (2) A new public EntitySearchCriteriaEvent
# is dispatched in EntitySearcher::search(), which every DAL search passes,
# although EntitySearchedEvent and the Store API extension events exist (DIX-003,
# also DIX-004/DIX-006). Guide di-and-extension-points.
# Expectation: a DIX- finding on the EntitySearcher event; the helper finding is
# expected but not decisive.
set -uo pipefail

source "$(dirname "$0")/_lib.sh"

load_output
check_schema_persona

check   "persona-attribution"   '.persona == "architecture"'
require "dix-rule-finding"      '[(.findings // [])[] | select((.rule_id // "") | startswith("DIX-"))] | length >= 1'
require "hot-path-finding"      '[(.findings // [])[] | select(((.rule_id // "") | startswith("DIX-")) and (.file == "src/Core/Framework/DataAbstractionLayer/Dbal/EntitySearcher.php" or .file == "src/Core/Framework/DataAbstractionLayer/Event/EntitySearchCriteriaEvent.php"))] | length >= 1'
check   "static-helper-finding" '[(.findings // [])[] | select(((.rule_id // "") | startswith("DIX-")) and (.file == "src/Core/Checkout/Cart/Price/PriceRoundingHelper.php" or .file == "src/Core/Checkout/Cart/Price/AbsolutePriceCalculator.php"))] | length >= 1'
check   "hot-path-major"        '[(.findings // [])[] | select(((.rule_id // "") | startswith("DIX-")) and (.severity == "major" or .severity == "blocking"))] | length >= 1'

emit_result
