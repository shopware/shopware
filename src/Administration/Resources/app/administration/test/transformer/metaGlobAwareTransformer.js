/**
 * @sw-package framework
 *
 * Transforms JS/TS with SWC, except files that use Vite's `import.meta.glob`:
 * only the Babel plugin `babel-plugin-shopware-vite-meta-glob` rewrites it, and SWC is faster for the rest.
 * Files in `node_modules` pass through unchanged unless they are ES modules, which Jest cannot run as they are.
 */
const crypto = require('crypto');
const fs = require('fs');
const path = require('path');
const babelJest = require('babel-jest').default;
const swcJest = require('@swc/jest');
const esModuleLexer = require('es-module-lexer');
const { isDependencyFile } = require('./isDependencyFile');

esModuleLexer.initSync();

/**
 * Jest keys its transform cache by `getCacheKey` alone,
 * so every key carries the code that picks a delegate: this file, its dependency check and the lexer.
 */
const ROUTING_FINGERPRINT = digest(
    ...[
        __filename,
        require.resolve('./isDependencyFile'),
        require.resolve('es-module-lexer'),
    ].map((file) => fs.readFileSync(file, 'utf8')),
);

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

/** Package `type` by directory, so each worker reads a package.json only once. */
const packageTypeCache = new Map();

/**
 * The `type` field of the package.json nearest to `directory`, as Node resolves it.
 *
 * @example
 * nearestPackageType('/admin/node_modules/uuidv7/dist'); // → 'module'
 *
 * @param {string} directory
 * @returns {string | undefined}
 */
function nearestPackageType(directory) {
    if (packageTypeCache.has(directory)) {
        return packageTypeCache.get(directory);
    }

    const manifest = path.join(directory, 'package.json');
    const parent = path.dirname(directory);
    let type;

    if (fs.existsSync(manifest)) {
        try {
            type = JSON.parse(fs.readFileSync(manifest, 'utf8')).type ?? 'commonjs';
        } catch {
            type = 'commonjs';
        }
    } else if (parent !== directory) {
        type = nearestPackageType(parent);
    }

    packageTypeCache.set(directory, type);

    return type;
}

/**
 * Whether `source` has `import` or `export` statements or `import.meta`.
 * Unlike a regular expression, the lexer skips strings, template literals and comments.
 * A file it cannot lex counts as CommonJS.
 *
 * @example
 * hasModuleSyntax('export default 1;'); // → true
 * hasModuleSyntax("module.exports = () => import('./lazy');"); // → false
 *
 * @param {string} source
 * @returns {boolean}
 */
function hasModuleSyntax(source) {
    try {
        return esModuleLexer.parse(source)[3];
    } catch {
        return false;
    }
}

/**
 * Whether a `node_modules` file is an ES module: its package declares `"type": "module"`,
 * or its source has module syntax, as in the `module` build of a dual package.
 *
 * @example
 * isEsModule('export default 1;', '/admin/node_modules/foo/index.js'); // → true
 * isEsModule('module.exports = 1;', '/admin/node_modules/bar/index.js'); // → false
 *
 * @param {string} source
 * @param {string} filename
 * @returns {boolean}
 */
function isEsModule(source, filename) {
    return nearestPackageType(path.dirname(filename)) === 'module' || hasModuleSyntax(source);
}

/**
 * A SHA-256 over `parts`, separated so that adjacent parts cannot run into each other.
 *
 * @example
 * digest('swc', delegateKey, 'instrument'); // → '3f2a…'
 *
 * @param {...string} parts
 * @returns {string}
 */
function digest(...parts) {
    return crypto.createHash('sha256').update(parts.join('\0')).digest('hex');
}

/** Hands a CommonJS dependency to Jest as it is. */
const passThroughTransformer = {
    process: (source) => ({ code: source }),
    processAsync: async (source) => ({ code: source }),
};

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
    // The TypeScript parser reads plain JS such as `f(a < b, c > (d))` as a generic call and drops the comparison.
    const javascriptSwcTransformer = swcJest.createTransformer({
        ...swc,
        jsc: { ...swc.jsc, parser: { syntax: 'ecmascript' } },
    });

    /**
     * The SWC delegate for a dependency: published JavaScript gets the JavaScript parser,
     * published TypeScript keeps the configured one.
     *
     * @example
     * dependencySwcTransformer('/admin/node_modules/three/build/three.module.js'); // → javascriptSwcTransformer
     *
     * @param {string} filename
     */
    function dependencySwcTransformer(filename) {
        return /\.tsx?$/.test(filename) ? swcTransformer : javascriptSwcTransformer;
    }

    /**
     * The delegate for `source`, with the options to hand it.
     *
     * @example
     * pick('export default 1;', '/admin/src/app/main.ts', options); // → { name: 'swc', transformer: swcTransformer, options }
     *
     * @param {string} source
     * @param {string} filename
     * @param {{ instrument?: boolean }} options
     */
    function pick(source, filename, options) {
        if (isDependencyFile(filename)) {
            return isEsModule(source, filename)
                ? { name: 'swc', transformer: dependencySwcTransformer(filename), options }
                : { name: 'pass-through', transformer: passThroughTransformer, options };
        }

        if (usesMetaGlob(source)) {
            return { name: 'babel', transformer: babelTransformer, options: { ...options, instrument: false } };
        }

        return { name: 'swc', transformer: swcTransformer, options };
    }

    return {
        canInstrument: false,

        process(source, filename, options) {
            const picked = pick(source, filename, options);

            return picked.transformer.process(source, filename, picked.options);
        },

        processAsync(source, filename, options) {
            const picked = pick(source, filename, options);

            return picked.transformer.processAsync(source, filename, picked.options);
        },

        /**
         * Jest keys its transform cache by this value alone,
         * so it names the delegate and keeps the instrument flag that babel-jest no longer sees.
         * A dependency is keyed by the inputs of the ES module check instead,
         * so a run with a warm cache never lexes it.
         */
        getCacheKey(source, filename, options) {
            if (isDependencyFile(filename)) {
                return digest(
                    ROUTING_FINGERPRINT,
                    'dependency',
                    nearestPackageType(path.dirname(filename)) ?? '',
                    dependencySwcTransformer(filename).getCacheKey(source, filename, options),
                );
            }

            const picked = pick(source, filename, options);

            return digest(
                ROUTING_FINGERPRINT,
                picked.name,
                picked.transformer.getCacheKey(source, filename, picked.options),
                options.instrument ? 'instrument' : '',
            );
        },
    };
}

module.exports = { createTransformer };
