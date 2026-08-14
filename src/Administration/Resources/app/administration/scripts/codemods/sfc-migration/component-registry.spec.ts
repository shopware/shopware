/**
 * @sw-package framework
 */

import * as fs from 'fs';
import * as os from 'os';
import * as path from 'path';
import { collectComponentRegistry } from './component-registry';

const ADMIN_SRC = path.resolve(__dirname, '../../../src');

describe('scripts/codemods/sfc-migration/component-registry', () => {
    let tmpDir: string;

    const writeFile = (relativePath: string, source: string): void => {
        const file = path.join(tmpDir, relativePath);

        fs.mkdirSync(path.dirname(file), { recursive: true });
        fs.writeFileSync(file, source);
    };

    const writeComponentDir = (relativeDir: string): string => {
        writeFile(path.join(relativeDir, 'index.js'), 'export default {};\n');

        return path.join(tmpDir, relativeDir);
    };

    beforeEach(() => {
        tmpDir = fs.mkdtempSync(path.join(os.tmpdir(), 'sfc-registry-'));
    });

    afterEach(() => {
        fs.rmSync(tmpDir, { recursive: true, force: true });
    });

    it('maps a lazy register call to its component directory', () => {
        const componentDir = writeComponentDir('sw-simple');

        writeFile('index.js', "Component.register('sw-simple', () => import('./sw-simple'));\n");

        const registry = collectComponentRegistry(tmpDir);

        expect([...registry.byDir]).toEqual([
            [
                componentDir,
                { kind: 'register', name: 'sw-simple' },
            ],
        ]);
        expect(registry.inlineOverrides).toEqual([]);
        expect(registry.duplicateNames).toEqual(new Set());
    });

    it('reads multiline calls with a Shopware prefix and double quotes', () => {
        const componentDir = writeComponentDir('component/sw-multiline');

        writeFile(
            'index.js',
            [
                'Shopware.Component.register(',
                '    "sw-multiline",',
                '    () => import("./component/sw-multiline"),',
                ');',
                '',
            ].join('\n'),
        );

        const registry = collectComponentRegistry(tmpDir);

        expect(registry.byDir.get(componentDir)).toEqual({ kind: 'register', name: 'sw-multiline' });
    });

    it('captures the parent of a three-argument extend call', () => {
        const childDir = writeComponentDir('sw-child');

        writeFile(
            'index.js',
            [
                'Shopware.Component.extend(',
                "    'sw-child',",
                "    'sw-parent',",
                "    () => import('./sw-child'),",
                ');',
                '',
            ].join('\n'),
        );

        const registry = collectComponentRegistry(tmpDir);

        expect(registry.byDir.get(childDir)).toEqual({ kind: 'extend', name: 'sw-child', parent: 'sw-parent' });
    });

    it('reads an import that is passed without the factory arrow', () => {
        const componentDir = writeComponentDir('sw-eager');

        writeFile('index.js', "Component.extend('sw-eager', 'sw-parent', import('./sw-eager'));\n");

        const registry = collectComponentRegistry(tmpDir);

        expect(registry.byDir.get(componentDir)).toEqual({ kind: 'extend', name: 'sw-eager', parent: 'sw-parent' });
    });

    it('maps a lazy override call like any other registration', () => {
        const componentDir = writeComponentDir('sw-lazy-override');

        writeFile('index.js', "Component.override('sw-lazy-override', () => import('./sw-lazy-override'));\n");

        const registry = collectComponentRegistry(tmpDir);

        expect(registry.byDir.get(componentDir)).toEqual({ kind: 'override', name: 'sw-lazy-override' });
        expect(registry.inlineOverrides).toEqual([]);
    });

    it('collects inline overrides with their call site instead of a directory', () => {
        writeFile(
            'overrides.js',
            [
                "Shopware.Component.override('sw-inline-override', {",
                '    computed: {},',
                '});',
                '',
            ].join('\n'),
        );

        const registry = collectComponentRegistry(tmpDir);

        expect(registry.byDir.size).toBe(0);
        expect(registry.inlineOverrides).toEqual([
            { file: path.join(tmpDir, 'overrides.js'), name: 'sw-inline-override' },
        ]);
    });

    it('ignores inline register and extend calls', () => {
        writeFile(
            'inline.js',
            [
                "Component.register('sw-inline-register', {",
                "    template: '',",
                '});',
                "Component.extend('sw-inline-child', 'sw-parent', {",
                "    template: '',",
                '});',
                '',
            ].join('\n'),
        );

        const registry = collectComponentRegistry(tmpDir);

        expect(registry.byDir.size).toBe(0);
        expect(registry.inlineOverrides).toEqual([]);
    });

    it('resolves src/ alias specifiers against the admin src root', () => {
        writeFile('index.js', "Component.register('sw-aliased', () => import('src/app/component'));\n");

        const registry = collectComponentRegistry(tmpDir);

        expect(registry.byDir.get(path.join(ADMIN_SRC, 'app', 'component'))).toEqual({
            kind: 'register',
            name: 'sw-aliased',
        });
    });

    it('resolves a specifier that spells out the index module to its directory', () => {
        writeComponentDir('page');

        writeFile('index.js', "Component.register('sw-page-component', () => import('./page/index'));\n");

        const registry = collectComponentRegistry(tmpDir);

        expect(registry.byDir.get(path.join(tmpDir, 'page'))).toEqual({
            kind: 'register',
            name: 'sw-page-component',
        });
    });

    it('ignores specifiers without an index module and bare package names', () => {
        fs.mkdirSync(path.join(tmpDir, 'sw-no-index'));
        fs.writeFileSync(path.join(tmpDir, 'sw-no-index', 'sw-no-index.vue'), '<template />\n');

        writeFile(
            'index.js',
            [
                "Component.register('sw-missing', () => import('./does-not-exist'));",
                "Component.register('sw-no-index', () => import('./sw-no-index'));",
                "Component.register('sw-package', () => import('some-package/component'));",
                '',
            ].join('\n'),
        );

        const registry = collectComponentRegistry(tmpDir);

        expect(registry.byDir.size).toBe(0);
    });

    it('keeps both directories but flags a component name registered twice', () => {
        const firstDir = writeComponentDir('module-a/sw-duplicate');
        const secondDir = writeComponentDir('module-b/sw-duplicate');

        writeFile('module-a/index.js', "Component.register('sw-duplicate', () => import('./sw-duplicate'));\n");
        writeFile('module-b/index.js', "Component.register('sw-duplicate', () => import('./sw-duplicate'));\n");

        const registry = collectComponentRegistry(tmpDir);

        expect(registry.byDir.has(firstDir)).toBe(true);
        expect(registry.byDir.has(secondDir)).toBe(true);
        expect(registry.duplicateNames).toEqual(new Set(['sw-duplicate']));
    });

    it('skips registrations inside node_modules', () => {
        writeComponentDir('sw-vendor');
        writeFile(
            'node_modules/some-package/index.js',
            "Component.register('sw-vendor', () => import('../../sw-vendor'));\n",
        );

        const registry = collectComponentRegistry(tmpDir);

        expect(registry.byDir.size).toBe(0);
    });
});
