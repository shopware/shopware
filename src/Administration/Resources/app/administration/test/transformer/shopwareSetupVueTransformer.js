/**
 * @sw-package framework
 */

const vueJest = require('@vue/vue3-jest');
const {
    transformShopwareSetupSfc,
    ShopwareSetupInternalError,
    ShopwareSetupTransformError,
} = require('../../build/vue-setup-transform');

/**
 * @typedef {object} JestTransformerConfig
 */

/**
 * Whether a file belongs to an installed dependency.
 *
 * Mirrors the Vite plugin's guard. Needed despite Jest's default `transformIgnorePatterns`, because
 * the Jest config un-ignores `@shopware-ag/meteor-component-library`, which ships Options-API `.vue` files.
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

    try {
        const result = transformShopwareSetupSfc(source, filename);

        return result?.code ?? source;
    } catch (error) {
        if (error instanceof ShopwareSetupTransformError && error.loc) {
            const { file, line, column } = error.loc;
            // An analyzer bug keeps the frames below the original `name: message` stack header, since its
            // fix goes where it was thrown. For an author error they only point into the transform.
            const frames =
                error instanceof ShopwareSetupInternalError
                    ? error.stack.slice(`${error.name}: ${error.message}`.length)
                    : '';

            // Jest ignores loc/frame and prints the stack; display columns are 1-based.
            error.message = `${error.message}\n\n${file}:${line}:${column + 1}\n${error.frame}`;
            error.stack = `${error.name}: ${error.message}${frames}`;
        }

        throw error;
    }
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
