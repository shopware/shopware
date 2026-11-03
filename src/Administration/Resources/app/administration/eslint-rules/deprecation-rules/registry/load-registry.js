/**
 * @sw-package framework
 */

const path = require('path');

process.env.TS_NODE_COMPILER_OPTIONS = JSON.stringify({
    module: 'CommonJS',
    moduleResolution: 'node',
});

require('ts-node/register/transpile-only');

function getRegistryPath() {
    return path.resolve(__dirname, '../../..', 'src/app/deprecation-registry/index.ts');
}

// One registry per ESLint process; the definitions are static and transpiling index.ts per rule and file is slow.
let cachedRegistry = null;

/**
 * Loads the TypeScript deprecation registry (src/app/deprecation-registry) into the ESLint process.
 * Rules call it in `create()`, so a disabled rule never pays the transpile.
 *
 * @example
 * const { componentApiMigrations } = loadRegistry();
 */
function loadRegistry() {
    if (cachedRegistry) {
        return cachedRegistry;
    }

    cachedRegistry = require(getRegistryPath());

    return cachedRegistry;
}

module.exports = {
    loadRegistry,
};
