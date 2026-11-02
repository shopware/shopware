/**
 * @sw-package framework
 */

import { spawnSync } from 'child_process';
import fs from 'fs';
import path from 'path';
import { checkExtensions } from '../check';
import {
    cleanupTempProject,
    createTempProject,
    createVendorAdmin,
    realAdministrationRoot,
    writeFile,
    writePluginsConfig,
} from '../test-helpers';

const CHECK_TIMEOUT = 300000;

describe('generated Administration scaffold', () => {
    let projectRoot: string;
    let administrationRoot: string;
    let modulePath: string;
    let generatedFiles: string[];

    beforeAll(() => {
        projectRoot = createTempProject('sw-scaffolding-e2e-');
        administrationRoot = createVendorAdmin(projectRoot, { entitySchema: 'real' });

        // Generate the actual PHP stubs instead of duplicating their TypeScript in a fixture.
        const generated = spawnSync('php', [], {
            cwd: path.resolve(realAdministrationRoot, '../../../../..'),
            input: String.raw`<?php
require 'vendor/autoload.php';
$config = new \Shopware\Core\Framework\Plugin\Command\Scaffolding\PluginScaffoldConfiguration(
    'SfIssue20688', 'SfIssue20688', '/unused',
    [\Shopware\Core\Framework\Plugin\Command\Scaffolding\Generator\AdminModuleGenerator::OPTION_NAME => true],
);
$stubs = new \Shopware\Core\Framework\Plugin\Command\Scaffolding\StubCollection();
(new \Shopware\Core\Framework\Plugin\Command\Scaffolding\Generator\ComposerGenerator())->generateStubs($config, $stubs);
(new \Shopware\Core\Framework\Plugin\Command\Scaffolding\Generator\AdminModuleGenerator())->generateStubs($config, $stubs);
$files = [];
foreach ($stubs as $stub) {
    $files[$stub->getPath()] = $stub->getContent();
}
echo json_encode($files, JSON_THROW_ON_ERROR);
`,
            encoding: 'utf8',
            timeout: 30000,
        });

        if (generated.error || generated.status !== 0) {
            throw new Error(`PHP scaffolding failed: ${generated.error?.message ?? generated.stderr}`);
        }

        const files = JSON.parse(generated.stdout) as Record<string, string>;
        const pluginRoot = path.join(projectRoot, 'custom/plugins/SfIssue20688');

        generatedFiles = Object.keys(files);

        for (const [name, content] of Object.entries(files)) {
            writeFile(path.join(pluginRoot, name), content);
        }

        modulePath = path.join(pluginRoot, 'src/Resources/app/administration/src/module/swag-example/index.ts');
        writePluginsConfig(projectRoot, [
            {
                technicalName: 'SfIssue20688',
                basePath: 'custom/plugins/SfIssue20688/src',
                administrationPath: 'Resources/app/administration/src',
                entryFilePath: 'Resources/app/administration/src/main.ts',
            },
        ]);
    }, CHECK_TIMEOUT);

    afterAll(() => {
        cleanupTempProject(projectRoot);
    });

    it(
        'typechecks and lints all generated implementation files against the installed Administration',
        async () => {
            expect(generatedFiles.filter((name) => name.endsWith('.ts'))).toHaveLength(3);
            expect(generatedFiles.some((name) => name.endsWith('.js'))).toBe(false);

            const check = await checkExtensions({
                projectRoot,
                administrationRoot,
                only: 'SfIssue20688',
                failOnSkipped: true,
            });

            expect(check.fatalDiagnostics).toEqual([]);
            expect(check.results).toHaveLength(1);
            expect(check.results[0].typescript.output).toBe('');
            expect(check.results[0].typescript.status).toBe('passed');
            expect(check.results[0].eslint.output).toBe('');
            expect(check.results[0].eslint.status).toBe('passed');
            expect(check.exitCode).toBe(0);
        },
        CHECK_TIMEOUT,
    );

    it(
        'builds the generated TypeScript entry point with the production extension builder',
        () => {
            const built = spawnSync(
                process.execPath,
                [
                    path.join(realAdministrationRoot, 'node_modules/ts-node/dist/bin.js'),
                    '--transpile-only',
                    path.join(realAdministrationRoot, 'build/plugins.vite.ts'),
                ],
                {
                    cwd: realAdministrationRoot,
                    env: {
                        ...process.env,
                        PROJECT_ROOT: projectRoot,
                        ADMIN_ROOT: path.resolve(administrationRoot, '../../..'),
                        VITE_MODE: 'production',
                    },
                    encoding: 'utf8',
                    timeout: CHECK_TIMEOUT,
                },
            );

            expect(built.error).toBeUndefined();
            expect(`${built.stdout}\n${built.stderr}`).not.toContain('Failed to build');
            expect(built.status).toBe(0);

            const output = path.join(projectRoot, 'custom/plugins/SfIssue20688/src/Resources/public/administration');

            expect(fs.existsSync(path.join(output, '.vite/manifest.json'))).toBe(true);
            expect(fs.readdirSync(path.join(output, 'assets')).some((name) => name.endsWith('.js'))).toBe(true);
        },
        CHECK_TIMEOUT,
    );

    it(
        'rejects an invalid core module type in the generated implementation',
        async () => {
            const original = fs.readFileSync(modulePath, 'utf8');

            expect(original).toContain("type: 'plugin'");
            fs.writeFileSync(modulePath, original.replace("type: 'plugin'", 'type: 42'));

            try {
                const check = await checkExtensions({
                    projectRoot,
                    administrationRoot,
                    only: 'SfIssue20688',
                    failOnSkipped: true,
                });

                expect(check.results).toHaveLength(1);
                expect(check.results[0].typescript.status).toBe('failed');
                expect(check.results[0].typescript.output).toContain('TS2322');
                expect(check.results[0].typescript.output).toContain('module/swag-example/index.ts');
                expect(check.exitCode).toBe(1);
            } finally {
                fs.writeFileSync(modulePath, original);
            }
        },
        CHECK_TIMEOUT,
    );
});
