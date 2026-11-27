/**
 * @sw-package framework
 *
 * Texts of the `vite-8` codemod. 6.8 builds the Administration and the Administration code of extensions with Vite 8
 * (Rolldown and Oxc instead of Rollup and esbuild). Extensions with their own `vite.config.*` must migrate it, and the
 * default import of a CommonJS module may resolve to a different value. See "Administration build updated to Vite 8"
 * in `UPGRADE-6.8.md`.
 */

module.exports = {
    id: 'vite-8',
    description: 'Marks the own Vite config of an extension and default imports of CommonJS-only packages for Vite 8',
    configFile: /^vite\.config\.(js|mjs|cjs|ts|mts|cts)$/,
    configMessage:
        'Shopware 6.8 builds Administration code with Vite 8 (Rolldown and Oxc instead of Rollup and esbuild). ' +
        'If this config or its plugins take part in that build, migrate them per the Vite 7 and Vite 8 migration guides ' +
        '(https://v7.vite.dev/guide/migration, https://vite.dev/guide/migration) and test them with ADMIN_VITE_8=1 on 6.7. ' +
        'Vite 8 needs Node.js ^20.19.0 || >=22.12.0.',
    commonJsMessage:
        'Vite 8 changes the default import of CommonJS modules: it is module.exports if the importer is .mjs/.mts, ' +
        'its package.json sets "type": "module" or the module does not set __esModule, otherwise module.exports.default. ' +
        'Check this import.',
    /** Specifiers that never resolve to a package of `node_modules`. */
    ignoredSpecifiers: /^(\.|\/|src\/|~|[a-z][a-z0-9+.-]*:)/,
    /** Subpaths of a package that are not JavaScript modules, e.g. `pkg/style.css`. */
    assetSubpath: /\.(?!(js|cjs|mjs|jsx|ts|cts|mts|tsx)$)[a-z0-9]+$/i,
};
