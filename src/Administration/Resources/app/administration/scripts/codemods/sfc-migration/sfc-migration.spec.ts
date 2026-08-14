/**
 * @sw-package framework
 */

import * as fs from 'fs';
import * as os from 'os';
import * as path from 'path';
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

async function convertFixture(name: string): Promise<ConvertResult> {
    const { indexPath, twigPath } = fixturePaths(name);

    return convertComponent({
        jsSource: fs.readFileSync(indexPath, 'utf8'),
        twigSource: fs.readFileSync(twigPath, 'utf8'),
        componentName: name,
        vuePath: path.join(FIXTURES, name, `${name}.vue`),
        lang: indexPath.endsWith('.ts') ? 'ts' : 'js',
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
        it('fully migrates a component without hard features', async () => {
            const result = await convertFixture('sw-simple-card');

            expect(result.outcome).toBe('full');
            expect(result.reasons).toEqual([]);
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

        it('reports module-level code that would run once per component instance', async () => {
            const result = await convertFixture('sw-module-level-code');

            expect(result.outcome).toBe('partial');
            expect(result.reasons).toEqual(
                expect.arrayContaining([
                    expect.stringContaining('module-level code outside the default export'),
                ]),
            );
        });

        // The `const { X } = Shopware` prelude is by far the most common shape in src/; widening the
        // allowlist check to reject it would downgrade more than half of all components.
        it('keeps a pure Shopware-namespace prelude a full migration', async () => {
            const result = await convertFixture('sw-wrap-config');

            expect(result.outcome).toBe('full');
            expect(result.reasons).toEqual([]);
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
    });

    describe('runMigration with --write (integration on a temporary component tree)', () => {
        let tmpDir: string;
        let result: MigrationResult;

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
            // The module index registers components the way the real modules do; it carries no
            // component config itself, so it is never discovered as a component.
            fs.writeFileSync(
                path.join(tmpDir, 'index.js'),
                [
                    "Component.register('sw-simple-card', () => import('./sw-simple-card'));",
                    "Shopware.Component.extend('sw-partial-todos', 'sw-simple-card', () => import('./sw-partial-todos'));",
                    "Component.override('sw-simple-card', { template: '' });",
                    '',
                ].join('\n'),
            );

            result = await runMigration(tmpDir, { write: true });
        });

        afterAll(() => {
            fs.rmSync(tmpDir, { recursive: true, force: true });
        });

        it('swaps the files of a fully migrated component', () => {
            const dir = path.join(tmpDir, 'sw-simple-card');

            expect(fs.existsSync(path.join(dir, 'sw-simple-card.vue'))).toBe(true);
            expect(fs.readFileSync(path.join(dir, 'index.js'), 'utf8')).toBe(
                "/**\n * @sw-package framework\n *\n * @private\n */\nexport { default } from './sw-simple-card.vue';\n",
            );
            expect(fs.existsSync(path.join(dir, 'sw-simple-card.html.twig'))).toBe(false);
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
            expect(registrationOf('sw-partial-todos')).toBe('extend');
            expect(registrationOf('sw-mixin-demo')).toBe('unregistered');
        });

        it('carries the inline overrides through, which own no component directory', () => {
            expect(result.inlineOverrides).toHaveLength(1);
            expect(result.inlineOverrides[0]).toEqual({ file: path.join(tmpDir, 'index.js'), name: 'sw-simple-card' });
        });

        it('is idempotent: a second run reports already-migrated and re-converts nothing', async () => {
            const second = await runMigration(tmpDir, { write: true });

            // sw-simple-card's index.js is now a shim without a component config, so it is not
            // discovered at all; the partial draft reports as already migrated.
            expect(second.stats).toEqual({
                full: 0,
                partial: 0,
                skipped: 1,
                alreadyMigrated: 1,
                error: 0,
            });
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
            expect(fs.readFileSync(path.join(dir, 'index.js'), 'utf8')).toContain(
                "export { default } from './sw-cms-block-demo.vue';",
            );
            expect(fs.existsSync(path.join(dir, 'sw-cms-block-demo.html.twig'))).toBe(false);
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
