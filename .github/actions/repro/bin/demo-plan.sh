#!/usr/bin/env bash
# DEMO (dry_run): emit a canned plan + demo script for one executor so the WHOLE pipeline
# (provision → executor → verdict → report) can be smoke-tested for $0 — no agent, no API
# key. confidence is 1.0 so the < 0.4 bail-out doesn't suppress the run; derived_from +
# the ::warning:: mark it fake.
#
# Env: DEMO_LAYER (http|direct|playwright; default http), ISSUE (req),
#      DEMO_DIR (default .github/actions/repro/demo).
set -euo pipefail

: "${ISSUE:?ISSUE is required}"
DEMO_LAYER=${DEMO_LAYER:-http}
DEMO_DIR=${DEMO_DIR:-.github/actions/repro/demo}

echo "::warning::DEMO dry-run ($DEMO_LAYER) — canned plan, NOT a real analysis."
case "$DEMO_LAYER" in
  http)
    jq -n --argjson issue "$ISSUE" '{
      schema_version:"1", issue:$issue, layer:"store-api", executor:"http", version:"6.6.10.0",
      scenario:["Given a running shop","When POST /store-api/checkout/cart with an empty body","Then a healthy shop returns HTTP 400"],
      build_profile:{admin_build:false,storefront_build:false,theme_build:false},
      fixtures:{demodata:false,sync_payload_path:"fixtures.json"},
      request:{method:"POST",path:"/store-api/checkout/cart",headers:{"Content-Type":"application/json"},body:"{}"},
      assertion:{kind:"http_status",expect:"400",field:null,locator:"/store-api/checkout/cart"},
      derived_from:"DEMO (dry-run)", confidence:1.0, blocked_reason:null
    }' > analysis.json
    ;;
  direct)
    jq -n --argjson issue "$ISSUE" '{
      schema_version:"1", issue:$issue, layer:"service", executor:"direct", version:"6.6.10.0",
      scenario:["Given a provisioned shop","When a PHPUnit integration test resolves a core service","Then the service is available (healthy)"],
      build_profile:{admin_build:false,storefront_build:false,theme_build:false},
      fixtures:{demodata:false,sync_payload_path:"fixtures.json"},
      script_path:"ReproTest.php",
      derived_from:"DEMO (dry-run)", confidence:1.0, blocked_reason:null
    }' > analysis.json
    cp "$DEMO_DIR/ReproTest.php" ReproTest.php
    ;;
  playwright)
    jq -n --argjson issue "$ISSUE" '{
      schema_version:"1", issue:$issue, layer:"admin-ui", executor:"playwright", version:"6.6.10.0",
      scenario:["Given a provisioned shop with the admin built","When the admin entry point is opened","Then the page renders (healthy)"],
      build_profile:{admin_build:true,storefront_build:false,theme_build:false},
      fixtures:{demodata:false,sync_payload_path:"fixtures.json"},
      script_path:"repro.spec.ts",
      derived_from:"DEMO (dry-run)", confidence:1.0, blocked_reason:null
    }' > analysis.json
    cp "$DEMO_DIR/repro.spec.ts" repro.spec.ts
    ;;
  *) echo "::error::unknown demo_layer '$DEMO_LAYER'"; exit 1 ;;
esac
cat analysis.json
