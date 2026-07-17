/**
 * @sw-package framework
 *
 * Discovery freshness: a var/plugins.json older than custom/plugins/ earns a
 * bundle:dump hint instead of a silently green "up to date".
 */

import fs from 'fs';
import path from 'path';
import { checkDiscoveryFreshness, setupExtensionTooling } from '../setup';
import {
    cleanupTempProject,
    createSkeletonAdmin,
    createTempProject,
    syntheticEntitySchema,
    writeFile,
    writePluginsConfig,
} from '../test-helpers';

describe('scripts/extensionTooling/setup discovery freshness', () => {
    let projectRoot: string;
    let administrationRoot: string;

    beforeEach(() => {
        projectRoot = createTempProject('sw-tooling-setup-freshness-');
        administrationRoot = createSkeletonAdmin(projectRoot);
        fs.mkdirSync(path.join(administrationRoot, 'src'), { recursive: true });
        writeFile(path.join(administrationRoot, 'src', 'entity-schema-definition.d.ts'), syntheticEntitySchema);
    });

    afterEach(() => {
        cleanupTempProject(projectRoot);
    });

    function setMtime(filePath: string, epochSeconds: number): void {
        fs.utimesSync(filePath, epochSeconds, epochSeconds);
    }

    it('warns with the bundle:dump command when a plugin is newer than plugins.json', () => {
        const pluginsPath = writePluginsConfig(projectRoot, []);
        const composerPath = path.join(projectRoot, 'custom/plugins/FreshlyInstalled/composer.json');

        writeFile(composerPath, '{}\n');
        setMtime(pluginsPath, 1_000_000);
        setMtime(composerPath, 2_000_000);

        expect(checkDiscoveryFreshness(projectRoot, pluginsPath)).toContain('bin/console bundle:dump');

        const result = setupExtensionTooling({ projectRoot, administrationRoot });

        expect(result.warnings.join('\n')).toContain('bin/console bundle:dump');
    });

    it('stays silent when plugins.json is current or inputs are missing', () => {
        const pluginsPath = writePluginsConfig(projectRoot, []);
        const composerPath = path.join(projectRoot, 'custom/plugins/Old/composer.json');

        writeFile(composerPath, '{}\n');
        setMtime(composerPath, 1_000_000);
        setMtime(pluginsPath, 2_000_000);

        expect(checkDiscoveryFreshness(projectRoot, pluginsPath)).toBeNull();

        // No custom/plugins directory at all — nothing to compare against.
        fs.rmSync(path.join(projectRoot, 'custom'), { recursive: true, force: true });
        expect(checkDiscoveryFreshness(projectRoot, pluginsPath)).toBeNull();

        // Missing plugins.json is discovery's own error, not a freshness warning.
        expect(checkDiscoveryFreshness(projectRoot, path.join(projectRoot, 'nope.json'))).toBeNull();
    });
});
