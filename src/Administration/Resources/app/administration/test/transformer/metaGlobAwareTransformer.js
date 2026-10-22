/**
 * @sw-package framework
 *
 * Transforms JS/TS with SWC, except files that use Vite's `import.meta.glob`:
 * only the Babel plugin `babel-plugin-shopware-vite-meta-glob` rewrites it, and SWC is faster for the rest.
 */
const crypto = require('crypto');
const babelJest = require('babel-jest').default;
const swcJest = require('@swc/jest');

/**
 * @typedef {object} MetaGlobAwareTransformerConfig
 * @property {object} babel - babel-jest options for files that use `import.meta.glob`
 * @property {object} swc - @swc/jest options for every other file
 */

/**
 * The Jest transformer contract, narrowed to what this file implements.
 *
 * @typedef {object} MetaGlobAwareTransformer
 * @property {boolean} canInstrument
 * @property {(source: string, filename: string, options: object) => { code: string }} process
 * @property {(source: string, filename: string, options: object) => Promise<{ code: string }>} processAsync
 * @property {(source: string, filename: string, options: object) => string} getCacheKey
 */

/**
 * Whether `source` needs the Babel transform.
 * A mention in a comment or string only costs the slower Babel path.
 *
 * @example
 * usesMetaGlob("const modules = import.meta.glob('./*.ts');"); // → true
 *
 * @param {string} source
 * @returns {boolean}
 */
function usesMetaGlob(source) {
    return source.includes('import.meta.glob');
}

/**
 * Creates the transformer from the options of its Jest `transform` entry.
 *
 * Reports `canInstrument: false`, so Jest instruments the output of both paths the same way for coverage.
 * babel-jest gets `instrument: false`, because it would instrument on its own as well.
 *
 * @example
 * transform: { '^.+\\.[jt]sx?$': [require.resolve('./metaGlobAwareTransformer.js'), { babel: {...}, swc: {...} }] }
 *
 * @param {MetaGlobAwareTransformerConfig} config
 * @returns {MetaGlobAwareTransformer}
 */
function createTransformer({ babel, swc }) {
    const babelTransformer = babelJest.createTransformer(babel);
    const swcTransformer = swcJest.createTransformer(swc);

    /**
     * The delegate for `source`, with the options to hand it.
     *
     * @example
     * pick('export default 1;', options); // → { name: 'swc', transformer: swcTransformer, options }
     *
     * @param {string} source
     * @param {{ instrument?: boolean }} options
     */
    function pick(source, options) {
        if (usesMetaGlob(source)) {
            return { name: 'babel', transformer: babelTransformer, options: { ...options, instrument: false } };
        }

        return { name: 'swc', transformer: swcTransformer, options };
    }

    return {
        canInstrument: false,

        process(source, filename, options) {
            const picked = pick(source, options);

            return picked.transformer.process(source, filename, picked.options);
        },

        processAsync(source, filename, options) {
            const picked = pick(source, options);

            return picked.transformer.processAsync(source, filename, picked.options);
        },

        /**
         * Jest keys its transform cache by this value alone,
         * so it names the delegate and keeps the instrument flag that babel-jest no longer sees.
         */
        getCacheKey(source, filename, options) {
            const picked = pick(source, options);

            return crypto
                .createHash('sha256')
                .update(picked.name)
                .update('\0')
                .update(picked.transformer.getCacheKey(source, filename, picked.options))
                .update('\0')
                .update(options.instrument ? 'instrument' : '')
                .digest('hex');
        },
    };
}

module.exports = { createTransformer };
