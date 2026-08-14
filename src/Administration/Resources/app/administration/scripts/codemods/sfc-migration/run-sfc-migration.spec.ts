/**
 * @sw-package framework
 */

import * as fs from 'fs';
import * as os from 'os';
import * as path from 'path';

import { runMigration } from './run-sfc-migration';

const SIMPLE_TWIG = '{% block sw_x %}\n    <div class="sw-x">{{ label }}</div>\n{% endblock %}\n';

describe('scripts/codemods/sfc-migration/run-sfc-migration', () => {
    let tmpDir: string;

    const componentDir = (name: string): string => path.join(tmpDir, name);

    const writeComponent = (name: string): void => {
        const dir = componentDir(name);

        fs.mkdirSync(dir, { recursive: true });
        fs.writeFileSync(
            path.join(dir, 'index.js'),
            [
                `import template from './${name}.html.twig';`,
                '',
                '/**',
                ' * @sw-package framework',
                ' */',
                'export default {',
                '    template,',
                '',
                '    data() {',
                "        return { label: 'x' };",
                '    },',
                '};',
                '',
            ].join('\n'),
        );
        fs.writeFileSync(path.join(dir, `${name}.html.twig`), SIMPLE_TWIG.replace('sw_x', name.replace(/-/g, '_')));
    };

    const registerAll = (...names: string[]): void => {
        fs.writeFileSync(
            path.join(tmpDir, 'index.js'),
            names.map((name) => `Component.register('${name}', () => import('./${name}'));`).join('\n') + '\n',
        );
    };

    const reportFor = (result: Awaited<ReturnType<typeof runMigration>>, name: string) =>
        result.reports.find((entry) => entry.name === name);

    beforeEach(() => {
        tmpDir = fs.mkdtempSync(path.join(os.tmpdir(), 'sfc-run-'));
    });

    afterEach(() => {
        fs.rmSync(tmpDir, { recursive: true, force: true });
    });

    describe('write failures', () => {
        // Names chosen so the middle component proves the batch remains deterministic.
        const NAMES = [
            'sw-alpha-item',
            'sw-bravo-item',
            'sw-charlie-item',
        ];

        beforeEach(() => {
            NAMES.forEach(writeComponent);
            registerAll(...NAMES);
        });

        it('writes validated drafts while retaining every legacy source', async () => {
            const result = await runMigration(tmpDir, { write: true });

            expect(result.stats).toEqual({ full: 3, partial: 0, skipped: 0, alreadyMigrated: 0, error: 0 });

            NAMES.forEach((name) => {
                const dir = componentDir(name);

                expect(fs.existsSync(path.join(dir, `${name}.vue`))).toBe(true);
                expect(fs.existsSync(path.join(dir, `${name}.html.twig`))).toBe(true);
                expect(fs.readFileSync(path.join(dir, 'index.js'), 'utf8')).toContain('export default {');
            });
        });

        it('replaces only in explicit replacement mode and retains Twig', async () => {
            const result = await runMigration(tmpDir, { write: true, replaceOriginals: true });

            expect(result.stats).toEqual({ full: 3, partial: 0, skipped: 0, alreadyMigrated: 0, error: 0 });

            NAMES.forEach((name) => {
                const dir = componentDir(name);

                expect(fs.readFileSync(path.join(dir, 'index.js'), 'utf8')).toContain(
                    `export { default } from './${name}.vue';`,
                );
                expect(fs.existsSync(path.join(dir, `${name}.html.twig`))).toBe(true);
            });
        });
    });

    describe('Twig retention', () => {
        it('retains Twig regardless of other imports', async () => {
            writeComponent('sw-shared-tpl');
            registerAll('sw-shared-tpl');
            fs.writeFileSync(
                path.join(tmpDir, 'consumer.js'),
                "import template from './sw-shared-tpl/sw-shared-tpl.html.twig';\n\nconsole.log(template);\n",
            );

            const result = await runMigration(tmpDir, { write: true });
            const entry = reportFor(result, 'sw-shared-tpl');

            expect(entry?.outcome).toBe('full');
            expect(entry?.reasons).toEqual([]);
            expect(fs.existsSync(path.join(componentDir('sw-shared-tpl'), 'sw-shared-tpl.html.twig'))).toBe(true);
        });
    });

    describe('an existing .vue', () => {
        it.each([
            [
                'draft from a previous run',
                '<template><div /></template>\n<script setup>\n// TODO(sfc-migration): x\n</script>\n',
                /^draft from a previous run:/,
            ],
            [
                'half-migrated',
                '<template><div /></template>\n<script setup>\nswDefinePublic({});\n</script>\n',
                /^half-migrated:/,
            ],
            [
                'foreign file',
                '<template><p>unrelated storybook demo</p></template>\n',
                /did not generate/,
            ],
        ])('reports %s distinctly', async (_label, contents, expected) => {
            writeComponent('sw-existing-vue');
            registerAll('sw-existing-vue');
            fs.writeFileSync(path.join(componentDir('sw-existing-vue'), 'sw-existing-vue.vue'), contents);

            const result = await runMigration(tmpDir, { write: true });
            const entry = reportFor(result, 'sw-existing-vue');

            expect(entry?.outcome).toBe('already-migrated');
            expect(entry?.reasons[0]).toMatch(expected);
            // Nothing is ever rewritten for an existing .vue.
            expect(fs.readFileSync(path.join(componentDir('sw-existing-vue'), 'sw-existing-vue.vue'), 'utf8')).toBe(
                contents,
            );
        });
    });

    describe('unreadable components', () => {
        beforeEach(() => {
            writeComponent('sw-alpha-item');
            writeComponent('sw-bravo-item');
            registerAll('sw-alpha-item', 'sw-bravo-item');
        });

        it('reports a template it cannot read and still migrates the rest', async () => {
            const twig = path.join(componentDir('sw-bravo-item'), 'sw-bravo-item.html.twig');

            fs.rmSync(twig);
            fs.mkdirSync(twig);

            const result = await runMigration(tmpDir, { write: true });

            expect(result.stats.full).toBe(1);
            expect(result.stats.error).toBe(1);
            expect(reportFor(result, 'sw-bravo-item')?.reasons[0]).toContain('EISDIR');
        });

        // Everything outside the conversion itself — the precheck reads and stats — is guarded too,
        // so no single unreadable component can cost the report for the run.
        it('reports a failure from outside the conversion and still migrates the rest', async () => {
            fs.mkdirSync(path.join(componentDir('sw-bravo-item'), 'sw-bravo-item.vue'));

            const result = await runMigration(tmpDir, { write: true });

            expect(result.stats.full).toBe(1);
            expect(result.stats.error).toBe(1);
            expect(reportFor(result, 'sw-bravo-item')?.reasons[0]).toMatch(/^unexpected failure:/);
        });
    });
});
