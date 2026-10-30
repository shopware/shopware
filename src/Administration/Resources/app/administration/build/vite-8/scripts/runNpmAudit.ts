#!/usr/bin/env node
import { runNpmAudit } from '../../../../../../../../.github/bin/js/run-npm-audit.ts';

/**
 * Run from the toolchain directory: node ./scripts/runNpmAudit.ts
 *
 * The toolchain is only build tooling of the Administration, so it shares the ignored advisories of
 * `src/Administration/Resources/app/administration/scripts/runNpmAudit.ts` for the same packages.
 */
runNpmAudit({
    ignoredGHSAs: [
        'https://github.com/advisories/GHSA-848j-6mx2-7j84', // elliptic ECDSA flaw, low severity, build tooling only (vite-plugin-node-polyfills/crypto-browserify), fix requires semver major
        'https://github.com/advisories/GHSA-vfj7-8cjw-p6xm', // braces stack-exhaustion DoS on deeply nested patterns, build tooling only (chokidar/micromatch via vite-plugin-symfony), patterns are developer-controlled and never reach the shipped bundle; no patched braces release exists yet (<=3.0.3 is the latest)
    ],
});
