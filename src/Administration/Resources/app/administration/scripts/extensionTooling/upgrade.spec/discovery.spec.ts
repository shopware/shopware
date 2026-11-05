/**
 * @sw-package framework
 *
 * Which extensions a run picks up: static plugins, vendor extensions, and
 * plugins that var/plugins.json does not list.
 */

import fs from 'fs';
import path from 'path';
import { upgradeExtensions } from '../upgrade';
import { cleanupTempProject, writePluginsConfig } from '../test-helpers';
import { commitAll, createUpgradeProject, writePlugin } from './fixtures';

jest.setTimeout(120_000);

describe('scripts/extensionTooling/upgrade discovery', () => {
    let projectRoot: string;
    let administrationRoot: string;

    beforeEach(() => {
        ({ projectRoot, administrationRoot } = createUpgradeProject('sw-tooling-upgrade-discovery-'));
    });

    afterEach(() => {
        cleanupTempProject(projectRoot);
    });

    it('upgrades static plugins like any other project extension', async () => {
        writePlugin(projectRoot, 'custom/static-plugins/AcmeStatic');
        writePluginsConfig(projectRoot, [
            {
                technicalName: 'AcmeStatic',
                basePath: 'custom/static-plugins/AcmeStatic/src',
                administrationPath: 'Resources/app/administration/src',
            },
        ]);

        const result = await upgradeExtensions({ projectRoot, administrationRoot });

        expect(result.extensions.map((extension) => extension.name)).toEqual(['AcmeStatic']);
        expect(result.extensions[0].skipped).toBeUndefined();
    });

    it('leaves vendor extensions alone unless they are named via --only', async () => {
        writePlugin(projectRoot, 'vendor/acme/vendor-upgrade');
        writePluginsConfig(projectRoot, [
            {
                technicalName: 'VendorUpgrade',
                basePath: 'vendor/acme/vendor-upgrade/src',
                administrationPath: 'Resources/app/administration/src',
            },
        ]);
        commitAll(path.join(projectRoot, 'vendor/acme/vendor-upgrade'));
        const vendorTemplate = path.join(
            projectRoot,
            'vendor/acme/vendor-upgrade/src/Resources/app/administration/src/page/acme-list/acme-list.html.twig',
        );

        const implicit = await upgradeExtensions({ projectRoot, administrationRoot, write: true });

        expect(implicit.extensions[0].skipped).toContain('vendor-installed');
        expect(fs.readFileSync(vendorTemplate, 'utf8')).toContain('<sw-button');

        await upgradeExtensions({
            projectRoot,
            administrationRoot,
            only: ['vendor-upgrade'],
            explicitOnly: ['vendor-upgrade'],
            write: true,
        });

        expect(fs.readFileSync(vendorTemplate, 'utf8')).toContain('<mt-button');
    });

    describe('plugins that var/plugins.json does not list', () => {
        const INACTIVE = 'custom/plugins/AcmeInactive';
        const inactiveTemplate = () =>
            fs.readFileSync(
                path.join(projectRoot, INACTIVE, 'src/Resources/app/administration/src/page/acme-list/acme-list.html.twig'),
                'utf8',
            );

        beforeEach(() => {
            writePlugin(projectRoot, INACTIVE);
            commitAll(path.join(projectRoot, INACTIVE));
        });

        it('upgrades them from custom/plugins only when they are named via --only', async () => {
            const implicit = await upgradeExtensions({ projectRoot, administrationRoot, write: true });

            expect(implicit.extensions.map((extension) => extension.name)).toEqual(['AcmeUpgrade']);
            expect(inactiveTemplate()).toContain('<sw-button');

            const named = await upgradeExtensions({
                projectRoot,
                administrationRoot,
                only: ['AcmeInactive'],
                explicitOnly: ['AcmeInactive'],
                write: true,
            });

            expect(named.exitCode).toBe(0);
            expect(named.extensions.map((extension) => extension.name)).toEqual(['AcmeInactive']);
            expect(inactiveTemplate()).toContain('<mt-button');
        });

        it('finds them without var/plugins.json', async () => {
            fs.rmSync(path.join(projectRoot, 'var/plugins.json'));

            const result = await upgradeExtensions({ projectRoot, administrationRoot, only: ['AcmeInactive'] });

            expect(result.exitCode).toBe(0);
            expect(result.extensions[0].codemods.find((report) => report.codemod.id === 'mt-components')).toMatchObject({
                changes: 1,
            });
        });

        it('still rejects names that are not a folder in custom/plugins', async () => {
            const result = await upgradeExtensions({
                projectRoot,
                administrationRoot,
                only: ['AcmeMissing', '../plugins/AcmeInactive'],
            });

            expect(result.exitCode).toBe(1);
            expect(result.fatalDiagnostics.join('\n')).toContain(
                '--only names unknown extension(s): AcmeMissing, ../plugins/AcmeInactive.',
            );
        });
    });
});
