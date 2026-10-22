/**
 * @sw-package framework
 */

import path from 'path';
import { createTransformer } from './metaGlobAwareTransformer';

const administrationRoot = path.resolve(__dirname, '../..');
const filename = path.join(__dirname, 'fixture.ts');

const transformer = createTransformer({
    babel: {
        presets: [
            require.resolve('@babel/preset-typescript'),
            [
                require.resolve('@babel/preset-env'),
                { targets: { node: 'current' } },
            ],
        ],
        plugins: [require.resolve('babel-plugin-shopware-vite-meta-glob')],
    },
    swc: { jsc: { parser: { syntax: 'typescript', decorators: true }, target: 'es2021' } },
});

/** The subset of Jest's transform options that babel-jest and @swc/jest read. */
function transformOptions(instrument = false) {
    return {
        config: { cwd: administrationRoot, rootDir: administrationRoot },
        configString: '{}',
        cacheFS: new Map<string, string>(),
        instrument,
        supportsDynamicImport: false,
        supportsExportNamespaceFrom: false,
        supportsStaticESM: false,
        supportsTopLevelAwait: false,
        transformerConfig: {},
    };
}

const globSource =
    "import foo from './foo';\nexport const modules = import.meta.glob('./*Transformer.js');\nexport default foo;\n";
const plainSource = "import foo from './foo';\nexport default foo;\n";

describe('test/transformer/metaGlobAwareTransformer', () => {
    it('rewrites import.meta.glob with the Babel transform', () => {
        const { code } = transformer.process(globSource, filename, transformOptions());

        expect(code).not.toContain('import.meta.glob');
        expect(code).toContain('./svgStringifyTransformer.js');
        expect(code).toContain('_interopRequireDefault');
    });

    it('transforms a file without import.meta.glob with SWC', () => {
        const { code } = transformer.process(plainSource, filename, transformOptions());

        expect(code).toContain('_interop_require_default');
        expect(code).not.toContain('_interopRequireDefault');
    });

    it.each([
        ['Babel', globSource],
        ['SWC', plainSource],
    ])('leaves the coverage instrumentation of the %s output to Jest', (_name, source) => {
        const { code } = transformer.process(source, filename, transformOptions(true));

        expect(code).not.toContain('cov_');
    });

    it('keys the cache by delegate, source and instrument flag', () => {
        const keys = [
            transformer.getCacheKey(globSource, filename, transformOptions()),
            transformer.getCacheKey(globSource, filename, transformOptions(true)),
            transformer.getCacheKey(plainSource, filename, transformOptions()),
            transformer.getCacheKey(plainSource, filename, transformOptions(true)),
        ];

        expect(new Set(keys).size).toBe(keys.length);
        expect(transformer.getCacheKey(globSource, filename, transformOptions())).toBe(keys[0]);
    });

    it('lets Jest instrument the output of both delegates', () => {
        expect(transformer.canInstrument).toBe(false);
    });
});
