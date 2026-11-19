/**
 * @sw-package framework
 */

import fs from 'fs';
import os from 'os';
import path from 'path';
import { createTransformer } from './metaGlobAwareTransformer';

const administrationRoot = path.resolve(__dirname, '../..');
const filename = path.join(__dirname, 'fixture.ts');

const transformerConfig = {
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
};

const transformer = createTransformer(transformerConfig);

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
    "import foo from './foo';\nexport const modules = import.meta.glob('./modules/*.ts');\nexport default foo;\n";
const plainSource = "import foo from './foo';\nexport default foo;\n";

describe('test/transformer/metaGlobAwareTransformer', () => {
    let globFixture: string;

    beforeAll(() => {
        globFixture = fs.mkdtempSync(path.join(os.tmpdir(), 'sw-meta-glob-fixture-'));
        fs.mkdirSync(path.join(globFixture, 'modules'));
        fs.writeFileSync(path.join(globFixture, 'modules', 'first.ts'), 'export default 1;');
        fs.writeFileSync(path.join(globFixture, 'modules', 'second.ts'), 'export default 2;');
    });

    afterAll(() => {
        fs.rmSync(globFixture, { recursive: true, force: true });
    });

    it('rewrites import.meta.glob with the Babel transform', () => {
        const { code } = transformer.process(globSource, path.join(globFixture, 'index.ts'), transformOptions());

        expect(code).not.toContain('import.meta.glob');
        expect(code).toContain('import("./modules/first.ts")');
        expect(code).toContain('import("./modules/second.ts")');
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

    it('keys the cache by its own code, so a change to how it picks a delegate invalidates cached output', () => {
        const transformerFile = require.resolve('./metaGlobAwareTransformer');
        const dependencyFile = path.join(administrationRoot, 'node_modules', 'some-package', 'index.js');
        const readFileSync = fs.readFileSync;
        const readSpy = jest.spyOn(fs, 'readFileSync').mockImplementation(((
            file: fs.PathOrFileDescriptor,
            options?: Parameters<typeof fs.readFileSync>[1],
        ) => {
            const content = readFileSync(file, options);

            return file === transformerFile ? `${String(content)}\n// changed` : content;
        }) as typeof fs.readFileSync);
        let changedTransformer = transformer;
        jest.isolateModules(() => {
            changedTransformer = jest
                .requireActual<{ createTransformer: typeof createTransformer }>('./metaGlobAwareTransformer')
                .createTransformer(transformerConfig);
        });
        readSpy.mockRestore();

        [
            [plainSource, filename],
            ['module.exports = 1;\n', dependencyFile],
        ].forEach(([source, file]) => {
            expect(changedTransformer.getCacheKey(source, file, transformOptions())).not.toBe(
                transformer.getCacheKey(source, file, transformOptions()),
            );
        });
    });

    it('lets Jest instrument the output of both delegates', () => {
        expect(transformer.canInstrument).toBe(false);
    });

    describe('in node_modules', () => {
        let nodeModules: string;

        /** Writes `files` into `node_modules/<name>` and returns the path of its `index.js`. */
        function installPackage(name: string, files: Record<string, string>): string {
            Object.entries(files).forEach(([file, content]) => {
                fs.mkdirSync(path.dirname(path.join(nodeModules, name, file)), { recursive: true });
                fs.writeFileSync(path.join(nodeModules, name, file), content);
            });

            return path.join(nodeModules, name, 'index.js');
        }

        beforeAll(() => {
            nodeModules = path.join(fs.mkdtempSync(path.join(os.tmpdir(), 'sw-script-transformer-')), 'node_modules');
        });

        afterAll(() => {
            fs.rmSync(path.dirname(nodeModules), { recursive: true, force: true });
        });

        it('converts a package of "type": "module" to CommonJS', () => {
            const file = installPackage('esm-package', {
                'package.json': '{ "type": "module" }',
                'index.js': 'export default 1;',
            });

            expect(transformer.process('export default 1;', file, transformOptions()).code).toContain('exports');
        });

        it('converts an ES module of a package without "type", such as its "module" build', () => {
            const source = "import foo from './foo';\nexport { foo };\n";
            const file = installPackage('dual-package', { 'package.json': '{ "main": "index.js" }', 'index.js': source });

            expect(transformer.process(source, file, transformOptions()).code).toContain('_interop_require_default');
        });

        it('parses the JavaScript of a dependency as JavaScript, not as TypeScript generics', () => {
            const source = 'export const compare = (f, a, b, c, d) => f(a < b, c > (d));\n';
            const file = installPackage('js-package', { 'package.json': '{ "type": "module" }', 'index.js': source });

            expect(transformer.process(source, file, transformOptions()).code).toContain('f(a < b, c > d)');
        });

        it.each([
            ['plain CommonJS', 'module.exports = 1;\n'],
            [
                'CommonJS with a dynamic import()',
                "module.exports = () => import('./lazy');\n",
            ],
            [
                'CommonJS with import and export statements inside a template literal',
                'module.exports = `\nimport x from "x";\nexport { x };\n`;\n',
            ],
            ['an exports assignment', 'exports.foo = 1;\n'],
        ])('passes %s through unchanged', (_name, source) => {
            const file = installPackage('cjs-package', { 'package.json': '{}', 'index.js': source });

            expect(transformer.process(source, file, transformOptions()).code).toBe(source);
        });

        it('passes a file of a "type": "commonjs" package through, even under a "type": "module" root', () => {
            installPackage('mixed-package', { 'package.json': '{ "type": "module" }' });
            const file = installPackage('mixed-package/cjs', {
                'package.json': '{ "type": "commonjs" }',
                'index.js': 'module.exports = 1;',
            });

            expect(transformer.process('module.exports = 1;', file, transformOptions()).code).toBe('module.exports = 1;');
        });

        it('keys a passed-through file apart from the same source transformed outside node_modules', () => {
            const source = 'module.exports = 1;\n';
            const file = installPackage('cache-package', { 'package.json': '{}', 'index.js': source });

            expect(transformer.getCacheKey(source, file, transformOptions())).not.toBe(
                transformer.getCacheKey(source, filename, transformOptions()),
            );
        });
    });
});
