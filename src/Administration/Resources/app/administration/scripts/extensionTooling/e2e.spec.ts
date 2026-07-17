/**
 * @sw-package framework
 *
 * End-to-end suite: a temp Flex-style project with a vendor-installed
 * Administration, checked and built with the real toolchain.
 */

import { spawnSync } from 'child_process';
import fs from 'fs';
import path from 'path';
import { checkExtensions } from './check';
import { setupExtensionTooling } from './setup';
import type { SetupExtensionToolingResult } from './setup';
import {
    cleanupTempProject,
    createTempProject,
    createVendorAdmin,
    realAdministrationRoot,
    writeConvertedEntitySchema,
    writeFile,
    writePluginsConfig,
    writeZeroConfigPlugin,
} from './test-helpers';

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

        // Committed-config plugin: files extend the generated shim.
        const shimAdminFolder = path.join(projectRoot, 'custom/plugins/ShimConfig/src/Resources/app/administration');

        writeFile(path.join(projectRoot, 'custom/plugins/ShimConfig/composer.json'), '{}\n');
        writeFile(path.join(shimAdminFolder, 'src/main.ts'), [
            "export const shimmedRepository = Shopware.Service('repositoryFactory').create('product');",
        ]);
        writeFile(path.join(shimAdminFolder, 'tsconfig.json'), [
            '{',
            '    "extends": "./.shopware-admin/tsconfig.json",',
            '    "include": ["src/**/*.ts", "src/**/*.vue"]',
            '}',
        ]);
        writeFile(path.join(shimAdminFolder, 'eslint.config.mjs'), [
            "import shopware from './.shopware-admin/eslint.mjs';",
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
                technicalName: 'administration',
                basePath: 'vendor/shopware/administration',
                administrationPath: 'Resources/app/administration/src',
            },
        ]);

        setupResult = setupExtensionTooling({ projectRoot, administrationRoot, shim: 'ShimConfig' });
    });

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
            'ShimConfig',
            'Suite',
            'ZeroConfig',
            'vendor-admin',
        ]);
        expect(byName.ZeroConfig).toMatchObject({
            ts: { mode: 'managed' },
            eslint: { mode: 'managed' },
            vendor: false,
        });
        expect(byName.ShimConfig).toMatchObject({ ts: { mode: 'custom' }, eslint: { mode: 'custom' } });
        expect(byName.Suite.sourcePaths).toHaveLength(2);
        expect(byName['vendor-admin'].vendor).toBe(true);

        expect(
            fs.existsSync(
                path.join(
                    projectRoot,
                    'custom/plugins/ShimConfig/src/Resources/app/administration/.shopware-admin/tsconfig.json',
                ),
            ),
        ).toBe(true);

        // Root references route every managed project to exactly its leaf config.
        const rootTsconfig = fs.readFileSync(path.join(projectRoot, 'tsconfig.json'), 'utf8');
        const references = [...rootTsconfig.matchAll(/"path": "\.\/(.+)"/g)].map((match) => match[1]);
        const managedLeafs = setupResult.manifest.projects
            .filter((project) => project.ts.mode === 'managed')
            .map((project) => project.checkTsconfig);

        expect(references.sort()).toEqual([...managedLeafs].sort());

        for (const leaf of managedLeafs) {
            expect(fs.existsSync(path.join(projectRoot, leaf))).toBe(true);
        }
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
            expect(byName.ZeroConfig.eslint.status).toBe('passed');
            expect(byName.ShimConfig.typescript.status).toBe('passed');
            expect(byName.ShimConfig.tsResolution.mode).toBe('custom');
            expect(byName.ShimConfig.eslintResolution.mode).toBe('custom');
            expect(byName.Suite.typescript.status).toBe('passed');

            expect(byName['vendor-admin'].typescript.status).toBe('failed');
            expect(byName['vendor-admin'].typescript.output).toContain('TS2322');
            expect(byName['vendor-admin'].typescript.output).toContain('broken.ts');

            expect(check.exitCode).toBe(0);
            expect(check.fatalDiagnostics).toEqual([]);
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
        'fails loudly when only the entity schema stub exists',
        async () => {
            const schemaPath = path.join(administrationRoot, 'src', 'entity-schema-definition.d.ts');

            fs.rmSync(schemaPath);

            try {
                const check = await checkExtensions({ projectRoot, administrationRoot, only: 'ZeroConfig' });

                expect(check.exitCode).toBe(1);
                expect(check.fatalDiagnostics.join('\n')).toContain('composer admin:generate-entity-schema-types');
                // With the stub in place even known entity names must error —
                // never a silent degradation to `any`.
                expect(check.results[0].typescript.status).toBe('failed');
                expect(hasLineMatching(check.results[0].typescript.output, 'ZeroConfig', 'main.ts', 'product')).toBe(true);
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

    it('still produces the plugin manifest through the real Vite build', () => {
        const tsNodeBin = path.join(realAdministrationRoot, 'node_modules', 'ts-node', 'dist', 'bin.js');
        const buildScript = path.join(realAdministrationRoot, 'build', 'plugins.vite.ts');
        const result = spawnSync(
            process.execPath,
            [
                tsNodeBin,
                '--transpileOnly',
                buildScript,
            ],
            {
                cwd: realAdministrationRoot,
                encoding: 'utf8',
                env: {
                    ...process.env,
                    PROJECT_ROOT: projectRoot,
                    VITE_MODE: 'production',
                },
                maxBuffer: 100 * 1024 * 1024,
            },
        );

        if (result.status !== 0) {
            throw new Error(`${result.stdout ?? ''}\n${result.stderr ?? ''}`);
        }

        expect(
            fs.existsSync(
                path.join(projectRoot, 'custom/plugins/ZeroConfig/src/Resources/public/administration/.vite/manifest.json'),
            ),
        ).toBe(true);
    }, 600000);
});

describe('scripts/extensionTooling e2e — scaffolded committable configs', () => {
    let projectRoot: string;
    let administrationRoot: string;
    const freshAdmin = 'custom/plugins/FreshPlugin/src/Resources/app/administration';

    beforeAll(() => {
        projectRoot = createTempProject('sw-tooling-scaffold-');
        administrationRoot = createVendorAdmin(projectRoot, { entitySchema: 'real' });

        // A plugin with no Administration config at all.
        writeFile(path.join(projectRoot, 'custom/plugins/FreshPlugin/composer.json'), '{}\n');
        writeFile(path.join(projectRoot, freshAdmin, 'src/main.ts'), [
            "export const productRepository = Shopware.Service('repositoryFactory').create('product');",
        ]);

        writePluginsConfig(projectRoot, [
            {
                technicalName: 'FreshPlugin',
                basePath: 'custom/plugins/FreshPlugin/src',
                administrationPath: 'Resources/app/administration/src',
            },
        ]);
    });

    afterAll(() => {
        cleanupTempProject(projectRoot);
    });

    it(
        'scaffolds committable tsconfig/eslint that extend the bridge, and the check passes',
        async () => {
            setupExtensionTooling({ projectRoot, administrationRoot, shim: 'FreshPlugin' });

            const pluginTsconfig = path.join(projectRoot, freshAdmin, 'tsconfig.json');
            const pluginEslint = path.join(projectRoot, freshAdmin, 'eslint.config.mjs');

            // Committable configs live at the admin folder (not inside .shopware-admin/) and just
            // extend the generated bridge.
            expect(fs.readFileSync(pluginTsconfig, 'utf8')).toContain('"extends": "./.shopware-admin/tsconfig.json"');
            expect(fs.readFileSync(pluginEslint, 'utf8')).toContain("import shopware from './.shopware-admin/eslint.mjs'");

            const check = await checkExtensions({ projectRoot, administrationRoot, only: 'FreshPlugin' });

            expect(check.results).toHaveLength(1);
            expect(check.results[0].typescript.status).toBe('passed');
            expect(check.results[0].eslint.status).toBe('passed');
            expect(check.exitCode).toBe(0);
        },
        CHECK_TIMEOUT,
    );
});
