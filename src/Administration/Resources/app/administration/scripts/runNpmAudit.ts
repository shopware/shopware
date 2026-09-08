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
        'https://github.com/advisories/GHSA-cp6q-959q-f8rh', // @tiptap/core mergeAttributes prototype pollution, moderate; pulled in transitively by @shopware-ag/meteor-component-library, which still depends on @tiptap ^2.22.3 in its latest release (5.7.1). No fix reachable from here without forcing a tiptap v2 to v3 major upgrade inside that library.
        'https://github.com/advisories/GHSA-jmr9-qjv8-65gv', // extract-zip symlink traversal via Puppeteer browser downloads, devDep only; fixed Puppeteer requires Node 22.12+ while this package still supports Node 20
    ],
});
