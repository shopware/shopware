/**
 * @sw-package framework
 *
 * What discovery reads out of var/plugins.json: bundles grouped by composer
 * root, statically resolved config modes, and the two clamps that keep a
 * semi-trusted bundle dump from widening the checked surface — sources outside
 * the project root, and duplicate bundles pointing at one source root.
 */

import { setupExtensionTooling } from '../setup';
import {
    cleanupTempProject,
    createTempProject,
    writeFile,
    writePluginsConfig,
    writeZeroConfigPlugin,
} from '../test-helpers';
import path from 'path';
import { createSetupProject, writeDefaultFixtures } from './fixtures';

describe('scripts/extensionTooling/setup discovery', () => {
    let projectRoot: string;
    let administrationRoot: string;

    beforeEach(() => {
        ({ projectRoot, administrationRoot } = createSetupProject('sw-tooling-setup-discovery-'));
    });

    afterEach(() => {
        cleanupTempProject(projectRoot);
    });

    it('discovers extensions, groups suites by composer root, and records modes in the manifest', () => {
        writeDefaultFixtures(projectRoot);

        const result = setupExtensionTooling({ projectRoot, administrationRoot });
        const names = result.manifest.projects.map((project) => project.name);

        expect(names).toEqual([
            'Suite',
            'ZeroConfig',
            'custom-admin',
        ]);

        const suite = result.manifest.projects.find((project) => project.name === 'Suite');
        const zeroConfig = result.manifest.projects.find((project) => project.name === 'ZeroConfig');
        const vendorExtension = result.manifest.projects.find((project) => project.name === 'custom-admin');

        expect(suite).toMatchObject({
            technicalNames: [
                'SuiteA',
                'SuiteB',
            ],
            vendor: false,
        });
        // Auto-bridging scaffolded composing configs, so the manifest records
        // the post-bridge state of the same run.
        expect(suite?.targets).toHaveLength(2);
        expect(suite?.targets).toEqual(
            expect.arrayContaining([
                expect.objectContaining({
                    technicalNames: ['SuiteA'],
                    ts: expect.objectContaining({ mode: 'bridged' }) as unknown,
                }),
                expect.objectContaining({
                    technicalNames: ['SuiteB'],
                    ts: expect.objectContaining({ mode: 'bridged' }) as unknown,
                }),
            ]),
        );
        expect(zeroConfig?.vendor).toBe(false);
        expect(zeroConfig?.targets[0].bridgePresent).toBe(true);
        expect(zeroConfig?.targets[0].ts).toMatchObject({ mode: 'bridged' });
        expect(zeroConfig?.targets[0].eslint).toMatchObject({ mode: 'bridged' });
        // The vendor fixture ships its own non-composing configs: they are
        // never overwritten, so the bridge exists but stays unwired — static
        // analysis already classifies them, unverified until a check run.
        expect(vendorExtension?.vendor).toBe(true);
        expect(vendorExtension?.targets[0].bridgePresent).toBe(true);
        expect(vendorExtension?.targets[0].ts).toMatchObject({
            mode: 'unmanaged',
            reason: 'not-extending',
            verified: false,
        });
        expect(vendorExtension?.targets[0].eslint).toMatchObject({
            mode: 'unmanaged',
            reason: 'factory-not-composed',
            verified: false,
        });
        expect(vendorExtension?.targets[0].tsconfig).toBe(
            'vendor/acme/custom-admin/src/Resources/app/administration/tsconfig.json',
        );
        expect(result.manifest.entitySchemaAvailable).toBe(true);
    });

    it('ignores extension sources resolved outside the project root', () => {
        // A tampered var/plugins.json can carry an absolute or `../`-traversing
        // basePath. The source physically exists (so the existence check passes)
        // but lives outside the project — it must not be discovered, walked, or
        // handed to the tools.
        const outsideRoot = createTempProject('sw-tooling-outside-');

        try {
            writeFile(path.join(outsideRoot, 'evil/Resources/app/administration/src/main.ts'), ['export {};']);
            writeZeroConfigPlugin({ projectRoot, pluginPath: 'custom/plugins/Inside' });
            writePluginsConfig(projectRoot, [
                {
                    technicalName: 'Inside',
                    basePath: 'custom/plugins/Inside/src',
                    administrationPath: 'Resources/app/administration/src',
                },
                {
                    technicalName: 'Outside',
                    basePath: path.join(outsideRoot, 'evil'),
                    administrationPath: 'Resources/app/administration/src',
                },
            ]);

            const result = setupExtensionTooling({ projectRoot, administrationRoot });
            const names = result.manifest.projects.map((project) => project.name);

            expect(names).toContain('Inside');
            expect(names).not.toContain('evil');
            expect(result.manifest.projects).toHaveLength(1);
        } finally {
            cleanupTempProject(outsideRoot);
        }
    });

    it('canonicalizes duplicate bundle entries that point to the same Administration source root', () => {
        writeZeroConfigPlugin({ projectRoot, pluginPath: 'custom/plugins/DuplicateRoot' });
        writePluginsConfig(projectRoot, [
            {
                technicalName: 'DuplicateRootA',
                basePath: 'custom/plugins/DuplicateRoot/src',
                administrationPath: 'Resources/app/administration/src',
            },
            {
                technicalName: 'DuplicateRootB',
                basePath: 'custom/plugins/DuplicateRoot/src',
                administrationPath: 'Resources/app/administration/src',
            },
        ]);

        const result = setupExtensionTooling({ projectRoot, administrationRoot });

        expect(result.manifest.projects).toHaveLength(1);
        expect(result.manifest.projects[0].targets).toEqual([
            expect.objectContaining({
                technicalNames: [
                    'DuplicateRootA',
                    'DuplicateRootB',
                ],
            }),
        ]);
    });
});
