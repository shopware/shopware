/**
 * @sw-package framework
 */

import * as fs from 'fs';
import * as path from 'path';
import { collectComponentSourceIndex, type ComponentSource, type ComponentSourceIndex } from './component-source-model';
import { makeRoot, writeFile as writeFileIn } from './spec-helpers';

describe('scripts/codemods/sfc-migration/component-source-model', () => {
    let tmpDir: string;

    const writeFile = (relativePath: string, source: string): string => writeFileIn(tmpDir, relativePath, source);

    const writeComponent = (relativeDir: string, componentSource = 'export default {};\n'): string => {
        const indexFile = writeFile(path.join(relativeDir, 'index.js'), componentSource);

        writeFile(path.join(relativeDir, `${path.basename(relativeDir)}.html.twig`), '<div />\n');

        return indexFile;
    };

    const sourceFor = (indexFile: string, index: ComponentSourceIndex): ComponentSource => {
        const component = index.components.get(indexFile);

        expect(component).toBeDefined();

        return component as ComponentSource;
    };

    beforeEach(() => {
        tmpDir = makeRoot('sfc-source-model-');
    });

    afterEach(() => {
        fs.rmSync(tmpDir, { recursive: true, force: true });
    });

    it('finds real multiline registrations but ignores comments, strings, and template literals', () => {
        const componentDir = path.join(tmpDir, 'sw-real');

        writeComponent('sw-real');
        writeFile(
            'registrations.js',
            [
                "/* Component.register('sw-comment', () => import('./sw-comment')); */",
                "const string = \"Component.register('sw-string', () => import('./sw-string'))\";",
                "const template = `Component.register('sw-template', () => import('./sw-template'))`;",
                'Shopware.Component.register(',
                '    "sw-real",',
                "    () => import('./sw-real'),",
                ');',
                '',
            ].join('\n'),
        );

        const index = collectComponentSourceIndex(tmpDir);
        const registrations = index.registrationsByFile.get(path.join(tmpDir, 'registrations.js')) ?? [];

        expect(registrations).toHaveLength(1);
        expect(registrations[0]).toMatchObject({
            kind: 'register',
            name: 'sw-real',
            specifier: './sw-real',
            resolvedDir: componentDir,
        });
    });

    it('excludes specs, fixtures, and documentation examples from registration discovery', () => {
        writeComponent('sw-production');
        writeFile('registrations.js', "Component.register('sw-production', () => import('./sw-production'));\n");
        writeFile('sw-test.spec.js', "Component.register('sw-test', () => import('./sw-test'));\n");
        writeFile('test/sw-test.js', "Component.register('sw-test', () => import('../sw-test'));\n");
        writeFile('fixtures/example.js', "Component.register('sw-fixture', () => import('../sw-fixture'));\n");
        writeFile('docs/example.js', "Component.register('sw-doc', () => import('../sw-doc'));\n");
        writeFile('technical-docs/example.ts', "Component.register('sw-technical-doc', () => import('../sw-doc'));\n");

        const index = collectComponentSourceIndex(tmpDir);
        const registrations = [...index.registrationsByFile.values()].flat();

        expect(registrations).toHaveLength(1);
        expect(registrations[0].name).toBe('sw-production');
    });

    it('skips registrations inside node_modules', () => {
        writeComponent('sw-vendor');
        writeFile(
            'node_modules/some-package/index.js',
            "Component.register('sw-vendor', () => import('../../sw-vendor'));\n",
        );

        expect(collectComponentSourceIndex(tmpDir).registrationsByDir.size).toBe(0);
    });

    it('retains register, extend, override, and inline override references', () => {
        writeComponent('sw-register');
        writeComponent('sw-extend');
        writeComponent('sw-override');
        writeFile(
            'module.ts',
            [
                "Component.register('sw-register', () => import('./sw-register'));",
                // An eager import without the factory arrow resolves the same way.
                "Shopware.Component.extend(\n    'sw-extend',\n    'sw-parent',\n    import('./sw-extend'),\n);",
                "Component.override('sw-override', () => import('./sw-override'));",
                "Component.override('sw-inline', { computed: {} });",
                '',
            ].join('\n'),
        );

        const index = collectComponentSourceIndex(tmpDir);
        const registrations = index.registrationsByFile.get(path.join(tmpDir, 'module.ts')) ?? [];

        expect(registrations).toHaveLength(4);
        expect(registrations).toEqual(
            expect.arrayContaining([
                expect.objectContaining({ kind: 'register', name: 'sw-register', inline: false }),
                expect.objectContaining({ kind: 'extend', name: 'sw-extend', parent: 'sw-parent', inline: false }),
                expect.objectContaining({ kind: 'override', name: 'sw-override', inline: false }),
                expect.objectContaining({ kind: 'override', name: 'sw-inline', inline: true }),
            ]),
        );
        // An inline override owns no directory, so it is carried by call site instead.
        expect(index.inlineOverrides).toEqual([{ file: path.join(tmpDir, 'module.ts'), name: 'sw-inline' }]);
    });

    it('ignores inline register and extend calls, which own no component directory', () => {
        writeFile(
            'inline.js',
            [
                "Component.register('sw-inline-register', {\n    template: '',\n});",
                "Component.extend('sw-inline-child', 'sw-parent', {\n    template: '',\n});",
                '',
            ].join('\n'),
        );

        const index = collectComponentSourceIndex(tmpDir);

        expect(index.registrationsByDir.size).toBe(0);
        expect(index.inlineOverrides).toEqual([]);
    });

    it.each([
        [
            'the src/ alias against the admin src root',
            'app/component',
            "Component.register('sw-aliased', () => import('src/app/component'));\n",
            'app/component',
        ],
        [
            'a specifier spelling out the index module',
            'page',
            "Component.register('sw-page', () => import('./page/index'));\n",
            'page',
        ],
        [
            'a directory without an index module',
            'sw-no-index',
            "Component.register('sw-no-index', () => import('./sw-no-index/other'));\n",
            null,
        ],
        [
            'a specifier that does not exist',
            'sw-present',
            "Component.register('sw-missing', () => import('./does-not-exist'));\n",
            null,
        ],
        [
            'a bare package name',
            'sw-present',
            "Component.register('sw-package', () => import('some-package/component'));\n",
            null,
        ],
    ])('resolves %s', (_label, componentDir, registration, expectedDir) => {
        writeComponent(componentDir);
        writeFile('index.js', registration);

        const index = collectComponentSourceIndex(tmpDir, { adminSrc: tmpDir });

        expect([...index.registrationsByDir.keys()]).toEqual(expectedDir === null ? [] : [path.join(tmpDir, expectedDir)]);
    });

    it('retains every registration and makes a conflicting directory ambiguous', () => {
        writeComponent('sw-conflict');
        writeFile(
            'module.js',
            [
                "Component.register('sw-conflict', () => import('./sw-conflict'));",
                "Component.extend('sw-conflict', 'sw-parent', () => import('./sw-conflict'));",
                '',
            ].join('\n'),
        );

        const index = collectComponentSourceIndex(tmpDir);

        expect(index.registrationsByDir.get(path.join(tmpDir, 'sw-conflict'))).toHaveLength(2);
        expect(index.diagnostics).toEqual(
            expect.arrayContaining([expect.objectContaining({ label: 'registration/registration-ambiguous' })]),
        );
    });

    it('keeps both directories but flags a component name registered twice', () => {
        writeComponent('module-a/sw-duplicate');
        writeComponent('module-b/sw-duplicate');
        writeFile('module-a/index.js', "Component.register('sw-duplicate', () => import('./sw-duplicate'));\n");
        writeFile('module-b/index.js', "Component.register('sw-duplicate', () => import('./sw-duplicate'));\n");

        const index = collectComponentSourceIndex(tmpDir);

        expect(index.registrationsByDir.has(path.join(tmpDir, 'module-a', 'sw-duplicate'))).toBe(true);
        expect(index.registrationsByDir.has(path.join(tmpDir, 'module-b', 'sw-duplicate'))).toBe(true);
        expect(index.duplicateNames).toEqual(new Set(['sw-duplicate']));
    });

    it('resolves the template option to the exact default Twig import', () => {
        const indexFile = writeComponent(
            'sw-template-binding',
            [
                "import stale from './stale.html.twig';",
                "import template from './real.html.twig';",
                '',
                'export default {',
                '    template,',
                '};',
                '',
            ].join('\n'),
        );
        writeFile('sw-template-binding/stale.html.twig', '<div>stale</div>\n');
        writeFile('sw-template-binding/real.html.twig', '<div>real</div>\n');

        const component = sourceFor(indexFile, collectComponentSourceIndex(tmpDir));

        expect(component.template?.twigPath).toBe(path.join(tmpDir, 'sw-template-binding', 'real.html.twig'));
        // The range covers the import statement the conversion has to remove.
        expect(component.source.slice(component.template?.importRange.start, component.template?.importRange.end)).toBe(
            "import template from './real.html.twig';",
        );
    });

    it.each([
        [
            'wrong',
            "import template from './template.js';\nexport default { template };\n",
            'template-binding-missing',
        ],
        [
            'missing',
            'export default { template };\n',
            'template-binding-missing',
        ],
        [
            'unrelated',
            "import template from 'some-package/template.html.twig';\nexport default { template };\n",
            'template-path-outside-component',
        ],
        [
            'non-default',
            "import { template } from './template.html.twig';\nexport default { template };\n",
            'template-binding-not-default',
        ],
        [
            'duplicate',
            [
                "import template from './template.html.twig';",
                "import other from './other.html.twig';",
                'export default { template, template: other };',
                '',
            ].join('\n'),
            'template-binding-ambiguous',
        ],
        [
            'escaping',
            "import template from './../outside.html.twig';\nexport default { template };\n",
            'template-path-outside-component',
        ],
    ])('diagnoses %s template bindings', (name, source, diagnosticLabel) => {
        const indexFile = writeComponent(`components/${name}`, source);

        writeFile('components/template.html.twig', '<div />\n');
        writeFile('components/other.html.twig', '<div />\n');

        const component = sourceFor(indexFile, collectComponentSourceIndex(tmpDir));

        expect(component.template).toBeNull();
        expect(component.diagnostics).toEqual(
            expect.arrayContaining([expect.objectContaining({ label: `template-binding/${diagnosticLabel}` })]),
        );
    });

    it('reports an escaping registration without authorizing an outside directory', () => {
        writeComponent('outside');
        writeFile('scan/module.js', "Component.register('sw-outside', () => import('../outside'));\n");

        const index = collectComponentSourceIndex(path.join(tmpDir, 'scan'));

        expect(index.registrationsByDir.size).toBe(0);
        expect(index.diagnostics).toEqual(
            expect.arrayContaining([expect.objectContaining({ label: 'registration/registration-path-outside-root' })]),
        );
    });

    it('retains unreadable-file diagnostics while collecting readable sources', () => {
        const goodFile = writeFile('good.js', "Component.register('sw-good', () => import('./sw-good'));\n");
        writeComponent('sw-good');
        const unreadableFile = writeFile('unreadable.js', "Component.register('sw-unreadable', {});\n");

        const index = collectComponentSourceIndex(tmpDir, {
            readFile: (file) => {
                if (file === unreadableFile) {
                    throw new Error('permission denied');
                }

                return fs.readFileSync(file, 'utf8');
            },
        });

        expect(index.files.get(goodFile)).toEqual([]);
        expect(index.files.get(unreadableFile)).toEqual([
            expect.objectContaining({ label: 'scan/read-failed', file: unreadableFile }),
        ]);
        expect(index.registrationsByDir.has(path.join(tmpDir, 'sw-good'))).toBe(true);
    });

    it('reports a source it cannot parse', () => {
        writeFile('broken.js', 'export default {\n');

        const index = collectComponentSourceIndex(tmpDir);

        expect(index.files.get(path.join(tmpDir, 'broken.js'))).toEqual([
            expect.objectContaining({ label: 'scan/parse-failed' }),
        ]);
    });
});
