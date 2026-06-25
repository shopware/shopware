#!/usr/bin/env node
import { runNpmAudit } from '../../../.github/bin/js/run-npm-audit.ts';

/**
 * Run from the project directory: node ./scripts/runNpmAudit.ts
 *
 * When a new vulnerability is reported, prefer fixing it over ignoring:
 *
 * 1. First, try adding an "overrides" entry in package.json to pin the
 *    vulnerable transitive dependency to a fixed version.
 * 2. Only add a GHSA here if the vulnerability has no fix available, is a
 *    false positive, or only affects devDependencies and poses no real risk.
 *
 * Each entry should include a comment explaining why it is safe to ignore.
 *
 * Example:
 *   'https://github.com/advisories/GHSA-xxxx-xxxx-xxxx', // pkg-name issue, severity, devDep only, no fix available
 *
 * Current package.json pins address these advisories in the acceptance suite
 * dependency tree:
 * - GHSA-8988-4f7v-96qf by pinning lighthouse to 12.6.1
 * - GHSA-hmw2-7cc7-3qxx by pinning form-data to 4.0.6
 * - GHSA-h67p-54hq-rp68 by pinning js-yaml to 4.2.0
 * - GHSA-7c78-jf6q-g5cm by pinning tmp to 0.2.7
 */
runNpmAudit({
    ignoredGHSAs: [
    ],
});
