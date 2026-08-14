/**
 * @sw-package framework
 */

import * as fs from 'fs';
import * as os from 'os';
import * as path from 'path';
import { collectComponentSourceIndex, type ComponentSource, type ComponentSourceIndex } from './component-source-model';
import { collectComponentRegistry } from './component-registry';

describe('scripts/codemods/sfc-migration/component-source-model', () => {
    let tmpDir: string;

    const writeFile = (relativePath: string, source: string): string => {
        const file = path.join(tmpDir, relativePath);

        fs.mkdirSync(path.dirname(file), { recursive: true });
        fs.writeFileSync(file, source);

        return file;
    };

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
        tmpDir = fs.mkdtempSync(path.join(os.tmpdir(), 'sfc-source-model-'));
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
                "    'sw-real',",
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

    it('retains register, extend, override, and inline override references', () => {
        writeComponent('sw-register');
        writeComponent('sw-extend');
        writeComponent('sw-override');
        writeFile(
            'module.ts',
            [
                "Component.register('sw-register', () => import('./sw-register'));",
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
    });

    it('retains every registration and makes a conflicting directory ambiguous', () => {
        writeComponent('sw-conflict');
        const componentDir = path.join(tmpDir, 'sw-conflict');
        writeFile(
            'module.js',
            [
                "Component.register('sw-conflict', () => import('./sw-conflict'));",
                "Component.extend('sw-conflict', 'sw-parent', () => import('./sw-conflict'));",
                '',
            ].join('\n'),
        );

        const index = collectComponentSourceIndex(tmpDir);
        const registry = collectComponentRegistry(tmpDir);
        const registrations = index.registrationsByDir.get(componentDir) ?? [];

        expect(registrations).toHaveLength(2);
        expect(index.diagnostics).toEqual(
            expect.arrayContaining([expect.objectContaining({ code: 'registration-ambiguous' })]),
        );
        expect(registry.ambiguousDirs).toContain(componentDir);
        expect(registry.byDir.has(componentDir)).toBe(false);
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

        expect(component.template).not.toBeNull();
        expect(component.template).toMatchObject({
            localName: 'template',
            specifier: './real.html.twig',
            twigPath: path.join(tmpDir, 'sw-template-binding', 'real.html.twig'),
        });
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
    ])('diagnoses %s template bindings', (name, source, diagnosticCode) => {
        const indexFile = writeComponent(`components/${name}`, source);

        writeFile('components/template.html.twig', '<div />\n');
        writeFile('components/other.html.twig', '<div />\n');

        const component = sourceFor(indexFile, collectComponentSourceIndex(tmpDir));

        expect(component.template).toBeNull();
        expect(component.diagnostics).toEqual(expect.arrayContaining([expect.objectContaining({ code: diagnosticCode })]));
    });

    it('reports an escaping registration without authorizing an outside directory', () => {
        writeComponent('outside');
        writeFile('scan/module.js', "Component.register('sw-outside', () => import('../outside'));\n");

        const index = collectComponentSourceIndex(path.join(tmpDir, 'scan'));

        expect(index.registrationsByDir.size).toBe(0);
        expect(index.diagnostics).toEqual(
            expect.arrayContaining([expect.objectContaining({ code: 'registration-path-outside-root' })]),
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

        expect(index.files.get(goodFile)?.ast).not.toBeNull();
        expect(index.files.get(unreadableFile)?.diagnostics).toEqual([
            expect.objectContaining({ code: 'read-failed', file: unreadableFile }),
        ]);
        expect(index.registrationsByDir.has(path.join(tmpDir, 'sw-good'))).toBe(true);
    });
});
