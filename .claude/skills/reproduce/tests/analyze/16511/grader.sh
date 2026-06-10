#!/usr/bin/env bash
# Bug #16511: out-of-range store-api product-listing returns 200 instead of 404.
# Fix #16575's regression test asserts 404 + PRODUCT__LISTING_PAGE_OUT_OF_RANGE.
#
# A correct Analyze derives: store-api/http, the verbatim request (product-listing
# + p=99), the HEALTHY assertion (404 or the OUT_OF_RANGE error code), reported
# version 6.7.9.0, no asset build, minimal fixtures (demodata:false).
set -uo pipefail

source "$(dirname "$0")/_lib.sh"
load_output
check_schema_analysis

check "layer-store-api"      '.layer == "store-api"'
check "executor-http"        '.executor == "http"'
check "version-reported"     '.version == "6.7.9.0"'
check "request-is-listing"   '(.request.path // "") | test("product-listing")'
check "request-out-of-range" '(.request.path // "") | test("p=99")'
check "request-method-post"  '(.request.method // "") | ascii_upcase == "POST"'
# expect = HEALTHY value: either 404 (status) or the OUT_OF_RANGE error code (field).
check "assertion-healthy" '
    (.assertion.kind == "http_status" and (.assertion.expect | tostring) == "404")
    or (.assertion.kind == "response_field" and ((.assertion.expect // "") | test("OUT_OF_RANGE")))'
check "no-asset-build"       '[.build_profile.admin_build, .build_profile.storefront_build, .build_profile.theme_build] | all(. == false)'
check "minimal-fixtures"     '.fixtures.demodata == false'
check "derived-from-test"    '(.derived_from // "") | test("16575|ProductListing|OUT_OF_RANGE")'

emit_result
