/**
 * @sw-package framework
 */

const vueJest = require('@vue/vue3-jest');
const { transformShopwareSetupSfc } = require('../../build/vue-setup-transform');

/**
 * @typedef {object} JestTransformerConfig
 */

/**
 * Whether a file belongs to an installed dependency rather than to authored source.
 *
 * Mirrors the Vite plugin's guard, and it is needed for the same reason: a missing `<script setup>` is a
 * hard error, and nobody can add `swDefinePublic()` to a package they do not own. Jest's default
 * `transformIgnorePatterns` would keep dependencies away from this transformer, but jest.config.ts
 * deliberately un-ignores `@shopware-ag/meteor-component-library`, which ships Options-API `.vue`
 * sources - so without this, resolving one of them would be an unfixable test failure.
 *
 * @param {string} filename
 * @returns {boolean}
 */
function isDependencyFile(filename) {
    return filename.replace(/\\/g, '/').includes('/node_modules/');
}

/**
 * Applies the shared pre-Vue transform before delegating to vue-jest.
 *
 * @param {string} source
 * @param {string} filename
 * @returns {string}
 */
function transformSource(source, filename) {
    if (isDependencyFile(filename)) {
        return source;
    }

    const result = transformShopwareSetupSfc(source, filename);

    return result?.code ?? source;
}

module.exports = {
    /**
     * Feeds vue-jest transformed code so tests exercise the same input shape as Vite.
     *
     * @param {string} source
     * @param {string} filename
     * @param {JestTransformerConfig} config
     * @param {unknown} transformOptions
     * @returns {unknown}
     */
    process(source, filename, config, transformOptions) {
        return vueJest.process(transformSource(source, filename), filename, config, transformOptions);
    },

    /**
     * Mirrors the source transform for Jest cache keys to avoid stale compiled SFCs.
     *
     * @param {string} source
     * @param {string} filename
     * @param {unknown} options
     * @returns {string}
     */
    getCacheKey(source, filename, options) {
        return vueJest.getCacheKey(transformSource(source, filename), filename, options);
    },
};
