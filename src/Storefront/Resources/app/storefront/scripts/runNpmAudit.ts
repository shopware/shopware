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
        'https://github.com/advisories/GHSA-2g4f-4pwh-qvx6', // ajv ReDoS with $data option, moderate severity, devDep only (jest/webpack), no safe override without semver major
        'https://github.com/advisories/GHSA-5j98-mcp5-4vw2', // glob CLI command injection via -c/--cmd, high severity, devDep only (jest/test-exclude), not exploitable via programmatic usage
        'https://github.com/advisories/GHSA-xxjr-mmjv-4gpg', // lodash Prototype Pollution in _.unset/_.omit, moderate severity, devDep only, no fix available for 4.x
    ],
});
