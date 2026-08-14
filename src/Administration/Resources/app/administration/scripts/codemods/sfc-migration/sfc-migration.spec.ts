/**
 * @sw-package framework
 */

import * as fs from 'fs';
import * as os from 'os';
import * as path from 'path';
import { convertComponent, type ConvertResult } from './convert-component';
import { runMigration, type MigrationResult } from './run-sfc-migration';
import { transformTemplate } from './transform-template';

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

        it('converts {% parent %} and {{ parent() }} to <sw-block-parent />', () => {
            expect(transformTemplate('{% block a_b %}{{ parent() }}{% endblock %}').template).toBe(
                '<sw-block name="a_b"><sw-block-parent /></sw-block>',
            );
            expect(transformTemplate('{% block a_b %}{% parent %}{% endblock %}').template).toBe(
                '<sw-block name="a_b"><sw-block-parent /></sw-block>',
            );
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
});
