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
 */
runNpmAudit({
    ignoredGHSAs: [
        'https://github.com/advisories/GHSA-qj83-cq47-w5f8', // axios HTTP/2 cleanup issue, legacy compatibility setup intentionally allows older axios while newer axios is used where migrated
        'https://github.com/advisories/GHSA-3p68-rc4w-qgx5', // axios NO_PROXY SSRF advisory, ignored for the same legacy compatibility reason
        'https://github.com/advisories/GHSA-fvcv-3m26-pcqx', // axios header injection/cloud metadata advisory, ignored for the same legacy compatibility reason
    ],
});
