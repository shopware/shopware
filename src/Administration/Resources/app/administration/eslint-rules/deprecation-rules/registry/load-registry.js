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
    return path.resolve(
        __dirname,
        '../../..',
        'src/app/deprecation-registry/index.ts',
    );
}

function loadRegistry() {
    const registryPath = getRegistryPath();
    delete require.cache[require.resolve(registryPath)];

    const {
        componentApiMigrations,
        globalApiMigrations,
        jsApiMigrations,
        assetMigrations,
        templateBlockMigrations,
        templateEventMigrations,
        snippetKeyMigrations,
        packageMigrations,
    } = require(registryPath);

    return {
        componentApiMigrations,
        globalApiMigrations,
        jsApiMigrations,
        assetMigrations,
        templateBlockMigrations,
        templateEventMigrations,
        snippetKeyMigrations,
        packageMigrations,
        getComponentApiMigration(componentName) {
            return componentApiMigrations.find((migration) => migration.component === componentName) ?? null;
        },
    };
}

module.exports = {
    loadRegistry,
};
