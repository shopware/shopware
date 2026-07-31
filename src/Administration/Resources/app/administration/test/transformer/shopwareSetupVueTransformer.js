/**
 * @sw-package framework
 */

const vueJest = require('@vue/vue3-jest');
const { transformShopwareSetupSfc } = require('../../build/vue-setup-transform');

/**
 * @typedef {object} JestTransformerConfig
 */

/**
 * Whether a file belongs to an installed dependency.
 *
 * Mirrors the Vite plugin's guard. Needed despite Jest's default `transformIgnorePatterns`, because
 * jest.config.ts un-ignores `@shopware-ag/meteor-component-library`, which ships Options-API `.vue` files.
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
