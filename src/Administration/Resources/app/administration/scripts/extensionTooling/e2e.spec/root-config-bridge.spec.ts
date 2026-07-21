/**
 * @sw-package framework
 *
 * End-to-end coverage for the root-config bridge mode: a multi-root extension
 * whose single package-level config already governs every Administration root
 * is bridged once beside that config — not once per root — and the one program
 * still covers every root. Existing configs are never overwritten and an
 * ambiguous layout refuses instead of guessing.
 */

import fs from 'fs';
import path from 'path';
import { checkExtensions } from '../check';
import { setupExtensionTooling } from '../setup';
import { SHIM_DIR_NAME } from '../shared';
import { cleanupTempProject, createTempProject, createVendorAdmin, writeFile, writePluginsConfig } from '../test-helpers';

const CHECK_TIMEOUT = 300000;

function countBridges(root: string): string[] {
    const found: string[] = [];
    const queue = [root];

    while (queue.length > 0) {
        const current = queue.shift() as string;

        for (const entry of fs.readdirSync(current, { withFileTypes: true })) {
            if (!entry.isDirectory() || entry.name === 'node_modules') {
                continue;
            }

            if (entry.name === SHIM_DIR_NAME && fs.existsSync(path.join(current, entry.name, 'tsconfig.json'))) {
                found.push(path.relative(root, path.join(current, entry.name)));
            }

            queue.push(path.join(current, entry.name));
        }
    }

    return found.sort();
}

describe('extension tooling root-config bridge (e2e)', () => {
    it(
        'auto-detects a shared package config, bridges once, and covers every root',
        async () => {
            const projectRoot = createTempProject('sw-tooling-root-config-');
            const administrationRoot = createVendorAdmin(projectRoot, { entitySchema: 'real' });
            const extensionRoot = path.join(projectRoot, 'custom/plugins/RootSuite');
            const bundleBMain = path.join(extensionRoot, 'src/BundleB/Resources/app/administration/src/main.ts');

            try {
                writeFile(path.join(extensionRoot, 'composer.json'), '{}\n');
                writeFile(path.join(extensionRoot, 'src/BundleA/Resources/app/administration/src/main.ts'), [
                    'export const bundleAValue: number = 1;',
                ]);
                writeFile(bundleBMain, ['export const bundleBValue: number = 2;']);

                // A pre-existing package-level config governing both roots — the
                // exact shape the DX report's monorepo plugin ships.
                writeFile(path.join(extensionRoot, 'tsconfig.json'), [
                    '{',
                    '    "extends": "./.shopware-admin/tsconfig.json",',
                    '    "include": ["src/*/Resources/app/administration/src/**/*.ts"]',
                    '}',
                ]);
                writeFile(path.join(extensionRoot, 'eslint.config.mjs'), [
                    "import shopware from './.shopware-admin/eslint.mjs';",
                    '',
                    'export default [...shopware];',
                ]);
                writePluginsConfig(projectRoot, [
                    {
                        technicalName: 'RootSuiteA',
                        basePath: 'custom/plugins/RootSuite/src/BundleA',
                        administrationPath: 'Resources/app/administration/src',
                    },
                    {
                        technicalName: 'RootSuiteB',
                        basePath: 'custom/plugins/RootSuite/src/BundleB',
                        administrationPath: 'Resources/app/administration/src',
                    },
                ]);

                // No --root-config: the single shared config is auto-detected.
                const setup = setupExtensionTooling({ projectRoot, administrationRoot, shim: 'RootSuite' });

                // Exactly one bridge, beside the package config — not one per root.
                expect(countBridges(extensionRoot)).toEqual([SHIM_DIR_NAME]);
                // No per-root committable scaffolds were written into the bundles.
                expect(
                    fs.existsSync(path.join(extensionRoot, 'src/BundleA/Resources/app/administration/tsconfig.json')),
                ).toBe(false);
                expect(
                    fs.existsSync(path.join(extensionRoot, 'src/BundleB/Resources/app/administration/tsconfig.json')),
                ).toBe(false);
                // The pre-existing package config was left untouched.
                expect(fs.readFileSync(path.join(extensionRoot, 'tsconfig.json'), 'utf8')).not.toContain('@generated');
                expect(setup.warnings.some((warning) => warning.includes('eslint.config.mjs already exists'))).toBe(true);

                const passing = await checkExtensions({ projectRoot, administrationRoot, only: 'RootSuite' });
                const passingResult = passing.results[0];

                expect(passingResult.project.targets).toHaveLength(2);
                // One shared program covers both roots.
                expect(passingResult.commands.typescript).toHaveLength(1);
                expect(passingResult.typescript.status).toBe('passed');
                expect(passingResult.eslint.status).toBe('passed');
                expect(passing.exitCode).toBe(0);

                // A type error in the second root is caught by the single program.
                writeFile(bundleBMain, ["export const bundleBValue: number = 'not a number';"]);

                const failing = await checkExtensions({ projectRoot, administrationRoot, only: 'RootSuite' });

                expect(failing.results[0].typescript.status).toBe('failed');
                expect(failing.results[0].typescript.output).toContain('BundleB');
                expect(failing.results[0].typescript.output).toContain('TS2322');
                expect(failing.exitCode).toBe(1);
            } finally {
                cleanupTempProject(projectRoot);
            }
        },
        CHECK_TIMEOUT,
    );

    it(
        'scaffolds a single committable root config when --root-config points at a config-less package',
        async () => {
            const projectRoot = createTempProject('sw-tooling-root-scaffold-');
            const administrationRoot = createVendorAdmin(projectRoot, { entitySchema: 'real' });
            const extensionRoot = path.join(projectRoot, 'custom/plugins/RootScaffold');

            try {
                writeFile(path.join(extensionRoot, 'composer.json'), '{}\n');

                for (const bundle of [
                    'BundleA',
                    'BundleB',
                ]) {
                    writeFile(path.join(extensionRoot, `src/${bundle}/Resources/app/administration/src/main.ts`), [
                        `export const ${bundle.toLowerCase()}Value: number = 1;`,
                    ]);
                }

                writePluginsConfig(projectRoot, [
                    {
                        technicalName: 'RootScaffoldA',
                        basePath: 'custom/plugins/RootScaffold/src/BundleA',
                        administrationPath: 'Resources/app/administration/src',
                    },
                    {
                        technicalName: 'RootScaffoldB',
                        basePath: 'custom/plugins/RootScaffold/src/BundleB',
                        administrationPath: 'Resources/app/administration/src',
                    },
                ]);

                setupExtensionTooling({ projectRoot, administrationRoot, shim: 'RootScaffold', rootConfig: '.' });

                expect(countBridges(extensionRoot)).toEqual([SHIM_DIR_NAME]);

                // One committable root config was scaffolded, including every root.
                const rootTsconfig = fs.readFileSync(path.join(extensionRoot, 'tsconfig.json'), 'utf8');

                expect(rootTsconfig).toContain('"extends": "./.shopware-admin/tsconfig.json"');
                expect(rootTsconfig).toContain('src/BundleA/Resources/app/administration/src/**/*.ts');
                expect(rootTsconfig).toContain('src/BundleB/Resources/app/administration/src/**/*.ts');

                const check = await checkExtensions({ projectRoot, administrationRoot, only: 'RootScaffold' });

                expect(check.results[0].commands.typescript).toHaveLength(1);
                expect(check.results[0].typescript.status).toBe('passed');
                expect(check.results[0].eslint.status).toBe('passed');
                expect(check.exitCode).toBe(0);
            } finally {
                cleanupTempProject(projectRoot);
            }
        },
        CHECK_TIMEOUT,
    );

    it('refuses to guess when two package configs each govern several roots', () => {
        const projectRoot = createTempProject('sw-tooling-root-ambiguous-');
        const administrationRoot = createVendorAdmin(projectRoot, { entitySchema: 'real' });
        const extensionRoot = path.join(projectRoot, 'custom/plugins/AmbiguousSuite');

        try {
            writeFile(path.join(extensionRoot, 'composer.json'), '{}\n');

            for (const [
                group,
                bundle,
            ] of [
                [
                    'GroupOne',
                    'BundleA',
                ],
                [
                    'GroupOne',
                    'BundleB',
                ],
                [
                    'GroupTwo',
                    'BundleC',
                ],
                [
                    'GroupTwo',
                    'BundleD',
                ],
            ]) {
                writeFile(path.join(extensionRoot, `src/${group}/${bundle}/Resources/app/administration/src/main.ts`), [
                    'export const value: number = 1;',
                ]);
            }

            // Two competing package-level configs, one per group.
            writeFile(path.join(extensionRoot, 'src/GroupOne/tsconfig.json'), ['{ "files": [] }']);
            writeFile(path.join(extensionRoot, 'src/GroupTwo/tsconfig.json'), ['{ "files": [] }']);

            writePluginsConfig(
                projectRoot,
                [
                    [
                        'GroupOne',
                        'BundleA',
                    ],
                    [
                        'GroupOne',
                        'BundleB',
                    ],
                    [
                        'GroupTwo',
                        'BundleC',
                    ],
                    [
                        'GroupTwo',
                        'BundleD',
                    ],
                ].map(
                    ([
                        group,
                        bundle,
                    ]) => ({
                        technicalName: `Ambiguous${bundle}`,
                        basePath: `custom/plugins/AmbiguousSuite/src/${group}/${bundle}`,
                        administrationPath: 'Resources/app/administration/src',
                    }),
                ),
            );

            expect(() => setupExtensionTooling({ projectRoot, administrationRoot, shim: 'AmbiguousSuite' })).toThrow(
                /more than one package-level config.*--root-config/s,
            );
        } finally {
            cleanupTempProject(projectRoot);
        }
    });
});
