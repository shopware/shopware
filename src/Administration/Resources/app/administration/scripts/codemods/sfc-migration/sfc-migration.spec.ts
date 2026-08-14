/**
 * @sw-package framework
 */

import * as fs from 'fs';
import * as os from 'os';
import * as path from 'path';
import { parse } from '@babel/parser';
import { SLOT_IN_BLOCK } from './assert-block-slots';
import { convertComponent, type ConvertResult } from './convert-component';
import { runMigration, type MigrationResult } from './run-sfc-migration';
import { TWIG_PARENT_BLOCKER, transformTemplate } from './transform-template';

const FIXTURES = path.join(__dirname, '__fixtures__');

function fixturePaths(name: string): { indexPath: string; twigPath: string } {
    const dir = path.join(FIXTURES, name);
    const indexPath = [
        path.join(dir, 'index.js'),
        path.join(dir, 'index.ts'),
    ].find((file) => fs.existsSync(file)) as string;

    return { indexPath, twigPath: path.join(dir, `${name}.html.twig`) };
}

function manifest(root: string): Record<string, Buffer> {
    const files: string[] = [];

    const visit = (directory: string): void => {
        fs.readdirSync(directory, { withFileTypes: true }).forEach((entry) => {
            const file = path.join(directory, entry.name);

            if (entry.isDirectory()) {
                visit(file);
                return;
            }

            files.push(file);
        });
    };

    visit(root);

    return Object.fromEntries(
        files.sort().map((file) => [
            path.relative(root, file).split(path.sep).join('/'),
            fs.readFileSync(file),
        ]),
    );
}

async function convertFixture(name: string): Promise<ConvertResult> {
    const { indexPath, twigPath } = fixturePaths(name);
    const jsSource = fs.readFileSync(indexPath, 'utf8');
    const templateImport = parse(jsSource, { sourceType: 'module', plugins: ['typescript'] }).program.body.find(
        (statement) => statement.type === 'ImportDeclaration' && statement.source.value.endsWith('.html.twig'),
    );

    if (!templateImport) {
        throw new Error(`Fixture ${name} has no Twig import`);
    }

    return convertComponent({
        jsSource,
        twigSource: fs.readFileSync(twigPath, 'utf8'),
        componentName: name,
        vuePath: path.join(FIXTURES, name, `${name}.vue`),
        lang: indexPath.endsWith('.ts') ? 'ts' : 'js',
        templateImportRange: { start: templateImport.start as number, end: templateImport.end as number },
    });
}

describe('scripts/codemods/sfc-migration', () => {
    describe('fixture snapshots (outcome, reasons and generated SFC per fixture)', () => {
        const fixtureNames = fs
            .readdirSync(FIXTURES)
            .filter((entry) => fs.statSync(path.join(FIXTURES, entry)).isDirectory())
            .sort();

        it.each(fixtureNames)('converts %s', async (name) => {
            const result = await convertFixture(name);

            expect(result).toMatchSnapshot();
        });
    });

    describe('outcome expectations (guard the snapshots against silent regressions)', () => {
        it('keeps array injection conservative until its ref-unwrapping contract is proven', async () => {
            const result = await convertFixture('sw-simple-card');

            expect(result.outcome).toBe('partial');
            expect(result.reasons).toContain('array inject declaration requires runtime ref-unwrapping verification');
            expect(result.sfc).toContain('swDefinePublic({');
            expect(result.sfc).not.toContain('this.');
            expect(result.sfc).not.toContain('$dataScope');
        });

        it('marks TODO-tier features as partial but still emits a valid draft', async () => {
            const result = await convertFixture('sw-partial-todos');

            expect(result.outcome).toBe('partial');
            expect(result.reasons).toEqual(
                expect.arrayContaining([
                    expect.stringContaining('inject'),
                    expect.stringContaining('metaInfo'),
                    expect.stringContaining('shortcuts'),
                    expect.stringContaining('$device'),
                ]),
            );
            expect(result.sfc).toContain('TODO(sfc-migration)');
        });

        it('leaves a this.<member> shadowed by a local binding unrewritten', async () => {
            const result = await convertFixture('sw-shadowed-locals');

            expect(result.outcome).toBe('partial');
            expect(result.reasons).toEqual(
                expect.arrayContaining([
                    'this.currentPage is shadowed by a local binding',
                    'this.perPage is shadowed by a local binding',
                    'this.iconSvgData is shadowed by a local binding',
                    'this.$route is shadowed by a local binding',
                    "template ref 'modalContent' is shadowed by a local binding",
                ]),
            );

            // The shadowed references keep their original text instead of resolving to the local.
            expect(result.sfc).toContain('this.perPage = Number(perPage)');
            expect(result.sfc).not.toContain('perPage.value = Number(perPage)');
            expect(result.sfc).toContain('const currentPage = this.currentPage');
            expect(result.sfc).not.toContain('const currentPage = currentPage.value');

            // A shadowed template ref must not be declared either — nothing would ever assign it.
            expect(result.sfc).not.toContain('const modalContent = ref(null)');

            // A binding in a sibling nested function does not shadow, and a local named after a
            // prop cannot shadow `props.<name>`.
            expect(result.sfc).toContain('items.value');
            expect(result.sfc).toContain('props.title');
        });

        it('preserves module-level code in a normal script block', async () => {
            const result = await convertFixture('sw-module-level-code');

            expect(result.outcome).toBe('full');
            expect(result.reasons).toEqual([]);
            expect(result.sfc).toContain('<script data-sfc-migration-module lang="ts">');
        });

        // The `const { X } = Shopware` prelude is by far the most common shape in src/; widening the
        // allowlist check to reject it would downgrade more than half of all components.
        it('keeps a pure Shopware-namespace prelude a full migration', async () => {
            const result = await convertFixture('sw-wrap-config');

            expect(result.outcome).toBe('partial');
            expect(result.reasons).toContain('array inject declaration requires runtime ref-unwrapping verification');
        });

        it('skips mixin components entirely', async () => {
            const result = await convertFixture('sw-mixin-demo');

            expect(result).toEqual({ outcome: 'skipped', reasons: ['mixins'], sfc: null });
        });

        it('skips components using this.$super', async () => {
            const result = await convertFixture('sw-super-demo');

            expect(result.outcome).toBe('skipped');
            expect(result.reasons).toEqual(['this.$super']);
        });

        it('skips components whose name option differs from the directory name', async () => {
            const result = await convertFixture('sw-name-mismatch');

            expect(result.outcome).toBe('skipped');
            expect(result.reasons).toEqual(["name 'sw-totally-different' does not match the directory name"]);
        });

        it('reconnects a v-if/v-else chain that the block conversion split into siblings', async () => {
            const result = await convertFixture('sw-cross-velse');

            expect(result.outcome).toBe('full');
            expect(result.sfc).toContain(
                '<template v-if="active"><!-- Keeps the conditional chain connected across sw-block. --></template>',
            );
        });

        // Every authoring form has to be refused: the leftover-twig check only looks for `{%`/`{#`,
        // so a surviving `{{ parent() }}` would compile as a live interpolation and fail at runtime.
        it.each([
            '{% block a_b %}{% parent %}{% endblock %}',
            '{% block a_b %}{{ parent() }}{% endblock %}',
            '{% block a_b %}{%- parent -%}{% endblock %}',
        ])('refuses %s, which only base output cannot express', (twig) => {
            expect(transformTemplate(twig)).toEqual({ template: null, blockers: [TWIG_PARENT_BLOCKER] });
        });

        it('skips a component whose twig block wraps a named slot', async () => {
            const result = await convertFixture('sw-block-named-slot');

            expect(result).toEqual({ outcome: 'skipped', reasons: [SLOT_IN_BLOCK], sfc: null });
        });

        it('skips a component whose twig uses {% parent %}', async () => {
            const result = await convertFixture('sw-twig-parent');

            expect(result).toEqual({ outcome: 'skipped', reasons: [TWIG_PARENT_BLOCKER], sfc: null });
        });

        it('keeps a named slot that belongs to a child component inside the block', async () => {
            const result = await convertFixture('sw-slot-in-child');

            expect(result.outcome).toBe('full');
            expect(result.reasons).toEqual([]);
            expect(result.sfc).toContain('#modal-footer');
        });

        it('preserves function contracts and special JSDoc when rendering setup functions', async () => {
            const jsSource = `
                    import template from './sw-function-contracts.html.twig';

                    export default {
                        template,
                        methods: {
                            /** @deprecated @experimental @internal @private */
                            typed<T>(value: T): T {
                                return value;
                            },
                        },
                    };
                `;
            const templateImport = parse(jsSource, { sourceType: 'module', plugins: ['typescript'] }).program.body.find(
                (statement) => statement.type === 'ImportDeclaration',
            );

            if (!templateImport) {
                throw new Error('Contract fixture has no template import');
            }

            const result = await convertComponent({
                componentName: 'sw-function-contracts',
                jsSource,
                twigSource: '{% block sw_function_contracts %}<div />{% endblock %}',
                vuePath: 'sw-function-contracts.vue',
                lang: 'ts',
                templateImportRange: { start: templateImport.start as number, end: templateImport.end as number },
            });

            expect(result.outcome).toBe('full');
            expect(result.sfc).toContain('@deprecated @experimental @internal @private');
            expect(result.sfc).toMatch(
                /const typed =\s+\/\*\* @deprecated @experimental @internal @private \*\/\s+function <T>\(value: T\): T/,
            );
        });
    });

    describe('runMigration with --write (integration on a temporary component tree)', () => {
        let tmpDir: string;
        let result: MigrationResult;
        let beforeManifest: Record<string, Buffer>;

        const copyFixture = (name: string): void => {
            fs.cpSync(path.join(FIXTURES, name), path.join(tmpDir, name), { recursive: true });
        };

        const registrationOf = (name: string): string | undefined =>
            result.reports.find((entry) => entry.name === name)?.registration;

        beforeAll(async () => {
            tmpDir = fs.mkdtempSync(path.join(os.tmpdir(), 'sfc-migration-'));
            copyFixture('sw-simple-card');
            copyFixture('sw-partial-todos');
            copyFixture('sw-mixin-demo');
            // Both would migrate on their own; they are here to prove the registration kind, not
            // their contents, is what keeps them off the destructive path.
            copyFixture('sw-slot-in-child');
            copyFixture('sw-lifecycle-demo');
            // The module index registers components the way the real modules do; it carries no
            // component config itself, so it is never discovered as a component.
            fs.writeFileSync(
                path.join(tmpDir, 'index.js'),
                [
                    "Component.register('sw-simple-card', () => import('./sw-simple-card'));",
                    "Component.register('sw-partial-todos', () => import('./sw-partial-todos'));",
                    "Shopware.Component.extend('sw-slot-in-child', 'sw-simple-card', () => import('./sw-slot-in-child'));",
                    "Component.override('sw-lifecycle-demo', () => import('./sw-lifecycle-demo'));",
                    "Component.override('sw-simple-card', { template: '' });",
                    '',
                ].join('\n'),
            );

            beforeManifest = manifest(tmpDir);
            result = await runMigration(tmpDir, { write: true });
        });

        afterAll(() => {
            fs.rmSync(tmpDir, { recursive: true, force: true });
        });

        it('writes only a validated draft and retains every legacy byte', () => {
            const afterManifest = manifest(tmpDir);
            const newFiles = [
                'sw-partial-todos/sw-partial-todos.vue',
                'sw-simple-card/sw-simple-card.vue',
            ];

            expect(Object.keys(afterManifest)).toEqual(
                [
                    ...Object.keys(beforeManifest),
                    ...newFiles,
                ].sort(),
            );

            Object.entries(beforeManifest).forEach(
                ([
                    file,
                    bytes,
                ]) => {
                    expect(afterManifest[file]).toEqual(bytes);
                },
            );

            expect(afterManifest['sw-simple-card/sw-simple-card.vue']).toBeInstanceOf(Buffer);
            expect(afterManifest['sw-partial-todos/sw-partial-todos.vue']).toBeInstanceOf(Buffer);
        });

        it('writes a draft next to untouched originals for a partial migration', () => {
            const dir = path.join(tmpDir, 'sw-partial-todos');
            const original = fs.readFileSync(path.join(FIXTURES, 'sw-partial-todos', 'index.js'), 'utf8');

            expect(fs.existsSync(path.join(dir, 'sw-partial-todos.vue'))).toBe(true);
            expect(fs.readFileSync(path.join(dir, 'index.js'), 'utf8')).toBe(original);
            expect(fs.existsSync(path.join(dir, 'sw-partial-todos.html.twig'))).toBe(true);
        });

        it('writes nothing for a skipped component', () => {
            expect(fs.existsSync(path.join(tmpDir, 'sw-mixin-demo', 'sw-mixin-demo.vue'))).toBe(false);
        });

        it('classifies every reported component by its registration', () => {
            expect(registrationOf('sw-simple-card')).toBe('register');
            expect(registrationOf('sw-partial-todos')).toBe('register');
            expect(registrationOf('sw-slot-in-child')).toBe('extend');
            expect(registrationOf('sw-lifecycle-demo')).toBe('override');
            expect(registrationOf('sw-mixin-demo')).toBe('unregistered');
        });

        it.each([
            [
                'sw-slot-in-child',
                "Component.extend child of 'sw-simple-card' (inherits the parent template)",
            ],
            [
                'sw-lifecycle-demo',
                "Component.override registration (patches another component's template)",
            ],
        ])('leaves %s untouched, because its template is not self-contained', (name, reason) => {
            const dir = path.join(tmpDir, name);
            const entry = result.reports.find((report) => report.name === name);

            expect(entry?.outcome).toBe('skipped');
            expect(entry?.reasons).toEqual([reason]);
            expect(fs.existsSync(path.join(dir, `${name}.vue`))).toBe(false);
            expect(fs.existsSync(path.join(dir, `${name}.html.twig`))).toBe(true);
            expect(fs.readFileSync(path.join(dir, 'index.js'), 'utf8')).toBe(
                fs.readFileSync(path.join(FIXTURES, name, 'index.js'), 'utf8'),
            );
        });

        it('writes a draft only, for a component no registration resolves to', () => {
            const entry = result.reports.find((report) => report.name === 'sw-mixin-demo');

            // sw-mixin-demo is a hard skip for its own reasons; the downgrade is asserted through
            // the registration column, which is what gates the destructive path.
            expect(entry?.registration).toBe('unregistered');
        });

        it('carries the inline overrides through, which own no component directory', () => {
            expect(result.inlineOverrides).toHaveLength(1);
            expect(result.inlineOverrides[0]).toEqual({ file: path.join(tmpDir, 'index.js'), name: 'sw-simple-card' });
        });

        it('is idempotent: a second run reports already-migrated and re-converts nothing', async () => {
            const second = await runMigration(tmpDir, { write: true });

            // Both written drafts are now already-migrated; legacy sources remain discoverable and
            // the self-contained skips remain skipped.
            expect(second.stats).toEqual({
                full: 0,
                partial: 0,
                skipped: 3,
                alreadyMigrated: 2,
                error: 0,
            });
        });

        it('requires an explicit replacement option for an unambiguous full registration', async () => {
            const replacementRoot = fs.mkdtempSync(path.join(os.tmpdir(), 'sfc-migration-replace-'));

            try {
                fs.cpSync(path.join(FIXTURES, 'sw-created-pattern'), path.join(replacementRoot, 'sw-created-pattern'), {
                    recursive: true,
                });
                fs.writeFileSync(
                    path.join(replacementRoot, 'index.js'),
                    "Component.register('sw-created-pattern', () => import('./sw-created-pattern'));\n",
                );

                const replacement = await runMigration(replacementRoot, { write: true, replaceOriginals: true });
                const dir = path.join(replacementRoot, 'sw-created-pattern');
                const afterManifest = manifest(replacementRoot);

                expect(replacement.reports.find((entry) => entry.name === 'sw-created-pattern')).toMatchObject({
                    outcome: 'full',
                    registration: 'register',
                });
                expect(fs.readFileSync(path.join(dir, 'index.js'), 'utf8')).toContain(
                    "export { default } from './sw-created-pattern.vue';",
                );
                expect(fs.existsSync(path.join(dir, 'sw-created-pattern.vue'))).toBe(true);
                expect(fs.existsSync(path.join(dir, 'sw-created-pattern.html.twig'))).toBe(true);
                expect(Object.keys(afterManifest).sort()).toEqual(
                    [
                        'index.js',
                        'sw-created-pattern/index.js',
                        'sw-created-pattern/sw-created-pattern.html.twig',
                        'sw-created-pattern/sw-created-pattern.vue',
                    ].sort(),
                );
                expect(afterManifest['sw-created-pattern/sw-created-pattern.html.twig']).toEqual(
                    fs.readFileSync(path.join(FIXTURES, 'sw-created-pattern', 'sw-created-pattern.html.twig')),
                );
            } finally {
                fs.rmSync(replacementRoot, { recursive: true, force: true });
            }
        });
    });

    describe('runMigration name derivation (component names come from the registration)', () => {
        let tmpDir: string;
        let result: MigrationResult;

        const writeFile = (relativePath: string, contents: string): void => {
            const file = path.join(tmpDir, relativePath);

            fs.mkdirSync(path.dirname(file), { recursive: true });
            fs.writeFileSync(file, contents);
        };

        // One component: an `index.js` importing a twig next to it, and that twig.
        const writeComponent = (dir: string, twigName: string): void => {
            writeFile(
                `${dir}/index.js`,
                [
                    `import template from './${twigName}.html.twig';`,
                    '',
                    '/**',
                    ' * @sw-package framework',
                    ' */',
                    'export default {',
                    '    template,',
                    '',
                    '    data() {',
                    '        return { message: `Hello` };',
                    '    },',
                    '};',
                    '',
                ].join('\n'),
            );
            writeFile(
                `${dir}/${twigName}.html.twig`,
                `{% block ${twigName.replace(/-/g, '_')} %}\n    <p>{{ message }}</p>\n{% endblock %}\n`,
            );
        };

        const reportOf = (name: string): MigrationResult['reports'][number] | undefined =>
            result.reports.find((entry) => entry.name === name);

        beforeAll(async () => {
            tmpDir = fs.mkdtempSync(path.join(os.tmpdir(), 'sfc-migration-registry-'));

            // CMS block layout: the registering module index sits one level above the component.
            writeFile(
                'blocks/demo/index.js',
                "Shopware.Component.register('sw-cms-block-demo', () => import('./component'));\n",
            );
            writeComponent('blocks/demo/component', 'sw-cms-block-demo');

            // Page layout: the registration spells the index module out.
            writeFile('sw-login/index.js', "Component.register('sw-login', () => import('./page/index'));\n");
            writeComponent('sw-login/page', 'sw-login');

            // A registered name no second source confirms: the template is named differently.
            writeFile(
                'blocks/mismatch/index.js',
                "Component.register('sw-cms-block-mismatch', () => import('./component'));\n",
            );
            writeComponent('blocks/mismatch/component', 'sw-cms-block-other');

            // The same name registered for two directories.
            writeFile(
                'duplicates/index.js',
                [
                    "Component.register('sw-duplicate-demo', () => import('./first'));",
                    "Component.register('sw-duplicate-demo', () => import('./second'));",
                    '',
                ].join('\n'),
            );
            writeComponent('duplicates/first', 'sw-duplicate-demo');
            writeComponent('duplicates/second', 'sw-duplicate-demo');

            // Nothing registers this directory, so its basename stays the only name source.
            writeComponent('orphan/preview', 'sw-orphan-preview');

            result = await runMigration(tmpDir, { write: true });
        });

        afterAll(() => {
            fs.rmSync(tmpDir, { recursive: true, force: true });
        });

        it('migrates a CMS-block-like component under its registered name', () => {
            const dir = path.join(tmpDir, 'blocks', 'demo', 'component');

            expect(reportOf('sw-cms-block-demo')).toMatchObject({ outcome: 'full', registration: 'register' });
            expect(fs.existsSync(path.join(dir, 'sw-cms-block-demo.vue'))).toBe(true);
            expect(fs.readFileSync(path.join(dir, 'index.js'), 'utf8')).toContain('export default {');
            expect(fs.existsSync(path.join(dir, 'sw-cms-block-demo.html.twig'))).toBe(true);
        });

        it('migrates a page registered through its index module', () => {
            expect(reportOf('sw-login')).toMatchObject({ outcome: 'full', registration: 'register' });
            expect(fs.existsSync(path.join(tmpDir, 'sw-login', 'page', 'sw-login.vue'))).toBe(true);
        });

        it('skips a registered name the template filename does not confirm', () => {
            expect(reportOf('sw-cms-block-mismatch')).toMatchObject({
                outcome: 'skipped',
                reasons: ['template filename does not match the registered component name'],
            });
            expect(fs.existsSync(path.join(tmpDir, 'blocks', 'mismatch', 'component', 'sw-cms-block-mismatch.vue'))).toBe(
                false,
            );
        });

        it('skips every directory sharing a registered name', () => {
            const duplicates = result.reports.filter((entry) => entry.name === 'sw-duplicate-demo');

            expect(duplicates).toHaveLength(2);
            expect(duplicates.map((entry) => entry.outcome)).toEqual([
                'skipped',
                'skipped',
            ]);
            expect(duplicates.map((entry) => entry.reasons)).toEqual([
                ['component name registered more than once'],
                ['component name registered more than once'],
            ]);
        });

        it('keeps skipping an unregistered directory whose basename is not kebab-case', () => {
            expect(reportOf('preview')).toMatchObject({
                outcome: 'skipped',
                registration: 'unregistered',
                reasons: ['component name is not multi-segment kebab-case'],
            });
        });
    });
});
