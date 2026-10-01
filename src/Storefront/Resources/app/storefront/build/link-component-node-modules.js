/**
 * Creates a `node_modules` symlink inside `Resources/views/components/` that
 * points at `Resources/app/storefront/node_modules/`.
 *
 * Why this is needed:
 *  - Component sources live under `Resources/views/components/` while their
 *    npm dependencies are installed into the sibling
 *    `Resources/app/storefront/node_modules/`.
 *  - Node's standard upward-scan module resolution never visits a sibling
 *    directory, so IDEs and `tsc` cannot resolve bare specifiers like
 *    `@shopware-ag/dive/quickview` from a component file.
 *  - The Vite build already bridges this via
 *    `extensionNodeModulesPlugin` / `scopedSubpathExportsPlugin`; this
 *    symlink gives the same bridge to tooling that has no Vite plugin
 *    hook (IDEs, tsc, vitest when run from the component directory, …).
 *
 * The symlink is re-created on every `npm install` via the storefront's
 * `postinstall` hook so it survives a fresh checkout or a `rm -rf
 * node_modules`.  Any existing entry at the target path is removed first so
 * a broken/outdated symlink — or a real directory left behind by a previous
 * experiment — gets replaced cleanly.
 */

'use strict';

const fs = require('node:fs');
const path = require('node:path');

const scriptDir = __dirname;
// scriptDir = Resources/app/storefront/build
const storefrontAppDir = path.resolve(scriptDir, '..');
const componentsDir = path.resolve(storefrontAppDir, '../../views/components');
const linkPath = path.join(componentsDir, 'node_modules');
const linkTarget = path.relative(componentsDir, path.join(storefrontAppDir, 'node_modules'));

if (!fs.existsSync(componentsDir)) {
    // Nothing to link against — the components directory does not exist in
    // this checkout (minimal distribution, test fixture, …).
    process.exit(0);
}

try {
    fs.rmSync(linkPath, { recursive: true, force: true });
} catch (err) {
     
    console.warn(`[link-component-node-modules] could not remove existing ${linkPath}: ${err.message}`);
}

try {
    // `junction` is a no-op on POSIX (same as `dir`) but avoids the Windows
    // "elevated privileges required" trap for directory symlinks.
    fs.symlinkSync(linkTarget, linkPath, 'junction');
    // eslint-disable-next-line no-console
    console.log(`[link-component-node-modules] linked ${linkPath} -> ${linkTarget}`);
} catch (err) {
     
    console.warn(`[link-component-node-modules] could not create symlink ${linkPath} -> ${linkTarget}: ${err.message}`);
    process.exit(0);
}
