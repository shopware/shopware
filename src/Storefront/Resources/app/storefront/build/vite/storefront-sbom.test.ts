// @vitest-environment node
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { createHash } from 'node:crypto';
import { createRequire } from 'node:module';
import { execFile } from 'node:child_process';
import { promisify } from 'node:util';
import { pathToFileURL } from 'node:url';
import { afterAll, afterEach, beforeAll, beforeEach, describe, expect, it, vi } from 'vitest';
import { build } from 'vite';
import type { Plugin, UserConfig } from 'vite';
import type { RolldownWatcher, RolldownWatcherEvent } from 'rolldown';
import { Spec, Validation } from '@cyclonedx/cyclonedx-library';
import { storefrontSbomPlugin } from './storefront-sbom.mjs';

const require = createRequire(import.meta.url);
const { componentMapPlugin } = require('./build-components.js') as { componentMapPlugin: () => Plugin };

type Component = {
    'bom-ref': string;
    type: string;
    name: string;
    group?: string;
    version?: string;
    purl?: string;
    hashes?: { alg: string; content: string }[];
};

type Bom = {
    bomFormat: string;
    specVersion: string;
    metadata: {
        component: Component;
        properties: { name: string; value: string }[];
    };
    components: Component[];
    dependencies: { ref: string; dependsOn?: string[] }[];
};

function writeFiles(root: string, files: Record<string, string>): void {
    for (const [filename, contents] of Object.entries(files)) {
        const destination = path.join(root, filename);
        fs.mkdirSync(path.dirname(destination), { recursive: true });
        fs.writeFileSync(destination, contents);
    }
}

function npmPackage(name: string, version = '1.2.3'): string {
    return JSON.stringify({ name, version, type: 'module', main: './index.js', sideEffects: false, license: 'MIT' });
}

function readReport(bundleRoot: string, filename = 'components.cdx.json'): Bom {
    return JSON.parse(fs.readFileSync(path.join(bundleRoot, 'Resources/sbom/storefront', filename), 'utf8')) as Bom;
}

function packages(report: Bom): string[] {
    return report.components.filter(component => component.purl?.startsWith('pkg:npm/'))
        .map(component => `${component.group ? `${component.group}/` : ''}${component.name}@${component.version}`)
        .sort();
}

function fixtureConfig(bundleRoot: string, config: UserConfig = {}): UserConfig {
    const app = path.join(bundleRoot, 'Resources/app/storefront');
    return {
        configFile: false,
        root: app,
        logLevel: 'silent',
        ...config,
        plugins: [
            storefrontSbomPlugin({ bundleRoot, bundleName: path.basename(bundleRoot), target: 'components' }),
            ...config.plugins ?? [],
        ],
        build: {
            outDir: path.join(bundleRoot, 'Resources/public/storefront/components'),
            emptyOutDir: true,
            minify: false,
            ...config.build,
            rolldownOptions: {
                input: path.join(app, 'main.js'),
                external: ['shopware'],
                preserveEntrySignatures: 'exports-only',
                ...config.build?.rolldownOptions,
            },
        },
    };
}

async function buildFixture(bundleRoot: string, files: Record<string, string>, config: UserConfig = {}): Promise<Bom> {
    writeFiles(bundleRoot, { 'composer.json': JSON.stringify({ name: 'test/extension', version: '2.3.4' }) });
    writeFiles(path.join(bundleRoot, 'Resources/app/storefront'), {
        'package.json': JSON.stringify({ name: 'build-tooling', version: '1.0.0', private: true }),
        ...files,
    });
    await build(fixtureConfig(bundleRoot, config));
    return readReport(bundleRoot);
}

async function watchBuild(watcher: RolldownWatcher, change?: () => void, expected: 'END' | 'ERROR' = 'END'): Promise<void> {
    return new Promise((resolve, reject) => {
        const timeout = setTimeout(() => {
            watcher.off('event', listener);
            reject(new Error(`Timed out waiting for ${expected}`));
        }, 5000);
        const listener = (event: RolldownWatcherEvent): void => {
            if (event.code === expected || event.code === 'ERROR') {
                clearTimeout(timeout);
                watcher.off('event', listener);
                if (event.code === 'ERROR' && expected !== 'ERROR') {
                    reject(event.error);
                } else {
                    resolve();
                }
            }
        };
        watcher.on('event', listener);
        change?.();
    });
}

async function validate(report: Bom): Promise<void> {
    const validator = new Validation.JsonStrictValidator(Spec.Version.v1dot6);
    expect(await validator.validate(JSON.stringify(report))).toBeNull();
    const references = new Set([report.metadata.component['bom-ref'], ...report.components.map(component => component['bom-ref'])]);
    for (const dependency of report.dependencies) {
        expect(references.has(dependency.ref)).toBe(true);
        for (const reference of dependency.dependsOn ?? []) {
            expect(references.has(reference)).toBe(true);
        }
    }
}

describe('production Storefront SBOMs', () => {
    let fixtureRoot: string;

    beforeAll(() => {
        fixtureRoot = fs.mkdtempSync(path.join(os.tmpdir(), 'storefront-sbom-'));
    });

    beforeEach(() => {
        vi.stubEnv('NODE_ENV', 'production');
    });

    afterEach(() => {
        vi.unstubAllEnvs();
    });

    afterAll(() => {
        fs.rmSync(fixtureRoot, { recursive: true, force: true });
    });

    it('includes bundled dev and transitive packages, excludes discarded code, and records dependency relationships', async () => {
        const root = path.join(fixtureRoot, 'Dependencies');
        const report = await buildFixture(root, {
            'package.json': JSON.stringify({
                name: 'extension-build', version: '1.0.0', private: true,
                devDependencies: { 'dev-library': '1.2.3' },
                dependencies: { removed: '1.2.3', unused: '1.2.3' },
            }),
            'main.js': 'import { run } from "dev-library"; import { unused } from "removed"; export { run };',
            'node_modules/dev-library/package.json': npmPackage('dev-library'),
            'node_modules/dev-library/index.js': 'import { deep } from "transitive"; export const run = () => deep();',
            'node_modules/transitive/package.json': npmPackage('transitive'),
            'node_modules/transitive/index.js': 'import { deep } from "deeper"; export { deep };',
            'node_modules/deeper/package.json': npmPackage('deeper'),
            'node_modules/deeper/index.js': 'export const deep = () => Math.random();',
            'node_modules/removed/package.json': npmPackage('removed'),
            'node_modules/removed/index.js': 'export const unused = () => "removed-package-marker";',
            'node_modules/unused/package.json': npmPackage('unused'),
            'node_modules/unused/index.js': 'export const unused = true;',
        });

        expect(packages(report)).toEqual(['deeper@1.2.3', 'dev-library@1.2.3']);
        const library = report.components.find(component => component.name === 'dev-library')!;
        const deeper = report.components.find(component => component.name === 'deeper')!;
        expect(report.dependencies).toContainEqual({ ref: library['bom-ref'], dependsOn: [deeper['bom-ref']] });
        expect(report.dependencies.find(dependency => dependency.ref === report.metadata.component['bom-ref'])?.dependsOn)
            .toContain(library['bom-ref']);
        expect(report.metadata.component.name).toBe('Dependencies/components');
        expect(report.metadata.component.version).toBe('2.3.4');
        await validate(report);
    });

    it('includes dynamically loaded packages and distinct nested versions', async () => {
        const report = await buildFixture(path.join(fixtureRoot, 'Dynamic'), {
            'main.js': 'import { run } from "direct"; export { run }; export const lazy = () => import("nested");',
            'node_modules/direct/package.json': npmPackage('direct'),
            'node_modules/direct/index.js': 'import { run as nested } from "nested"; export const run = () => nested() + Date.now();',
            'node_modules/direct/node_modules/nested/package.json': npmPackage('nested', '2.0.0'),
            'node_modules/direct/node_modules/nested/index.js': 'export const run = () => Math.random();',
            'node_modules/nested/package.json': npmPackage('nested', '3.0.0'),
            'node_modules/nested/index.js': 'export const dynamic = () => Math.random();',
        });

        expect(packages(report)).toEqual(['direct@1.2.3', 'nested@2.0.0', 'nested@3.0.0']);
        expect(report.components.filter(component => component.type === 'file').length).toBeGreaterThan(1);
        await validate(report);
    });

    it.each([
        ['worker-url', 'export const start = () => new Worker(new URL("./worker.js", import.meta.url), { type: "module" });', false],
        ['sharedworker-url', 'export const start = () => new SharedWorker(new URL("./worker.js", import.meta.url), { type: "module" });', false],
        ['worker-import', 'import Background from "./worker.js?worker"; export const start = () => new Background();', false],
        ['sharedworker-import', 'import Background from "./worker.js?sharedworker"; export const start = () => new Background();', false],
        ['inline-worker', 'import Background from "./worker.js?worker&inline"; export const start = () => new Background();', true],
        ['inline-sharedworker', 'import Background from "./worker.js?sharedworker&inline"; export const start = () => new Background();', true],
    ])('includes retained dependencies and actual file hashes for %s', async (name, entry, inline) => {
        const root = path.join(fixtureRoot, name);
        const report = await buildFixture(root, {
            'package.json': JSON.stringify({ name: 'worker-build', private: true, devDependencies: { 'worker-library': '4.5.6' } }),
            'main.js': entry,
            'worker.js': 'import { run } from "worker-library"; self.onmessage = () => self.postMessage(run());',
            'node_modules/worker-library/package.json': npmPackage('worker-library', '4.5.6'),
            'node_modules/worker-library/index.js': 'import { leaf } from "worker-leaf"; import { unused } from "removed"; export const run = () => "WORKER_VENDOR_MARKER" + leaf();',
            'node_modules/worker-leaf/package.json': npmPackage('worker-leaf'),
            'node_modules/worker-leaf/index.js': 'export const leaf = () => Date.now();',
            'node_modules/removed/package.json': npmPackage('removed'),
            'node_modules/removed/index.js': 'export const unused = () => "REMOVED_WORKER_MARKER";',
        });

        expect(packages(report)).toEqual(['worker-leaf@1.2.3', 'worker-library@4.5.6']);
        const output = path.join(root, 'Resources/public/storefront/components');
        const files = report.components.filter(component => component.type === 'file');
        const actualFiles = fs.readdirSync(output, { recursive: true, encoding: 'utf8' }).filter(file => file.endsWith('.js'));
        expect(files.map(file => file.name).sort()).toEqual(actualFiles.sort());
        expect(files).toHaveLength(inline ? 1 : 2);
        const library = report.components.find(component => component.name === 'worker-library')!;
        const leaf = report.components.find(component => component.name === 'worker-leaf')!;
        expect(report.dependencies).toContainEqual({ ref: library['bom-ref'], dependsOn: [leaf['bom-ref']] });
        for (const file of files) {
            const contents = fs.readFileSync(path.join(output, file.name));
            expect(file.hashes).toEqual([{ alg: 'SHA-256', content: createHash('sha256').update(contents).digest('hex') }]);
            expect(contents.toString()).not.toContain('REMOVED_WORKER_MARKER');
            if (contents.toString().includes('WORKER_VENDOR_MARKER')) {
                expect(report.dependencies.find(dependency => dependency.ref === file['bom-ref'])?.dependsOn).toContain(library['bom-ref']);
            }
        }
        expect(actualFiles.some(file => fs.readFileSync(path.join(output, file), 'utf8').includes('WORKER_VENDOR_MARKER'))).toBe(true);
        await validate(report);
    });

    it.each([false, true])('preserves custom plugins, nested workers and dynamic imports with an inline outer worker: %s', async (inline) => {
        const root = path.join(fixtureRoot, `NestedWorkers-${inline}`);
        const report = await buildFixture(root, {
            'main.js': inline
                ? 'import Background from "./worker.js?worker&inline"; export const start = () => new Background();'
                : 'export const start = () => new Worker(new URL("./worker.js", import.meta.url), { type: "module" });',
            'worker.js': 'import Nested from "./nested.js?worker&inline"; self.onmessage = async () => { new Nested(); self.postMessage("WORKER_PLUGIN_MARKER" + (await import("lazy-library")).run()); };',
            'nested.js': 'import { run } from "nested-library"; self.onmessage = () => self.postMessage(run());',
            'node_modules/lazy-library/package.json': npmPackage('lazy-library'),
            'node_modules/lazy-library/index.js': 'export const run = () => "LAZY_WORKER_MARKER" + Date.now();',
            'node_modules/nested-library/package.json': npmPackage('nested-library'),
            'node_modules/nested-library/index.js': 'export const run = () => "NESTED_WORKER_MARKER" + Date.now();',
        }, {
            worker: {
                format: 'es',
                plugins: () => [{
                    name: 'custom-worker-transform',
                    transform(code, id) {
                        return id.endsWith('/worker.js') ? code.replace('WORKER_PLUGIN_MARKER', 'CUSTOM_WORKER_PLUGIN_APPLIED') : null;
                    },
                }],
            },
        });

        expect(packages(report)).toEqual(['lazy-library@1.2.3', 'nested-library@1.2.3']);
        const output = path.join(root, 'Resources/public/storefront/components');
        const files = report.components.filter(component => component.type === 'file');
        const actualFiles = fs.readdirSync(output, { recursive: true, encoding: 'utf8' }).filter(file => file.endsWith('.js'));
        expect(files.map(file => file.name).sort()).toEqual(actualFiles.sort());
        const contents = actualFiles.map(file => fs.readFileSync(path.join(output, file), 'utf8')).join('\n');
        expect(contents).toContain('CUSTOM_WORKER_PLUGIN_APPLIED');
        expect(contents).toContain('LAZY_WORKER_MARKER');
        expect(contents).toContain('NESTED_WORKER_MARKER');
        for (const file of files) {
            const code = fs.readFileSync(path.join(output, file.name));
            expect(file.hashes).toEqual([{ alg: 'SHA-256', content: createHash('sha256').update(code).digest('hex') }]);
            const dependencies = report.dependencies.find(dependency => dependency.ref === file['bom-ref'])?.dependsOn ?? [];
            for (const [name, marker] of [['lazy-library', 'LAZY_WORKER_MARKER'], ['nested-library', 'NESTED_WORKER_MARKER']]) {
                const component = report.components.find(component => component.name === name)!;
                expect(dependencies.includes(component['bom-ref'])).toBe(code.toString().includes(marker));
            }
        }
        await validate(report);
    });

    it('preserves scoped packages and multiple levels of retained dependencies', async () => {
        const report = await buildFixture(path.join(fixtureRoot, 'DeepGraph'), {
            'main.js': 'import { run } from "@scope/direct"; export { run };',
            'node_modules/@scope/direct/package.json': npmPackage('@scope/direct', '4.5.6'),
            'node_modules/@scope/direct/index.js': 'import { middle } from "middle"; export const run = () => middle() + 1;',
            'node_modules/middle/package.json': npmPackage('middle'),
            'node_modules/middle/index.js': 'import { leaf } from "leaf"; export const middle = () => leaf() + 1;',
            'node_modules/leaf/package.json': npmPackage('leaf'),
            'node_modules/leaf/index.js': 'import { deepest } from "deepest"; export const leaf = () => deepest() + 1;',
            'node_modules/deepest/package.json': npmPackage('deepest'),
            'node_modules/deepest/index.js': 'export const deepest = () => Date.now();',
        });

        expect(packages(report)).toEqual(['@scope/direct@4.5.6', 'deepest@1.2.3', 'leaf@1.2.3', 'middle@1.2.3']);
        for (const [owner, dependency] of [['direct', 'middle'], ['middle', 'leaf'], ['leaf', 'deepest']]) {
            const from = report.components.find(component => component.name === owner)!;
            const to = report.components.find(component => component.name === dependency)!;
            expect(report.dependencies).toContainEqual({ ref: from['bom-ref'], dependsOn: [to['bom-ref']] });
        }
        await validate(report);
    });

    it('records generated module provenance gaps and excludes copied JavaScript assets from the chunk inventory', async () => {
        const generated: Plugin = {
            name: 'fixture-generated-module',
            resolveId(id) {
                return id === 'virtual:fixture' ? '\0fixture' : null;
            },
            load(id) {
                return id === '\0fixture' ? 'export const run = () => Math.random();' : null;
            },
            generateBundle() {
                this.emitFile({ type: 'asset', fileName: 'copied.js', source: 'console.log("copied asset");' });
            },
        };
        const report = await buildFixture(path.join(fixtureRoot, 'Generated'), {
            'main.js': 'import { run } from "virtual:fixture"; export { run };',
        }, { plugins: [generated] });

        expect(report.metadata.properties).toContainEqual({ name: 'shopware:sbom:virtual-module', value: 'virtual:fixture' });
        expect(report.components.some(component => component.name === 'copied.js')).toBe(false);
        expect(packages(report)).toEqual([]);
        await validate(report);
    });

    it('keeps bundled vendors after import-map rewriting and identifies external shopware separately', async () => {
        const root = path.join(fixtureRoot, 'ImportMap');
        const app = path.join(root, 'Resources/app/storefront');
        const report = await buildFixture(root, {
            'main.js': 'import { Shopware } from "shopware"; import { run } from "vendor-library"; export const main = () => run(Shopware);',
            'second.js': 'import { run } from "vendor-library"; export const second = () => run();',
            'node_modules/vendor-library/package.json': npmPackage('vendor-library'),
            'node_modules/vendor-library/index.js': 'export const run = (value) => console.log(value);',
        }, {
            plugins: [componentMapPlugin()],
            build: {
                manifest: true,
                rolldownOptions: {
                    input: { main: path.join(app, 'main.js'), second: path.join(app, 'second.js') },
                    output: {
                        chunkFileNames: 'vendor/[name]-[hash].js',
                        codeSplitting: { groups: [{ name: 'vendor', test: /node_modules/ }] },
                    },
                },
            },
        });

        expect(packages(report)).toEqual(['vendor-library@1.2.3']);
        expect(report.metadata.properties).toContainEqual({ name: 'shopware:sbom:external-module', value: 'shopware' });
        const output = path.join(root, 'Resources/public/storefront/components');
        const metadata = JSON.parse(fs.readFileSync(path.join(output, '.vite/build-meta.json'), 'utf8')) as {
            vendorMap: Record<string, string>;
        };
        expect(metadata.vendorMap).toHaveProperty('vendor-library');
        const files = report.components.filter(component => component.type === 'file');
        expect(files.some(file => /from\s*["']vendor-library["']/.test(fs.readFileSync(path.join(output, file.name), 'utf8')))).toBe(true);
        for (const file of files) {
            const contents = fs.readFileSync(path.join(output, file.name));
            expect(file.hashes).toEqual([{ alg: 'SHA-256', content: createHash('sha256').update(contents).digest('hex') }]);
        }
        await validate(report);
    });

    it('keeps reports outside public assets and preserves them after source cleanup', async () => {
        const root = path.join(fixtureRoot, 'NoPackages');
        const report = await buildFixture(root, { 'main.js': 'export const run = () => Math.random();' });

        expect(packages(report)).toEqual([]);
        expect(report.components.filter(component => component.type === 'file')).toHaveLength(1);
        expect(fs.readdirSync(path.join(root, 'Resources/public'), { recursive: true, encoding: 'utf8' })
            .filter(file => file.endsWith('.cdx.json'))).toEqual([]);
        fs.rmSync(path.join(root, 'Resources/app'), { recursive: true });
        expect(readReport(root)).toEqual(report);
        await validate(report);
    });

    it('hashes artifacts after asynchronous output plugins finish writing them', async () => {
        const root = path.join(fixtureRoot, 'Postprocessed');
        const appendFooter: Plugin = {
            name: 'append-footer',
            async writeBundle(options, bundle) {
                await new Promise(resolve => setTimeout(resolve, 20));
                for (const chunk of Object.values(bundle)) {
                    if (chunk.type === 'chunk') {
                        await fs.promises.appendFile(path.join(options.dir!, chunk.fileName), '\n// final footer\n');
                    }
                }
            },
        };

        const report = await buildFixture(root, { 'main.js': 'export const run = () => Math.random();' }, { plugins: [appendFooter] });

        for (const file of report.components.filter(component => component.type === 'file')) {
            const contents = fs.readFileSync(path.join(root, 'Resources/public/storefront/components', file.name));
            expect(contents.toString()).toContain('// final footer');
            expect(file.hashes).toEqual([{ alg: 'SHA-256', content: createHash('sha256').update(contents).digest('hex') }]);
        }
    });

    it('keeps concurrent builds isolated and deduplicates shared factory registration', async () => {
        const roots = ['FirstExtension', 'SecondExtension'].map(name => path.join(fixtureRoot, name));
        const reports = await Promise.all(roots.map((root, index) => buildFixture(root, {
            'main.js': `import { run } from "package-${index}"; export { run };`,
            [`node_modules/package-${index}/package.json`]: npmPackage(`package-${index}`, `${index + 1}.0.0`),
            [`node_modules/package-${index}/index.js`]: 'export const run = () => Math.random();',
        }, {
            plugins: [storefrontSbomPlugin({ bundleRoot: root, bundleName: path.basename(root), target: 'components' })],
        })));

        reports.forEach((report, index) => {
            expect(packages(report)).toEqual([`package-${index}@${index + 1}.0.0`]);
            expect(report.metadata.component.name).toBe(`${path.basename(roots[index]!)}/components`);
            expect(fs.readdirSync(path.join(roots[index]!, 'Resources/sbom/storefront'))).toEqual(['components.cdx.json']);
        });
    });

    it('produces separate reports for multiple custom outputs', async () => {
        const root = path.join(fixtureRoot, 'Outputs');
        await buildFixture(root, {
            'main.js': 'export const start = () => new Worker(new URL("./worker.js", import.meta.url), { type: "module" });',
            'worker.js': 'import { run } from "worker-library"; self.onmessage = () => self.postMessage(run());',
            'node_modules/worker-library/package.json': npmPackage('worker-library'),
            'node_modules/worker-library/index.js': 'export const run = () => Date.now();',
        }, {
            build: { rolldownOptions: { output: [{ format: 'es' }, { format: 'cjs', entryFileNames: '[name].cjs' }] } },
        });

        const directory = path.join(root, 'Resources/sbom/storefront');
        expect(fs.readdirSync(directory).sort()).toEqual(['components-2-cjs.cdx.json', 'components.cdx.json']);
        const first = readReport(root);
        const second = readReport(root, 'components-2-cjs.cdx.json');
        expect(first.metadata.component['bom-ref']).not.toBe(second.metadata.component['bom-ref']);
        expect(second.components.some(component => component.type === 'file' && component.name.endsWith('.cjs'))).toBe(true);
        for (const report of [first, second]) {
            expect(packages(report)).toEqual(['worker-library@1.2.3']);
            const files = report.components.filter(component => component.type === 'file');
            expect(files).toHaveLength(2);
            for (const file of files) {
                const contents = fs.readFileSync(path.join(root, 'Resources/public/storefront/components', file.name));
                expect(file.hashes).toEqual([{ alg: 'SHA-256', content: createHash('sha256').update(contents).digest('hex') }]);
            }
        }
        await validate(first);
        await validate(second);
    });

    it.each([false, true])('resolves relative output directories with a custom bundler cwd: %s', async (customCwd) => {
        const root = path.join(fixtureRoot, `RelativeOutputs-${customCwd}`);
        const app = path.join(root, 'Resources/app/storefront');
        const workingDirectory = customCwd ? path.join(root, 'working-directory') : root;
        fs.mkdirSync(workingDirectory, { recursive: true });
        writeFiles(app, { 'main.js': 'export const run = () => Math.random();' });
        const script = `
            import { build } from ${JSON.stringify(pathToFileURL(require.resolve('vite')).href)};
            import { storefrontSbomPlugin } from ${JSON.stringify(pathToFileURL(path.join(import.meta.dirname, 'storefront-sbom.mjs')).href)};
            await build({
                configFile: false, root: ${JSON.stringify(app)}, logLevel: 'silent',
                plugins: [storefrontSbomPlugin({ bundleRoot: ${JSON.stringify(root)}, bundleName: 'RelativeOutputs', target: 'components' })],
                build: {
                    emptyOutDir: false,
                    rolldownOptions: {
                        cwd: ${customCwd ? JSON.stringify(workingDirectory) : 'undefined'},
                        input: ${JSON.stringify(path.join(app, 'main.js'))},
                        preserveEntrySignatures: 'exports-only',
                        output: [{ dir: 'custom-es', format: 'es' }, { dir: 'custom-cjs', format: 'cjs' }],
                    },
                },
            });
        `;
        await promisify(execFile)(process.execPath, ['--input-type=module', '--eval', script], { cwd: root });

        const report = readReport(root);
        const esDirectory = path.join(workingDirectory, 'custom-es');
        const cjsDirectory = path.join(workingDirectory, 'custom-cjs');
        expect(report.metadata.properties).toContainEqual({
            name: 'shopware:sbom:output-directory', value: path.relative(root, esDirectory).split(path.sep).join('/'),
        });
        const cjsReport = readReport(root, 'components-2-cjs.cdx.json');
        expect(cjsReport.metadata.properties).toContainEqual({
            name: 'shopware:sbom:output-directory', value: path.relative(root, cjsDirectory).split(path.sep).join('/'),
        });
        for (const [document, directory] of [[report, esDirectory], [cjsReport, cjsDirectory]] as const) {
            for (const file of document.components.filter(component => component.type === 'file')) {
                const contents = fs.readFileSync(path.join(directory, file.name));
                expect(file.hashes).toEqual([{ alg: 'SHA-256', content: createHash('sha256').update(contents).digest('hex') }]);
            }
            await validate(document);
        }
    });

    it('uses Composer metadata from the owning plugin beside its src directory', async () => {
        const extension = path.join(fixtureRoot, 'PluginMetadata');
        const root = path.join(extension, 'src');
        writeFiles(extension, {
            'composer.json': JSON.stringify({ type: 'shopware-platform-plugin', name: 'test/plugin', version: '3.2.1', description: 'Plugin metadata' }),
            'src/Resources/app/storefront/main.js': 'export const run = () => Math.random();',
        });

        await build(fixtureConfig(root));

        const report = readReport(root);
        expect(report.metadata.component.version).toBe('3.2.1');
        expect(report.metadata.component).toHaveProperty('description', 'Plugin metadata');
        await validate(report);
    });

    it('does not inherit the enclosing shop Composer metadata', async () => {
        const project = path.join(fixtureRoot, 'ShopMetadata');
        const root = path.join(project, 'custom/apps/ExampleApp');
        writeFiles(project, {
            'composer.json': JSON.stringify({ type: 'project', name: 'test/shop', version: '9.8.7', description: 'Enclosing shop' }),
            'custom/apps/ExampleApp/Resources/app/storefront/main.js': 'export const run = () => Math.random();',
        });

        await build(fixtureConfig(root));

        const report = readReport(root);
        expect(report.metadata.component).not.toHaveProperty('version');
        expect(report.metadata.component).not.toHaveProperty('description');
        await validate(report);
    });

    it('replaces watch inventories for every output and recovers after a failed rebuild', async () => {
        const root = path.join(fixtureRoot, 'Watch');
        const app = path.join(root, 'Resources/app/storefront');
        const entry = path.join(app, 'main.js');
        writeFiles(app, {
            'main.js': 'import { run } from "first"; export { run };',
            'node_modules/first/package.json': npmPackage('first'),
            'node_modules/first/index.js': 'export const run = () => console.log("first vendor");',
            'node_modules/second/package.json': npmPackage('second'),
            'node_modules/second/index.js': 'export const run = () => console.log("second vendor");',
        });
        const watcher = await build(fixtureConfig(root, {
            build: {
                watch: {},
                rolldownOptions: { output: [{ format: 'es', entryFileNames: 'main.js' }, { format: 'cjs', entryFileNames: 'main.cjs' }] },
            },
        }));
        if (!('on' in watcher)) {
            throw new Error('Expected a watch build');
        }
        const filenames = ['components.cdx.json', 'components-2-cjs.cdx.json'];
        const expectPackages = async (name: string): Promise<void> => {
            await vi.waitFor(() => {
                expect(fs.readdirSync(path.join(root, 'Resources/sbom/storefront')).sort()).toEqual([...filenames].sort());
                for (const filename of filenames) {
                    const report = readReport(root, filename);
                    expect(packages(report)).toEqual([`${name}@1.2.3`]);
                    for (const file of report.components.filter(component => component.type === 'file')) {
                        const contents = fs.readFileSync(path.join(root, 'Resources/public/storefront/components', file.name));
                        expect(file.hashes).toEqual([{ alg: 'SHA-256', content: createHash('sha256').update(contents).digest('hex') }]);
                    }
                }
            }, { timeout: 3000 });
        };

        try {
            await watchBuild(watcher);
            await expectPackages('first');

            await watchBuild(watcher, () => fs.writeFileSync(entry, 'import { run } from "second"; export { run };'));
            await expectPackages('second');

            await watchBuild(watcher, () => fs.writeFileSync(entry, 'export const = ;'), 'ERROR');
            await watchBuild(watcher, () => fs.writeFileSync(entry, 'import { run } from "first"; export { run };'));
            await expectPackages('first');
        } finally {
            await watcher.close();
        }
    }, 30000);

    it('retains cached inline worker inventories and refreshes them on watch rebuilds', async () => {
        const root = path.join(fixtureRoot, 'WatchWorkers');
        const app = path.join(root, 'Resources/app/storefront');
        const entry = path.join(app, 'main.js');
        const worker = path.join(app, 'worker.js');
        const mainSource = 'import Background from "./worker.js?worker&inline"; export const start = () => new Background();';
        const workerSource = (name: string): string => `import { run } from "${name}"; self.onmessage = () => self.postMessage(run());`;
        writeFiles(app, {
            'main.js': mainSource,
            'worker.js': workerSource('first'),
            'node_modules/first/package.json': npmPackage('first'),
            'node_modules/first/index.js': 'export const run = () => "FIRST_WORKER_MARKER" + Date.now();',
            'node_modules/second/package.json': npmPackage('second'),
            'node_modules/second/index.js': 'export const run = () => "SECOND_WORKER_MARKER" + Date.now();',
        });
        const watcher = await build(fixtureConfig(root, { build: { watch: {} } }));
        if (!('on' in watcher)) {
            throw new Error('Expected a watch build');
        }
        const expectPackages = async (expected: string[]): Promise<void> => {
            await vi.waitFor(() => expect(packages(readReport(root))).toEqual(expected), { timeout: 3000 });
            await validate(readReport(root));
        };

        try {
            await watchBuild(watcher);
            await expectPackages(['first@1.2.3']);

            await watchBuild(watcher, () => fs.writeFileSync(entry, `${mainSource} export const marker = "main changed";`));
            await expectPackages(['first@1.2.3']);

            await watchBuild(watcher, () => fs.writeFileSync(worker, workerSource('second')));
            await expectPackages(['second@1.2.3']);

            await watchBuild(watcher, () => fs.writeFileSync(worker, 'export const = ;'), 'ERROR');
            await watchBuild(watcher, () => fs.writeFileSync(worker, workerSource('first')));
            await expectPackages(['first@1.2.3']);

            await watchBuild(watcher, () => fs.writeFileSync(entry, 'export const marker = "no worker";'));
            await expectPackages([]);
        } finally {
            await watcher.close();
        }
    }, 30000);

    it('generates reports for a production build using a custom Vite mode', async () => {
        const report = await buildFixture(path.join(fixtureRoot, 'Staging'), { 'main.js': 'export const run = () => Math.random();' }, { mode: 'staging' });

        expect(report.metadata.component.name).toBe('Staging/components');
        await validate(report);
    });

    it.each([
        { mode: 'development', nodeEnv: 'production' },
        { mode: 'production', nodeEnv: 'development' },
    ])('does not generate reports for mode=$mode and NODE_ENV=$nodeEnv', async ({ mode, nodeEnv }) => {
        const root = path.join(fixtureRoot, `Development-${mode}`);
        writeFiles(root, { 'Resources/app/storefront/main.js': 'export default 1;' });
        vi.stubEnv('NODE_ENV', nodeEnv);

        await build(fixtureConfig(root, { mode }));

        expect(fs.readdirSync(path.join(root, 'Resources/public/storefront/components'), { recursive: true, encoding: 'utf8' })
            .some(file => file.endsWith('.js'))).toBe(true);
        expect(fs.existsSync(path.join(root, 'Resources/sbom/storefront'))).toBe(false);
    });

    it('propagates persistence errors', async () => {
        const root = path.join(fixtureRoot, 'PersistenceFailure');
        const plugin: Plugin = {
            name: 'block-sbom-directory',
            writeBundle() {
                const directory = path.join(root, 'Resources/sbom/storefront');
                fs.mkdirSync(path.dirname(directory), { recursive: true });
                fs.writeFileSync(directory, 'blocked');
            },
        };

        await expect(buildFixture(root, { 'main.js': 'export default 1;' }, { plugins: [plugin] }))
            .rejects.toThrow(/Refusing to use SBOM report path/);
    });

    it.each([
        'components.cdx.json.tmp',
        'components.cdx.json',
    ])('does not follow a symlink planted as Resources/sbom/storefront/%s', async (planted) => {
        const root = path.join(fixtureRoot, `PlantedFile-${planted}`);
        const victim = path.join(fixtureRoot, `victim-${planted}.env`);
        fs.writeFileSync(victim, 'APP_SECRET=keep');
        fs.mkdirSync(path.join(root, 'Resources/sbom/storefront'), { recursive: true });
        fs.symlinkSync(victim, path.join(root, 'Resources/sbom/storefront', planted));

        await buildFixture(root, { 'main.js': 'export default 1;' });

        expect(fs.readFileSync(victim, 'utf8')).toBe('APP_SECRET=keep');
        expect(fs.lstatSync(path.join(root, 'Resources/sbom/storefront/components.cdx.json')).isFile()).toBe(true);
        expect(readReport(root).bomFormat).toBe('CycloneDX');
    });

    it('refuses a report directory reached through a symlink', async () => {
        const root = path.join(fixtureRoot, 'PlantedDirectory');
        const victim = path.join(fixtureRoot, 'victim-directory');
        fs.mkdirSync(path.join(victim, 'storefront'), { recursive: true });
        fs.writeFileSync(path.join(victim, 'storefront/components.cdx.json'), 'keep');
        fs.mkdirSync(path.join(root, 'Resources'), { recursive: true });
        fs.symlinkSync(victim, path.join(root, 'Resources/sbom'));

        await expect(buildFixture(root, { 'main.js': 'export default 1;' }))
            .rejects.toThrow(/Refusing to use SBOM report path/);
        expect(fs.readdirSync(path.join(victim, 'storefront'))).toEqual(['components.cdx.json']);
        expect(fs.readFileSync(path.join(victim, 'storefront/components.cdx.json'), 'utf8')).toBe('keep');
    });

    it('fails generation when a bundled package has no resolved version', async () => {
        const root = path.join(fixtureRoot, 'MissingVersion');

        await expect(buildFixture(root, {
            'main.js': 'import { run } from "unversioned"; export { run };',
            'node_modules/unversioned/package.json': JSON.stringify({ name: 'unversioned', type: 'module', main: 'index.js' }),
            'node_modules/unversioned/index.js': 'export const run = () => Math.random();',
        })).rejects.toThrow(/Missing resolved package name\/version/);
        expect(fs.existsSync(path.join(root, 'Resources/sbom/storefront/components.cdx.json'))).toBe(false);
    });

    it('orchestrates generic and custom extension builds, and removes reports when components disappear', async () => {
        const project = path.join(fixtureRoot, 'Project');
        const genericRoot = path.join(project, 'Generic');
        const customRoot = path.join(project, 'Custom');
        const factory = path.join(import.meta.dirname, 'component-config-factory.ts');
        const bundles = { Generic: { basePath: 'Generic' }, Custom: { basePath: 'Custom' } };
        writeFiles(project, { 'var/plugins.json': JSON.stringify(bundles) });
        for (const root of [genericRoot, customRoot]) {
            writeFiles(root, {
                'Resources/app/storefront/package.json': npmPackage('extension-build'),
                'Resources/app/storefront/node_modules/fixture-library/package.json': npmPackage('fixture-library'),
                'Resources/app/storefront/node_modules/fixture-library/index.js': 'export const run = () => Math.random();',
                'Resources/app/storefront/node_modules/worker-library/package.json': npmPackage('worker-library'),
                'Resources/app/storefront/node_modules/worker-library/index.js': 'export const run = () => "ORCHESTRATED_WORKER_MARKER" + Date.now();',
                'Resources/app/storefront/worker.js': 'import { run } from "worker-library"; self.onmessage = () => self.postMessage(run());',
                'Resources/views/components/Action.js': 'import { run } from "fixture-library"; export default run; export const start = () => new Worker(new URL("../../app/storefront/worker.js", import.meta.url), { type: "module" });',
            });
        }
        writeFiles(customRoot, {
            'Resources/app/storefront/vite.components.config.mts': `
                import { createComponentBuildConfig } from ${JSON.stringify(factory)};
                export default () => createComponentBuildConfig({
                    componentRoot: ${JSON.stringify(path.join(customRoot, 'Resources/views/components'))},
                    outDir: ${JSON.stringify(path.join(customRoot, 'custom-output'))},
                    namespace: 'CustomComponentNamespace',
                    storefrontAppDir: ${JSON.stringify(path.join(customRoot, 'Resources/app/storefront'))},
                });
            `,
        });
        const run = () => promisify(execFile)(process.execPath, [path.join(import.meta.dirname, 'build-components.js')], {
            env: { ...process.env, PROJECT_ROOT: project },
        });

        await run();
        for (const root of [genericRoot, customRoot]) {
            const report = readReport(root);
            expect(packages(report)).toEqual(['fixture-library@1.2.3', 'worker-library@1.2.3']);
            expect(report.metadata.component.name).toBe(`${path.basename(root)}/components`);
            const directory = root === customRoot ? path.join(root, 'custom-output') : path.join(root, 'Resources/public/storefront/components');
            const files = report.components.filter(component => component.type === 'file');
            const workerFile = files.find(file => fs.readFileSync(path.join(directory, file.name), 'utf8').includes('ORCHESTRATED_WORKER_MARKER'));
            expect(workerFile).toBeDefined();
            for (const file of files) {
                expect(file.hashes).toEqual([{ alg: 'SHA-256', content: createHash('sha256').update(fs.readFileSync(path.join(directory, file.name))).digest('hex') }]);
            }
            await validate(report);
        }
        expect(readReport(customRoot).metadata.properties).toContainEqual({
            name: 'shopware:sbom:output-directory', value: 'custom-output',
        });
        fs.rmSync(path.join(genericRoot, 'Resources/views/components/Action.js'));
        await run();
        expect(fs.existsSync(path.join(genericRoot, 'Resources/sbom/storefront/components.cdx.json'))).toBe(false);
        expect(packages(readReport(customRoot))).toEqual(['fixture-library@1.2.3', 'worker-library@1.2.3']);
        fs.rmSync(path.join(customRoot, 'Resources/views/components'), { recursive: true });
        await run();
        expect(fs.existsSync(path.join(customRoot, 'Resources/sbom/storefront/components.cdx.json'))).toBe(false);
    }, 30000);
});
