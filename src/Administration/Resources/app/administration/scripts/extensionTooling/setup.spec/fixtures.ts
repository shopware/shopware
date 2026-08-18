/**
 * @sw-package framework
 *
 * Shared fixtures for the setup generator specs. `createSetupProject` builds a
 * temp project with a skeleton Administration and a real entity schema;
 * `writeDefaultFixtures` adds the extension layout the generator scenarios
 * assert against — a zero-config plugin, a vendor extension shipping its own
 * non-composing configs, a two-bundle suite under one composer root, the
 * platform Administration bundle, and an entry that does not exist on disk.
 *
 * Not a spec file: no `.spec.ts` suffix, so Jest does not execute it.
 */

import fs from 'fs';
import path from 'path';
import {
    createSkeletonAdmin,
    createTempProject,
    syntheticEntitySchema,
    writeFile,
    writePluginsConfig,
    writeZeroConfigPlugin,
} from '../test-helpers';

export interface SetupFixtureProject {
    projectRoot: string;
    administrationRoot: string;
}

export function createSetupProject(prefix: string): SetupFixtureProject {
    const projectRoot = createTempProject(prefix);
    const administrationRoot = createSkeletonAdmin(projectRoot);

    fs.mkdirSync(path.join(administrationRoot, 'src'), { recursive: true });
    writeFile(path.join(administrationRoot, 'src', 'entity-schema-definition.d.ts'), syntheticEntitySchema);

    return { projectRoot, administrationRoot };
}

export function writeDefaultFixtures(projectRoot: string): string {
    writeZeroConfigPlugin({ projectRoot, pluginPath: 'custom/plugins/ZeroConfig' });

    // Vendor extension with its own configs next to the admin sources.
    writeFile(path.join(projectRoot, 'vendor/acme/custom-admin/composer.json'), '{}\n');
    writeFile(path.join(projectRoot, 'vendor/acme/custom-admin/src/Resources/app/administration/src/main.ts'), [
        'export {};',
    ]);
    writeFile(path.join(projectRoot, 'vendor/acme/custom-admin/src/Resources/app/administration/tsconfig.json'), ['{}']);
    writeFile(path.join(projectRoot, 'vendor/acme/custom-admin/src/Resources/app/administration/eslint.config.mjs'), [
        'export default [];',
    ]);

    // Multi-bundle suite sharing one composer root.
    writeFile(path.join(projectRoot, 'custom/plugins/Suite/composer.json'), '{}\n');

    for (const bundleName of [
        'BundleA',
        'BundleB',
    ]) {
        writeFile(
            path.join(projectRoot, 'custom/plugins/Suite/src', bundleName, 'Resources/app/administration/src/main.ts'),
            ['export {};'],
        );
    }

    return writePluginsConfig(projectRoot, [
        {
            technicalName: 'ZeroConfig',
            basePath: 'custom/plugins/ZeroConfig/src',
            administrationPath: 'Resources/app/administration/src',
        },
        {
            technicalName: 'CustomAdmin',
            basePath: 'vendor/acme/custom-admin/src',
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
            technicalName: 'administration',
            basePath: 'vendor/shopware/administration',
            administrationPath: 'Resources/app/administration/src',
        },
        { technicalName: 'MissingOnDisk', basePath: 'custom/plugins/MissingOnDisk', administrationPath: 'src' },
    ]);
}
