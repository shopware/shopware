/**
 * @sw-package framework
 *
 * End-to-end suite for the `--shim` scaffolding path: committable configs,
 * autofixes, and the files-override diagnosis — all with the real toolchain.
 */

import fs from 'fs';
import path from 'path';
import { checkExtensions } from '../check';
import { renderCheckReport, renderSetupReport } from '../report';
import { stripAnsi } from '../report.spec/helpers';
import { setupExtensionTooling } from '../setup';
import { cleanupTempProject, createTempProject, createVendorAdmin, writeFile, writePluginsConfig } from '../test-helpers';

const CHECK_TIMEOUT = 300000;

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

    it(
        'applies ESLint autofixes with --fix and names the command in the native hint',
        async () => {
            const fixablePath = path.join(projectRoot, freshAdmin, 'src/fixable.js');

            // no-extra-boolean-cast: in eslint:recommended and auto-fixable.
            writeFile(fixablePath, [
                'const enabled = true;',
                '',
                'if (!!enabled) {',
                '    document.title = String(enabled);',
                '}',
                '',
                'export default enabled;',
            ]);

            try {
                const before = await checkExtensions({ projectRoot, administrationRoot, only: 'FreshPlugin' });

                expect(before.results[0].eslint.status).toBe('failed');
                expect(before.results[0].eslint.output).toContain(
                    'auto-fixable: composer admin:check-extensions -- --only=FreshPlugin --fix',
                );

                const fixed = await checkExtensions({ projectRoot, administrationRoot, only: 'FreshPlugin', fix: true });

                expect(fs.readFileSync(fixablePath, 'utf8')).not.toContain('!!');
                expect(fixed.results[0].eslint.status).toBe('passed');
            } finally {
                fs.rmSync(fixablePath, { force: true });
            }
        },
        CHECK_TIMEOUT,
    );

    it(
        'diagnoses a files-override tsconfig and reaches bridged after the printed fix',
        async () => {
            // The pre-existing standard pattern: an own tsconfig that extends
            // the bridge but declares its own "files" — the bridge's type
            // surface injection is silently replaced.
            const overrideAdmin = 'custom/plugins/FilesOverride/src/Resources/app/administration';

            writeFile(path.join(projectRoot, 'custom/plugins/FilesOverride/composer.json'), '{}\n');
            writeFile(path.join(projectRoot, overrideAdmin, 'src/main.ts'), ["export const label: string = 'x';"]);
            writeFile(path.join(projectRoot, overrideAdmin, 'tsconfig.json'), [
                '{',
                '    "extends": "./.shopware-admin/tsconfig.json",',
                '    "files": ["./src/main.ts"],',
                '    "include": ["src/**/*.ts"]',
                '}',
            ]);
            writePluginsConfig(projectRoot, [
                {
                    technicalName: 'FreshPlugin',
                    basePath: 'custom/plugins/FreshPlugin/src',
                    administrationPath: 'Resources/app/administration/src',
                },
                {
                    technicalName: 'FilesOverride',
                    basePath: 'custom/plugins/FilesOverride/src',
                    administrationPath: 'Resources/app/administration/src',
                },
            ]);

            setupExtensionTooling({ projectRoot, administrationRoot, shim: 'FilesOverride' });

            const brokenCheck = await checkExtensions({ projectRoot, administrationRoot, only: 'FilesOverride' });

            expect(brokenCheck.results[0].typescript.status).toBe('unmanaged');
            expect(brokenCheck.results[0].tsResolution.reason).toBe('files-override');

            const brokenReport = stripAnsi(renderCheckReport(brokenCheck));

            expect(brokenReport).toContain('why:');
            expect(brokenReport).toContain('"files"');
            expect(brokenReport).toContain('fix: remove "files" from the plugin tsconfig');
            expect(brokenReport).not.toContain('--shim=FilesOverride');

            // Apply exactly the printed fix: drop the "files" override.
            writeFile(path.join(projectRoot, overrideAdmin, 'tsconfig.json'), [
                '{',
                '    "extends": "./.shopware-admin/tsconfig.json",',
                '    "include": ["src/**/*.ts"]',
                '}',
            ]);

            const fixedCheck = await checkExtensions({ projectRoot, administrationRoot, only: 'FilesOverride' });

            expect(fixedCheck.results[0].tsResolution.mode).toBe('bridged');
            expect(fixedCheck.results[0].typescript.status).toBe('passed');

            // Setup and check agree afterwards: the extension renders as bridged.
            const setupAfter = setupExtensionTooling({ projectRoot, administrationRoot });
            const setupReport = stripAnsi(renderSetupReport(setupAfter));

            expect(setupReport).toContain('● bridged');
            expect(setupReport).toContain('FilesOverride');
            expect(setupReport).not.toContain('--shim=FilesOverride');
        },
        CHECK_TIMEOUT,
    );
});
