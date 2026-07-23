/**
 * @sw-package framework
 *
 * End-to-end coverage for multi-root extensions whose Administration roots
 * resolve to distinct configs. A diagnostic in the second root must never be
 * hidden behind the first target's program.
 */

import path from 'path';
import { checkExtensions } from '../check';
import { setupExtensionTooling } from '../setup';
import { asRelativeSpecifier } from '../shared';
import { cleanupTempProject, createTempProject, createVendorAdmin, writeFile, writePluginsConfig } from '../test-helpers';

const CHECK_TIMEOUT = 300000;

describe('extension tooling multi-target coverage (e2e)', () => {
    let projectRoot: string;
    let administrationRoot: string;

    beforeAll(() => {
        projectRoot = createTempProject('sw-tooling-multi-target-');
        administrationRoot = createVendorAdmin(projectRoot, { entitySchema: 'real' });

        writeFile(path.join(projectRoot, 'custom/plugins/Suite/composer.json'), '{}\n');
        writeFile(path.join(projectRoot, 'custom/plugins/Suite/src/BundleA/Resources/app/administration/src/main.ts'), [
            'export const firstRootValue: number = 1;',
        ]);
        writeFile(path.join(projectRoot, 'custom/plugins/Suite/src/BundleB/Resources/app/administration/src/main.ts'), [
            "export const secondRootValue: number = 'not a number';",
        ]);
        writePluginsConfig(projectRoot, [
            {
                technicalName: 'SuiteA',
                basePath: 'custom/plugins/Suite/src/BundleA',
                administrationPath: 'Resources/app/administration/src',
            },
            {
                technicalName: 'SuiteB',
                basePath: 'custom/plugins/Suite/src/BundleB',
                administrationPath: 'Resources/app/administration/src',
            },
        ]);

        setupExtensionTooling({ projectRoot, administrationRoot, shim: 'Suite' });
    }, CHECK_TIMEOUT);

    afterAll(() => {
        cleanupTempProject(projectRoot);
    });

    it(
        'runs every distinct target program and reports a finding from the second root',
        async () => {
            const check = await checkExtensions({ projectRoot, administrationRoot, only: 'Suite' });
            const result = check.results[0];

            expect(result.project.targets).toHaveLength(2);
            expect(result.project.targets.map((target) => target.technicalNames)).toEqual([
                ['SuiteA'],
                ['SuiteB'],
            ]);
            expect(result.commands.typescript).toHaveLength(2);
            expect(result.typescript.status).toBe('failed');
            expect(result.typescript.output).toContain('BundleB');
            expect(result.typescript.output).toContain('TS2322');
            expect(check.exitCode).toBe(1);
        },
        CHECK_TIMEOUT,
    );

    it(
        'detects a diagnostic in every root of a zero-config multi-root project',
        async () => {
            const zeroConfigRoot = createTempProject('sw-tooling-multi-zeroconfig-');
            const zeroConfigAdmin = createVendorAdmin(zeroConfigRoot, { entitySchema: 'real' });
            const extensionRoot = path.join(zeroConfigRoot, 'custom/plugins/ZeroSuite');

            try {
                writeFile(path.join(extensionRoot, 'composer.json'), '{}\n');

                // Both roots carry their own type error; neither may hide behind the other.
                writeFile(path.join(extensionRoot, 'src/BundleA/Resources/app/administration/src/main.ts'), [
                    "export const bundleAValue: number = 'not a number';",
                ]);
                writeFile(path.join(extensionRoot, 'src/BundleB/Resources/app/administration/src/main.ts'), [
                    "export const bundleBValue: number = 'also not a number';",
                ]);

                writePluginsConfig(zeroConfigRoot, [
                    {
                        technicalName: 'ZeroSuiteA',
                        basePath: 'custom/plugins/ZeroSuite/src/BundleA',
                        administrationPath: 'Resources/app/administration/src',
                    },
                    {
                        technicalName: 'ZeroSuiteB',
                        basePath: 'custom/plugins/ZeroSuite/src/BundleB',
                        administrationPath: 'Resources/app/administration/src',
                    },
                ]);

                // No shim: the managed per-target programs are the zero-config default.
                setupExtensionTooling({ projectRoot: zeroConfigRoot, administrationRoot: zeroConfigAdmin });

                const check = await checkExtensions({
                    projectRoot: zeroConfigRoot,
                    administrationRoot: zeroConfigAdmin,
                    only: 'ZeroSuite',
                });
                const result = check.results[0];

                expect(result.project.targets).toHaveLength(2);
                expect(result.tsResolution.mode).toBe('managed');
                expect(result.commands.typescript).toHaveLength(2);
                expect(result.typescript.status).toBe('failed');
                expect(result.typescript.output).toContain('BundleA');
                expect(result.typescript.output).toContain('BundleB');
                expect(result.typescript.output).toContain('TS2322');
                expect(check.exitCode).toBe(1);
            } finally {
                cleanupTempProject(zeroConfigRoot);
            }
        },
        CHECK_TIMEOUT,
    );

    it(
        'runs one shared package config once and rejects it until it covers every target',
        async () => {
            const sharedProjectRoot = createTempProject('sw-tooling-shared-target-');
            const sharedAdministrationRoot = createVendorAdmin(sharedProjectRoot, { entitySchema: 'real' });
            const extensionRoot = path.join(sharedProjectRoot, 'custom/plugins/SharedSuite');
            const rootTsconfig = path.join(extensionRoot, 'tsconfig.json');
            const bridgeTsconfig = path.join(extensionRoot, '.shopware-admin/tsconfig.json');

            try {
                writeFile(path.join(extensionRoot, 'composer.json'), '{}\n');

                for (const bundleName of [
                    'BundleA',
                    'BundleB',
                ]) {
                    writeFile(path.join(extensionRoot, `src/${bundleName}/Resources/app/administration/src/main.ts`), [
                        `export const ${bundleName.toLowerCase()}Value: number = 1;`,
                    ]);
                }

                writeFile(
                    bridgeTsconfig,
                    JSON.stringify(
                        {
                            extends: asRelativeSpecifier(
                                bridgeTsconfig,
                                path.join(sharedAdministrationRoot, 'extension-tooling/tsconfig.base.json'),
                            ),
                            files: [
                                asRelativeSpecifier(
                                    bridgeTsconfig,
                                    path.join(sharedAdministrationRoot, 'extension-tooling/admin-types.d.ts'),
                                ),
                            ],
                        },
                        null,
                        4,
                    ),
                );
                writeFile(rootTsconfig, [
                    '{',
                    '    "extends": "./.shopware-admin/tsconfig.json",',
                    '    "include": ["src/BundleA/Resources/app/administration/src/**/*.ts"]',
                    '}',
                ]);
                writePluginsConfig(sharedProjectRoot, [
                    {
                        technicalName: 'SharedSuiteA',
                        basePath: 'custom/plugins/SharedSuite/src/BundleA',
                        administrationPath: 'Resources/app/administration/src',
                    },
                    {
                        technicalName: 'SharedSuiteB',
                        basePath: 'custom/plugins/SharedSuite/src/BundleB',
                        administrationPath: 'Resources/app/administration/src',
                    },
                ]);

                const incomplete = await checkExtensions({
                    projectRoot: sharedProjectRoot,
                    administrationRoot: sharedAdministrationRoot,
                    only: 'SharedSuite',
                });

                expect(incomplete.results[0].typescript.status).toBe('tooling-error');
                expect(incomplete.results[0].typescript.output).toContain('does not cover');
                expect(incomplete.results[0].typescript.output).toContain('BundleB');

                writeFile(rootTsconfig, [
                    '{',
                    '    "extends": "./.shopware-admin/tsconfig.json",',
                    '    "include": ["src/*/Resources/app/administration/src/**/*.ts"]',
                    '}',
                ]);

                const complete = await checkExtensions({
                    projectRoot: sharedProjectRoot,
                    administrationRoot: sharedAdministrationRoot,
                    only: 'SharedSuite',
                });

                expect(complete.results[0].typescript.status).toBe('passed');
                expect(complete.results[0].commands.typescript).toHaveLength(1);
                expect(complete.exitCode).toBe(0);
            } finally {
                cleanupTempProject(sharedProjectRoot);
            }
        },
        CHECK_TIMEOUT,
    );
});
