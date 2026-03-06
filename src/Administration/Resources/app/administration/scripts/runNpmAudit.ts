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
        'https://github.com/advisories/GHSA-848j-6mx2-7j84', // elliptic ECDSA flaw, low severity, devDep only (vite-plugin-node-polyfills/crypto-browserify), no patch available
        'https://github.com/advisories/GHSA-3ppc-4f35-3m26', // minimatch ReDoS, high severity, devDep only, needs ESLint 9 migration to drop eslint-plugin-import
        "https://github.com/advisories/GHSA-vpq2-c234-7xj6", // require jest update, which is in progress https://github.com/shopware/shopware/pull/15182
        "https://github.com/advisories/GHSA-23c5-xmqv-rm74", // minimatch - waiting for eslint update
        "https://github.com/advisories/GHSA-7r86-cg39-jmmj", // minimatch - waiting for eslint update
        "https://github.com/advisories/GHSA-fp25-p6mj-qqg6", // needs to wait for twig lib update release
    ],
});
