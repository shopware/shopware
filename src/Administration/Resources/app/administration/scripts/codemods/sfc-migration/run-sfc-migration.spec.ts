/**
 * @sw-package framework
 */

import * as fs from 'fs';
import type * as fsTypes from 'fs';
import * as os from 'os';
import * as path from 'path';

/**
 * Arms a write failure for exact paths. A module factory rather than `jest.spyOn(fs, …)`, because
 * the runner does `import * as fs`, which the swc transform compiles to a namespace *copy* — a spy
 * installed on the real module afterwards would not be observed.
 */
const mockFailingWrites = new Set<string>();

jest.mock('fs', () => {
    const actual = jest.requireActual<typeof fsTypes>('fs');

    return {
        ...actual,
        writeFileSync: (file: fs.PathOrFileDescriptor, data: string, options?: unknown) => {
            if (mockFailingWrites.has(String(file))) {
                throw Object.assign(new Error(`EACCES: permission denied, open '${String(file)}'`), { code: 'EACCES' });
            }

            return actual.writeFileSync(file as string, data, options as never);
        },
    };
});

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
        mockFailingWrites.clear();
    });

    afterEach(() => {
        mockFailingWrites.clear();
        fs.rmSync(tmpDir, { recursive: true, force: true });
    });

    describe('write failures', () => {
        // Names chosen so the failing component sorts in the middle of globSync(...).sort().
        const NAMES = [
            'sw-alpha-item',
            'sw-bravo-item',
            'sw-charlie-item',
        ];

        beforeEach(() => {
            NAMES.forEach(writeComponent);
            registerAll(...NAMES);
        });

        it('keeps going, reports the failure and rolls the .vue back when the shim write fails', async () => {
            mockFailingWrites.add(path.join(componentDir('sw-bravo-item'), 'index.js'));

            const result = await runMigration(tmpDir, { write: true });

            // The run completes and the other two components are still migrated and reported.
            expect(result.stats).toEqual({ full: 2, partial: 0, skipped: 0, alreadyMigrated: 0, error: 1 });

            const failed = reportFor(result, 'sw-bravo-item');

            expect(failed?.outcome).toBe('error');
            expect(failed?.reasons[0]).toMatch(/^write failed at index\.js shim:/);
            expect(failed?.reasons[0]).toContain('on disk: nothing changed');

            // Rolled back to exactly the pre-run state, so a re-run can retry it.
            const dir = componentDir('sw-bravo-item');

            expect(fs.existsSync(path.join(dir, 'sw-bravo-item.vue'))).toBe(false);
            expect(fs.existsSync(path.join(dir, 'sw-bravo-item.html.twig'))).toBe(true);
            expect(fs.readFileSync(path.join(dir, 'index.js'), 'utf8')).toContain('export default {');
        });

        it('changes nothing on disk when the .vue write itself fails', async () => {
            mockFailingWrites.add(path.join(componentDir('sw-bravo-item'), 'sw-bravo-item.vue'));

            const result = await runMigration(tmpDir, { write: true });
            const failed = reportFor(result, 'sw-bravo-item');

            expect(result.stats.error).toBe(1);
            expect(failed?.reasons[0]).toMatch(/^write failed at \.vue write:/);
            expect(failed?.reasons[0]).toContain('on disk: nothing changed');
            expect(fs.existsSync(path.join(componentDir('sw-bravo-item'), 'sw-bravo-item.html.twig'))).toBe(true);
        });
    });

    describe('twig deletion guard', () => {
        it('keeps a twig another file still imports, and says so', async () => {
            writeComponent('sw-shared-tpl');
            registerAll('sw-shared-tpl');
            // No default export, so it is never discovered as a component of its own.
            fs.writeFileSync(
                path.join(tmpDir, 'consumer.js'),
                "import template from './sw-shared-tpl/sw-shared-tpl.html.twig';\n\nconsole.log(template);\n",
            );

            const result = await runMigration(tmpDir, { write: true });
            const entry = reportFor(result, 'sw-shared-tpl');

            expect(entry?.outcome).toBe('full');
            expect(entry?.reasons).toContain('twig kept: still imported by 1 other file(s)');
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
