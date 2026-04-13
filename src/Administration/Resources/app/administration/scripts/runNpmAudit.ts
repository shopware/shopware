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
        'https://github.com/advisories/GHSA-3ppc-4f35-3m26', // minimatch ReDoS, high severity, devDep only (twig), fix requires twig semver major
        'https://github.com/advisories/GHSA-7r86-cg39-jmmj', // minimatch ReDoS (matchOne), high severity, devDep only (twig), fix requires twig semver major
        'https://github.com/advisories/GHSA-23c5-xmqv-rm74', // minimatch ReDoS (extglobs), high severity, devDep only (twig), fix requires twig semver major
        'https://github.com/advisories/GHSA-5rq4-664w-9x2c', // basic-ftp Path Traversal, critical severity, devDep only (playwright), fix requires semver major (4.x → 5.x)
        'https://github.com/advisories/GHSA-fp25-p6mj-qqg6', // locutus RCE via call_user_func_array, high severity, devDep only (twig), fix requires twig semver major
        'https://github.com/advisories/GHSA-vh9h-29pq-r5m8', // locutus RCE via create_function(), critical severity, devDep only (twig), fix requires twig semver major
        'https://github.com/advisories/GHSA-qpx9-hpmf-5gmw', // underscore unlimited recursion DoS, high severity, devDep only, no fix available in 1.x
    ],
});
