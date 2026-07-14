#!/usr/bin/env bash
# Reproduction entrypoint — the harness executes this file identically on the REPORTED version and
# on TRUNK. It must behave the same on both: never branch on the Shopware version or guess which
# leg is running.
#
# Verdict contract (per leg):   exit 0 = healthy behaviour observed
#                               exit 1 = the bug was observed
#                               exit 2+ = setup/environment failure (never counts as the bug)
#
# Env provided: APP_URL, SW_ACCESS_KEY (storefront sales-channel key), ADMIN_USER, ADMIN_PASS,
#               SHOP_DIR (the provisioned Shopware checkout), BUNDLE_DIR (this directory),
#               EVIDENCE_DIR (drop screenshots/dumps here for the issue comment).
set -euo pipefail

# ── 1. Prepare ──────────────────────────────────────────────────────────────────────────────────
# Everything the reproduction needs, done HERE so both legs are self-contained: seed data via the
# Admin API, install a plugin ("$SHOP_DIR"/bin/console plugin:install …), change config, build.
# If preparation fails, say so and exit 2 — a broken setup must never read as the bug:
#
#   echo "##repro blocked could not seed the product (see output above)"
#   exit 2

# ── 2. Reproduce ────────────────────────────────────────────────────────────────────────────────
# Drive the shop and OBSERVE. Report real runtime values through markers as you go:
#
#   echo "##repro step  opening the product detail page"
#   echo "##repro expected  order total 10.00"
#   echo "##repro observed  order total ${actual_total}"
#
# Save visual proof for the comment and caption it:
#
#   cp screenshot.png "$EVIDENCE_DIR/after-save.png"
#   echo "##repro evidence after-save.png :: Order detail right after saving"

# ── 3. Verdict ──────────────────────────────────────────────────────────────────────────────────
# Decide from what you OBSERVED, then exit 0 (healthy) or 1 (bug observed).

echo "##repro blocked run.sh is still the unedited scaffold"
exit 2
