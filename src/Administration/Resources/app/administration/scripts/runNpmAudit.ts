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
        'https://github.com/advisories/GHSA-pjwm-pj3p-43mv', // axios v0 proxy bypass, legacy admin HTTP client kept for extension compatibility until v6.8 axios v1 migration
        'https://github.com/advisories/GHSA-898c-q2cr-xwhg', // axios v0 prototype pollution gadgets, legacy admin HTTP client kept for extension compatibility until v6.8 axios v1 migration
        'https://github.com/advisories/GHSA-hfxv-24rg-xrqf', // axios v0 ReDoS, legacy admin HTTP client kept for extension compatibility until v6.8 axios v1 migration
        'https://github.com/advisories/GHSA-p92q-9vqr-4j8v', // axios v0 proxy authorization leak, legacy admin HTTP client kept for extension compatibility until v6.8 axios v1 migration
        'https://github.com/advisories/GHSA-j5f8-grm9-p9fc', // axios v0 proxy authorization leak, legacy admin HTTP client kept for extension compatibility until v6.8 axios v1 migration
        'https://github.com/advisories/GHSA-42h9-826w-cgv3', // axios v0 formDataToJSON DoS, legacy admin HTTP client kept for extension compatibility until v6.8 axios v1 migration
        'https://github.com/advisories/GHSA-pmv8-rq9r-6j72', // axios v0 formToJSON DoS, legacy admin HTTP client kept for extension compatibility until v6.8 axios v1 migration
        'https://github.com/advisories/GHSA-mmx7-hfxf-jppx', // axios v0 prototype pollution gadgets, legacy admin HTTP client kept for extension compatibility until v6.8 axios v1 migration
        'https://github.com/advisories/GHSA-f4gw-2p7v-4548', // axios v0 NO_PROXY bypass, legacy admin HTTP client kept for extension compatibility until v6.8 axios v1 migration
        'https://github.com/advisories/GHSA-gcfj-64vw-6mp9', // axios v0 inherited proxy use, legacy admin HTTP client kept for extension compatibility until v6.8 axios v1 migration
        'https://github.com/advisories/GHSA-hcpx-6fm6-wx23', // axios v0 form serializer maxDepth bypass, legacy admin HTTP client kept for extension compatibility until v6.8 axios v1 migration
        'https://github.com/advisories/GHSA-7q8q-rj6j-mhjq', // axios v0 nested option prototype pollution, legacy admin HTTP client kept for extension compatibility until v6.8 axios v1 migration
        'https://github.com/advisories/GHSA-jmr9-qjv8-65gv', // extract-zip symlink traversal via Puppeteer browser downloads, devDep only; fixed Puppeteer requires Node 22.12+ while this package still supports Node 20
    ],
});
