/**
 * @sw-package framework
 */

import * as fs from 'fs';
import * as path from 'path';
import { spawnSync } from 'child_process';
import { runMigration, type MigrationResult } from './run-sfc-migration';
import { makeRoot, manifest, writeFile } from './spec-helpers';

const FIXTURES = path.join(__dirname, '__fixtures__');

/** One component: an `index.js` importing the Twig next to it, and that Twig. */
const writeComponent = (root: string, dir: string, twigName = path.basename(dir)): void => {
    writeFile(
        root,
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
            "        return { label: 'x' };",
            '    },',
            '};',
            '',
        ].join('\n'),
    );
    writeFile(
        root,
        `${dir}/${twigName}.html.twig`,
        `{% block ${twigName.replace(/-/g, '_')} %}\n    <p>{{ label }}</p>\n{% endblock %}\n`,
    );
};

const writeModuleIndex = (root: string, ...registrations: string[]): void => {
    writeFile(root, 'index.js', `${registrations.join('\n')}\n`);
};

const registerAll = (root: string, ...names: string[]): void =>
    writeModuleIndex(root, ...names.map((name) => `Component.register('${name}', () => import('./${name}'));`));

const reportOf = (result: MigrationResult, name: string): MigrationResult['reports'][number] | undefined =>
    result.reports.find((entry) => entry.name === name);

/** The working tree without git's own bookkeeping, which `git status` may refresh on its own. */
const trackedManifest = (root: string): Record<string, Buffer> =>
    Object.fromEntries(Object.entries(manifest(root)).filter(([file]) => !file.startsWith('.git/')));

describe('scripts/codemods/sfc-migration/run-sfc-migration', () => {
    describe('CLI', () => {
        let tmpDir: string;

        const runCli = (...args: string[]): { status: number | null; output: string } => {
            const result = spawnSync(
                process.execPath,
                [
                    '-r',
                    'ts-node/register/transpile-only',
                    path.join(__dirname, 'run-sfc-migration.ts'),
                    ...args,
                ],
                { cwd: process.cwd(), encoding: 'utf8' },
            );

            return { status: result.status, output: `${result.stdout ?? ''}${result.stderr ?? ''}` };
        };

        beforeEach(() => {
            tmpDir = makeRoot('sfc-cli-');
        });

        afterEach(() => {
            fs.rmSync(tmpDir, { recursive: true, force: true });
        });

        it.each([
            [
                'unknown flag',
                '--unknown',
            ],
            [
                'replacement without write',
                '--replace-originals',
            ],
            [
                'duplicate write',
                '--write',
                '--write',
            ],
        ])('rejects %s with a nonzero exit code', (_label, ...flags: string[]) => {
            const result = runCli(tmpDir, ...flags);

            expect(result.status).toBe(1);
            expect(result.output).toContain('Usage: npm run codemod:sfc-migration');
        });

        it('returns zero for a successful read-only empty scan', () => {
            const result = runCli(tmpDir);

            expect(result.status).toBe(0);
            expect(result.output).toContain('dry run — nothing written');
        });

        it('returns nonzero when the target is not a directory', () => {
            writeFile(tmpDir, 'not-a-directory.js', 'export default {};\n');

            const result = runCli(path.join(tmpDir, 'not-a-directory.js'));

            expect(result.status).toBe(1);
            expect(result.output).toContain('Not a directory:');
        });

        // `git checkout` is the undo button for a written run, which only works while the tree
        // carries nothing else — so the second run, dirtied by the first run's own draft, refuses.
        it('refuses to write into a target directory with uncommitted changes', () => {
            const git = (...args: string[]): void => {
                const result = spawnSync('git', args, { cwd: tmpDir, encoding: 'utf8' });

                expect(result.status).toBe(0);
            };

            writeComponent(tmpDir, 'sw-alpha-item');
            registerAll(tmpDir, 'sw-alpha-item');
            git('init');
            git('add', '.');
            git('-c', 'user.name=codemod', '-c', 'user.email=codemod@example.com', 'commit', '-m', 'baseline');

            const clean = runCli(tmpDir, '--write');

            expect(clean.status).toBe(0);
            expect(fs.existsSync(path.join(tmpDir, 'sw-alpha-item', 'sw-alpha-item.vue'))).toBe(true);

            const before = trackedManifest(tmpDir);
            const dirty = runCli(tmpDir, '--write');

            expect(dirty.status).toBe(1);
            expect(dirty.output).toContain('Refusing to write into a dirty working tree');
            expect(dirty.output).toContain('sw-alpha-item.vue');
            expect(trackedManifest(tmpDir)).toEqual(before);
        }, 120000);
    });

    describe('writing a synthesized component tree', () => {
        // Names chosen so the middle component proves the batch remains deterministic.
        const NAMES = [
            'sw-alpha-item',
            'sw-bravo-item',
            'sw-charlie-item',
        ];

        let tmpDir: string;

        beforeEach(() => {
            tmpDir = makeRoot('sfc-run-');
            NAMES.forEach((name) => writeComponent(tmpDir, name));
            registerAll(tmpDir, ...NAMES);
        });

        afterEach(() => {
            fs.rmSync(tmpDir, { recursive: true, force: true });
        });

        it('writes validated drafts while retaining every legacy source', async () => {
            const result = await runMigration(tmpDir, { write: true });

            expect(result.stats).toEqual({ full: 3, partial: 0, skipped: 0, 'already-migrated': 0, error: 0 });

            NAMES.forEach((name) => {
                expect(fs.existsSync(path.join(tmpDir, name, `${name}.vue`))).toBe(true);
                expect(fs.existsSync(path.join(tmpDir, name, `${name}.html.twig`))).toBe(true);
                expect(fs.readFileSync(path.join(tmpDir, name, 'index.js'), 'utf8')).toContain('export default {');
            });
        });

        it('replaces only in explicit replacement mode and retains Twig', async () => {
            const result = await runMigration(tmpDir, { write: true, replaceOriginals: true });

            expect(result.stats).toEqual({ full: 3, partial: 0, skipped: 0, 'already-migrated': 0, error: 0 });

            NAMES.forEach((name) => {
                expect(fs.readFileSync(path.join(tmpDir, name, 'index.js'), 'utf8')).toContain(
                    `export { default } from './${name}.vue';`,
                );
                expect(fs.existsSync(path.join(tmpDir, name, `${name}.html.twig`))).toBe(true);
            });
        });

        it('retains Twig even when another module imports it too', async () => {
            writeFile(
                tmpDir,
                'consumer.js',
                "import template from './sw-alpha-item/sw-alpha-item.html.twig';\n\nconsole.log(template);\n",
            );

            const result = await runMigration(tmpDir, { write: true });

            expect(reportOf(result, 'sw-alpha-item')).toMatchObject({ outcome: 'full', reasons: [] });
            expect(fs.existsSync(path.join(tmpDir, 'sw-alpha-item', 'sw-alpha-item.html.twig'))).toBe(true);
        });

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
        ])('reports an existing .vue as %s and rewrites nothing', async (_label, contents, expected) => {
            const vuePath = path.join(tmpDir, 'sw-alpha-item', 'sw-alpha-item.vue');

            fs.writeFileSync(vuePath, contents);

            const result = await runMigration(tmpDir, { write: true });

            expect(reportOf(result, 'sw-alpha-item')?.outcome).toBe('already-migrated');
            expect(reportOf(result, 'sw-alpha-item')?.reasons[0]).toMatch(expected);
            expect(fs.readFileSync(vuePath, 'utf8')).toBe(contents);
        });

        it('reports a template it cannot read and still migrates the rest', async () => {
            const twig = path.join(tmpDir, 'sw-bravo-item', 'sw-bravo-item.html.twig');

            fs.rmSync(twig);
            fs.mkdirSync(twig);

            const result = await runMigration(tmpDir, { write: true });

            expect(result.stats).toMatchObject({ full: 2, error: 1 });
            expect(reportOf(result, 'sw-bravo-item')?.reasons[0]).toContain('EISDIR');
        });

        // Everything outside the conversion itself — the precheck reads and stats — is guarded too,
        // so no single unreadable component can cost the report for the run.
        it('reports a failure from outside the conversion and still migrates the rest', async () => {
            fs.mkdirSync(path.join(tmpDir, 'sw-bravo-item', 'sw-bravo-item.vue'));

            const result = await runMigration(tmpDir, { write: true });

            expect(result.stats).toMatchObject({ full: 2, error: 1 });
            expect(reportOf(result, 'sw-bravo-item')?.reasons[0]).toMatch(/^unexpected failure:/);
        });
    });

    describe('a real fixture tree written with --write', () => {
        let tmpDir: string;
        let result: MigrationResult;
        let beforeManifest: Record<string, Buffer>;

        beforeAll(async () => {
            tmpDir = makeRoot('sfc-migration-');
            [
                'sw-simple-card',
                'sw-partial-todos',
                'sw-mixin-demo',
                // Both would migrate on their own; they are here to prove the registration kind, not
                // their contents, is what keeps them off the destructive path.
                'sw-slot-in-child',
                'sw-lifecycle-demo',
            ].forEach((name) => fs.cpSync(path.join(FIXTURES, name), path.join(tmpDir, name), { recursive: true }));
            // The module index registers components the way the real modules do; it carries no
            // component config itself, so it is never discovered as a component.
            writeModuleIndex(
                tmpDir,
                "Component.register('sw-simple-card', () => import('./sw-simple-card'));",
                "Component.register('sw-partial-todos', () => import('./sw-partial-todos'));",
                "Shopware.Component.extend('sw-slot-in-child', 'sw-simple-card', () => import('./sw-slot-in-child'));",
                "Component.override('sw-lifecycle-demo', () => import('./sw-lifecycle-demo'));",
                "Component.override('sw-simple-card', { template: '' });",
            );

            beforeManifest = manifest(tmpDir);
            result = await runMigration(tmpDir, { write: true });
        });

        afterAll(() => {
            fs.rmSync(tmpDir, { recursive: true, force: true });
        });

        it('adds only validated drafts and retains every legacy byte', () => {
            const afterManifest = manifest(tmpDir);

            expect(Object.keys(afterManifest)).toEqual(
                [
                    ...Object.keys(beforeManifest),
                    'sw-partial-todos/sw-partial-todos.vue',
                    'sw-simple-card/sw-simple-card.vue',
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
        });

        it('writes nothing for a skipped component', () => {
            expect(fs.existsSync(path.join(tmpDir, 'sw-mixin-demo', 'sw-mixin-demo.vue'))).toBe(false);
        });

        it('classifies every reported component by its registration', () => {
            expect(reportOf(result, 'sw-simple-card')?.registration).toBe('register');
            expect(reportOf(result, 'sw-partial-todos')?.registration).toBe('register');
            expect(reportOf(result, 'sw-slot-in-child')?.registration).toBe('extend');
            expect(reportOf(result, 'sw-lifecycle-demo')?.registration).toBe('override');
            // sw-mixin-demo is a hard skip for its own reasons; the unregistered class is what gates
            // the destructive path.
            expect(reportOf(result, 'sw-mixin-demo')?.registration).toBe('unregistered');
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
            expect(reportOf(result, name)).toMatchObject({ outcome: 'skipped', reasons: [reason] });
            expect(fs.existsSync(path.join(tmpDir, name, `${name}.vue`))).toBe(false);
            expect(fs.readFileSync(path.join(tmpDir, name, 'index.js'), 'utf8')).toBe(
                fs.readFileSync(path.join(FIXTURES, name, 'index.js'), 'utf8'),
            );
        });

        it('carries the inline overrides through, which own no component directory', () => {
            expect(result.inlineOverrides).toEqual([{ file: path.join(tmpDir, 'index.js'), name: 'sw-simple-card' }]);
        });

        it('is idempotent: a second run reports already-migrated and re-converts nothing', async () => {
            const second = await runMigration(tmpDir, { write: true });

            expect(second.stats).toEqual({ full: 0, partial: 0, skipped: 3, 'already-migrated': 2, error: 0 });
        });
    });

    it('requires an explicit replacement option for an unambiguous full registration', async () => {
        const root = makeRoot('sfc-migration-replace-');

        try {
            fs.cpSync(path.join(FIXTURES, 'sw-created-pattern'), path.join(root, 'sw-created-pattern'), {
                recursive: true,
            });
            writeModuleIndex(root, "Component.register('sw-created-pattern', () => import('./sw-created-pattern'));");

            const result = await runMigration(root, { write: true, replaceOriginals: true });
            const dir = path.join(root, 'sw-created-pattern');

            expect(reportOf(result, 'sw-created-pattern')).toMatchObject({ outcome: 'full', registration: 'register' });
            expect(fs.readFileSync(path.join(dir, 'index.js'), 'utf8')).toContain(
                "export { default } from './sw-created-pattern.vue';",
            );
            expect(Object.keys(manifest(root)).sort()).toEqual([
                'index.js',
                'sw-created-pattern/index.js',
                'sw-created-pattern/sw-created-pattern.html.twig',
                'sw-created-pattern/sw-created-pattern.vue',
            ]);
            expect(manifest(root)['sw-created-pattern/sw-created-pattern.html.twig']).toEqual(
                fs.readFileSync(path.join(FIXTURES, 'sw-created-pattern', 'sw-created-pattern.html.twig')),
            );
        } finally {
            fs.rmSync(root, { recursive: true, force: true });
        }
    });

    describe('component names come from the registration', () => {
        let tmpDir: string;
        let result: MigrationResult;

        beforeAll(async () => {
            tmpDir = makeRoot('sfc-migration-registry-');

            // CMS block layout: the registering module index sits one level above the component.
            writeFile(
                tmpDir,
                'blocks/demo/index.js',
                "Shopware.Component.register('sw-cms-block-demo', () => import('./component'));\n",
            );
            writeComponent(tmpDir, 'blocks/demo/component', 'sw-cms-block-demo');

            // Page layout: the registration spells the index module out.
            writeFile(tmpDir, 'sw-login/index.js', "Component.register('sw-login', () => import('./page/index'));\n");
            writeComponent(tmpDir, 'sw-login/page', 'sw-login');

            // A registered name no second source confirms: the template is named differently.
            writeFile(
                tmpDir,
                'blocks/mismatch/index.js',
                "Component.register('sw-cms-block-mismatch', () => import('./component'));\n",
            );
            writeComponent(tmpDir, 'blocks/mismatch/component', 'sw-cms-block-other');

            // The same name registered for two directories.
            writeFile(
                tmpDir,
                'duplicates/index.js',
                [
                    "Component.register('sw-duplicate-demo', () => import('./first'));",
                    "Component.register('sw-duplicate-demo', () => import('./second'));",
                    '',
                ].join('\n'),
            );
            writeComponent(tmpDir, 'duplicates/first', 'sw-duplicate-demo');
            writeComponent(tmpDir, 'duplicates/second', 'sw-duplicate-demo');

            // Nothing registers this directory, so its basename stays the only name source.
            writeComponent(tmpDir, 'orphan/preview', 'sw-orphan-preview');

            result = await runMigration(tmpDir, { write: true });
        });

        afterAll(() => {
            fs.rmSync(tmpDir, { recursive: true, force: true });
        });

        it('migrates a CMS-block-like component under its registered name', () => {
            const dir = path.join(tmpDir, 'blocks', 'demo', 'component');

            expect(reportOf(result, 'sw-cms-block-demo')).toMatchObject({ outcome: 'full', registration: 'register' });
            expect(fs.existsSync(path.join(dir, 'sw-cms-block-demo.vue'))).toBe(true);
            expect(fs.readFileSync(path.join(dir, 'index.js'), 'utf8')).toContain('export default {');
        });

        it('migrates a page registered through its index module', () => {
            expect(reportOf(result, 'sw-login')).toMatchObject({ outcome: 'full', registration: 'register' });
            expect(fs.existsSync(path.join(tmpDir, 'sw-login', 'page', 'sw-login.vue'))).toBe(true);
        });

        it('skips a registered name the template filename does not confirm', () => {
            expect(reportOf(result, 'sw-cms-block-mismatch')).toMatchObject({
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
            duplicates.forEach((entry) =>
                expect(entry).toMatchObject({ outcome: 'skipped', reasons: ['component name registered more than once'] }),
            );
        });

        it('keeps skipping an unregistered directory whose basename is not kebab-case', () => {
            expect(reportOf(result, 'preview')).toMatchObject({
                outcome: 'skipped',
                registration: 'unregistered',
                reasons: ['component name is not multi-segment kebab-case'],
            });
        });
    });
});
