#!/usr/bin/env node
import { runNpmAudit } from '../../../../../../.github/bin/js/run-npm-audit.ts';

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
 */
runNpmAudit({
    ignoredGHSAs: [
        'https://github.com/advisories/GHSA-848j-6mx2-7j84', // elliptic ECDSA flaw, low severity, devDep only (vite-plugin-node-polyfills/crypto-browserify), fix requires semver major
        'https://github.com/advisories/GHSA-w5hq-g745-h8pq', // uuid buffer bounds check, moderate severity, devDep only (@lhci/cli uses v4 without buf), no safe upstream fix yet
    ],
});
