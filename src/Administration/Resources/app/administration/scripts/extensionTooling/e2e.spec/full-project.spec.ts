/**
 * @sw-package framework
 *
 * End-to-end suite: a temp Flex-style project with a vendor-installed
 * Administration, checked and built with the real toolchain.
 */

import { spawnSync } from 'child_process';
import fs from 'fs';
import path from 'path';
import { checkExtensions } from '../check';
import { setupExtensionTooling } from '../setup';
import type { SetupExtensionToolingResult } from '../setup';
import {
    cleanupTempProject,
    createTempProject,
    createVendorAdmin,
    realAdministrationRoot,
    writeConvertedEntitySchema,
    writeFile,
    writePluginsConfig,
    writeZeroConfigPlugin,
} from '../test-helpers';

const CHECK_TIMEOUT = 300000;

function hasLineMatching(output: string, ...fragments: string[]): boolean {
    return output.split('\n').some((line) => fragments.every((fragment) => line.includes(fragment)));
}

describe('scripts/extensionTooling e2e', () => {
    let projectRoot: string;
    let administrationRoot: string;
    let setupResult: SetupExtensionToolingResult;
    let zeroConfigMainPath: string;
    let originalZeroConfigMain: string;

    beforeAll(() => {
        projectRoot = createTempProject('sw-tooling-e2e-');
        administrationRoot = createVendorAdmin(projectRoot, { entitySchema: 'real' });

        writeZeroConfigPlugin({ projectRoot, pluginPath: 'custom/plugins/ZeroConfig' });
        zeroConfigMainPath = path.join(
            projectRoot,
            'custom/plugins/ZeroConfig/src/Resources/app/administration/src/main.ts',
        );
        originalZeroConfigMain = fs.readFileSync(zeroConfigMainPath, 'utf8');

        // Spec files are now type-checked by the dedicated spec program with jest
        // types — a clean spec that uses jest globals and the admin type surface
        // must therefore pass (the type-error case lives in typed-specs.spec.ts).
        writeFile(path.join(projectRoot, 'custom/plugins/ZeroConfig/src/Resources/app/administration/src/main.spec.ts'), [
            "describe('loadProduct', () => {",
            "    it('is typed against the admin type surface', () => {",
            "        const productId: string = 'product-id';",
            '        expect(productId).toBe(productId);',
            '    });',
            '});',
        ]);

        // Committed-config plugin: files extend the generated shim.
        const shimAdminFolder = path.join(projectRoot, 'custom/plugins/ShimConfig/src/Resources/app/administration');

        writeFile(path.join(projectRoot, 'custom/plugins/ShimConfig/composer.json'), '{}\n');
        writeFile(path.join(shimAdminFolder, 'src/main.ts'), [
            "export const shimmedRepository = Shopware.Service('repositoryFactory').create('product');",
        ]);
        writeFile(path.join(shimAdminFolder, 'tsconfig.json'), [
            '{',
            '    "extends": "./.shopware/tsconfig.json",',
            '    "include": ["src/**/*.ts", "src/**/*.vue"]',
            '}',
        ]);
        writeFile(path.join(shimAdminFolder, 'eslint.config.mjs'), [
            "import shopware from './.shopware/eslint.mjs';",
            '',
            'export default [',
            '    ...shopware,',
            "    { files: ['**/*.ts'], rules: { 'no-console': 'error' } },",
            '];',
        ]);

        // Multi-bundle suite under one composer root.
        writeFile(path.join(projectRoot, 'custom/plugins/Suite/composer.json'), '{}\n');

        for (const bundleName of [
            'BundleA',
            'BundleB',
        ]) {
            writeFile(
                path.join(projectRoot, 'custom/plugins/Suite/src', bundleName, 'Resources/app/administration/src/main.ts'),
                ['export const suiteCriteria = new Shopware.Data.Criteria(1, 25);'],
            );
        }

        // Vendor-installed extension (read-only, non-fatal) with a type error.
        writeFile(path.join(projectRoot, 'vendor/acme/vendor-admin/composer.json'), '{}\n');
        writeFile(path.join(projectRoot, 'vendor/acme/vendor-admin/src/Resources/app/administration/src/broken.ts'), [
            "export const brokenVendorValue: number = 'broken';",
        ]);
        writeFile(path.join(projectRoot, 'vendor/acme/vendor-admin/src/Resources/app/administration/src/broken.js'), [
            'const unusedVendorValue = 1;',
        ]);

        // JavaScript-only plugin: a TypeScript "pass" would be vacuous.
        writeFile(path.join(projectRoot, 'custom/plugins/JsOnly/composer.json'), '{}\n');
        writeFile(path.join(projectRoot, 'custom/plugins/JsOnly/src/Resources/app/administration/src/main.js'), [
            'export default {};',
        ]);

        // Server-side Twig outside every extension root — the Twig-Vue
        // processor must never touch this.
        writeFile(path.join(projectRoot, 'src/RealServerTwig/template.html.twig'), [
            '{% block page %}<div>{{ notVue|raw }}</div>{% endblock %}',
        ]);

        writePluginsConfig(projectRoot, [
            {
                technicalName: 'ZeroConfig',
                basePath: 'custom/plugins/ZeroConfig/src',
                administrationPath: 'Resources/app/administration/src',
                entryFilePath: 'Resources/app/administration/src/main.ts',
            },
            {
                technicalName: 'ShimConfig',
                basePath: 'custom/plugins/ShimConfig/src',
                administrationPath: 'Resources/app/administration/src',
            },
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
            {
                technicalName: 'VendorAdmin',
                basePath: 'vendor/acme/vendor-admin/src',
                administrationPath: 'Resources/app/administration/src',
            },
            {
                technicalName: 'JsOnly',
                basePath: 'custom/plugins/JsOnly/src',
                administrationPath: 'Resources/app/administration/src',
            },
            {
                technicalName: 'administration',
                basePath: 'vendor/shopware/administration',
                administrationPath: 'Resources/app/administration/src',
            },
        ]);

        setupResult = setupExtensionTooling({ projectRoot, administrationRoot });
    }, CHECK_TIMEOUT);

    afterAll(() => {
        cleanupTempProject(projectRoot);
    });

    it('connects zero-config, shim-config, multi-bundle, and vendor layouts', () => {
        const byName = Object.fromEntries(
            setupResult.manifest.projects.map((project) => [
                project.name,
                project,
            ]),
        );

        expect(Object.keys(byName).sort()).toEqual([
            'JsOnly',
            'ShimConfig',
            'Suite',
            'ZeroConfig',
            'vendor-admin',
        ]);
        expect(byName.ZeroConfig.vendor).toBe(false);
        // A zero-config plugin is auto-bridged: setup scaffolds a composing
        // tsconfig/eslint that compose the generated .shopware/ bridge.
        expect(byName.ZeroConfig.targets[0].tsconfig?.composes).toBe(true);
        expect(byName.ZeroConfig.targets[0].eslintConfig?.composes).toBe(true);
        expect(byName.ShimConfig.targets[0].tsconfig?.composes).toBe(true);
        expect(byName.ShimConfig.targets[0].eslintConfig?.composes).toBe(true);
        expect(byName.Suite.targets).toHaveLength(2);
        expect(byName['vendor-admin'].vendor).toBe(true);

        expect(
            fs.existsSync(
                path.join(projectRoot, 'custom/plugins/ShimConfig/src/Resources/app/administration/.shopware/tsconfig.json'),
            ),
        ).toBe(true);

        // Every discovered source root is auto-bridged, so none falls back to
        // the host-owned root projection.
        const uncoveredTargets = setupResult.manifest.projects.flatMap((project) =>
            project.targets.filter((target) => target.tsconfig === null),
        );

        expect(uncoveredTargets).toEqual([]);
    });

    it(
        'passes a clean check and reports vendor findings without failing',
        async () => {
            const check = await checkExtensions({ projectRoot, administrationRoot });
            const byName = Object.fromEntries(
                check.results.map((result) => [
                    result.project.name,
                    result,
                ]),
            );

            expect(byName.ZeroConfig.typescript.status).toBe('passed');
            // The dedicated spec program type-checked main.spec.ts with jest types.
            expect(byName.ZeroConfig.typescriptSpecs.status).toBe('passed');
            expect(byName.ZeroConfig.eslint.status).toBe('passed');
            expect(byName.JsOnly.typescript.status).toBe('no-files');
            expect(byName.JsOnly.typescriptSpecs.status).toBe('no-files');
            expect(byName.JsOnly.eslint.status).toBe('passed');
            expect(byName.ShimConfig.typescript.status).toBe('passed');
            // A fully composed project has no drift, so both resolutions are null.
            expect(byName.ShimConfig.tsResolution).toBeNull();
            expect(byName.ShimConfig.eslintResolution).toBeNull();
            expect(byName.Suite.typescript.status).toBe('passed');

            expect(byName['vendor-admin'].typescript.status).toBe('failed');
            expect(byName['vendor-admin'].typescript.output).toContain('TS2322');
            expect(byName['vendor-admin'].typescript.output).toContain('broken.ts');
            expect(byName['vendor-admin'].eslint.status).toBe('failed');
            expect(byName['vendor-admin'].eslint.output).toContain('no-unused-vars');

            expect(check.exitCode).toBe(0);
            expect(check.fatalDiagnostics).toEqual([]);
        },
        CHECK_TIMEOUT,
    );

    it(
        'makes vendor ESLint findings fatal only in strict vendor mode',
        async () => {
            const check = await checkExtensions({
                projectRoot,
                administrationRoot,
                only: 'vendor-admin',
                strictVendor: true,
            });

            expect(check.results[0].eslint.status).toBe('failed');
            expect(check.results[0].eslint.output).toContain('no-unused-vars');
            expect(check.exitCode).toBe(1);
        },
        CHECK_TIMEOUT,
    );

    it(
        'fails with native output when a type error is injected into a writable plugin',
        async () => {
            fs.appendFileSync(zeroConfigMainPath, "const invalidProductId: number = 'invalid';\nvoid invalidProductId;\n");

            try {
                const check = await checkExtensions({ projectRoot, administrationRoot, only: 'ZeroConfig' });

                expect(check.results).toHaveLength(1);
                expect(check.results[0].typescript.status).toBe('failed');
                expect(hasLineMatching(check.results[0].typescript.output, 'ZeroConfig', 'main.ts', 'error TS2322')).toBe(
                    true,
                );
                expect(check.exitCode).toBe(1);
            } finally {
                fs.writeFileSync(zeroConfigMainPath, originalZeroConfigMain);
            }
        },
        CHECK_TIMEOUT,
    );

    it(
        'rejects unknown entity names against the installation schema',
        async () => {
            fs.appendFileSync(
                zeroConfigMainPath,
                "export const nopeRepository = Shopware.Service('repositoryFactory').create('nope_entity');\n",
            );

            try {
                const check = await checkExtensions({ projectRoot, administrationRoot, only: 'ZeroConfig' });

                expect(check.results[0].typescript.status).toBe('failed');
                expect(hasLineMatching(check.results[0].typescript.output, 'ZeroConfig', 'main.ts', 'nope_entity')).toBe(
                    true,
                );
                expect(check.exitCode).toBe(1);
            } finally {
                fs.writeFileSync(zeroConfigMainPath, originalZeroConfigMain);
            }
        },
        CHECK_TIMEOUT,
    );

    it(
        'blocks TypeScript checks when only the entity schema stub exists',
        async () => {
            const schemaPath = path.join(administrationRoot, 'src', 'entity-schema-definition.d.ts');

            fs.rmSync(schemaPath);

            try {
                const check = await checkExtensions({ projectRoot, administrationRoot, only: 'ZeroConfig' });

                expect(check.exitCode).toBe(1);
                expect(check.fatalDiagnostics.join('\n')).toContain(
                    'bin/console administration:generate-entity-schema-types',
                );
                // The stub still guards IDEs against a silent degradation to
                // `any`; the check refuses to run vue-tsc instead of burying
                // the cause under cascade findings.
                expect(check.results[0].typescript.status).toBe('blocked');
                expect(check.results[0].typescript.durationMs).toBe(0);
                expect(check.results[0].eslint.status).not.toBe('blocked');
            } finally {
                writeConvertedEntitySchema(schemaPath);
                setupExtensionTooling({ projectRoot, administrationRoot });
            }
        },
        CHECK_TIMEOUT,
    );

    it('never applies the Twig-Vue processor outside discovered extension roots', () => {
        const eslintBin = path.join(realAdministrationRoot, 'node_modules', 'eslint', 'bin', 'eslint.js');

        function printConfig(filePath: string): string {
            const result = spawnSync(
                process.execPath,
                [
                    eslintBin,
                    '--flag',
                    'v10_config_lookup_from_file',
                    '--print-config',
                    filePath,
                ],
                { cwd: projectRoot, encoding: 'utf8', maxBuffer: 20 * 1024 * 1024 },
            );

            return `${result.stdout ?? ''}\n${result.stderr ?? ''}`;
        }

        const outsideConfig = printConfig(path.join(projectRoot, 'src/RealServerTwig/template.html.twig'));
        const insideConfig = printConfig(
            path.join(projectRoot, 'custom/plugins/ZeroConfig/src/Resources/app/administration/src/product-card.html.twig'),
        );

        expect(outsideConfig).not.toContain('twig-vue');
        expect(insideConfig).toContain('twig-vue');
    }, 120000);
});
