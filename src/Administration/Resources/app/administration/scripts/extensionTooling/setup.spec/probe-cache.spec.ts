/**
 * @sw-package framework
 *
 * Probe cache integration: setup adopts verified verdicts from check runs
 * while the config inputs are unchanged.
 */

import fs from 'fs';
import path from 'path';
import { setupExtensionTooling } from '../setup';
import { probeCacheEntryKey, probeCacheKey, probeInputFiles, readProbeCache, writeProbeCache } from '../probe';
import {
    cleanupTempProject,
    createSkeletonAdmin,
    createTempProject,
    syntheticEntitySchema,
    writeFile,
    writePluginsConfig,
} from '../test-helpers';

describe('scripts/extensionTooling/setup probe cache integration', () => {
    let projectRoot: string;
    let administrationRoot: string;

    beforeEach(() => {
        projectRoot = createTempProject('sw-tooling-setup-cache-');
        administrationRoot = createSkeletonAdmin(projectRoot);
        fs.mkdirSync(path.join(administrationRoot, 'src'), { recursive: true });
        writeFile(path.join(administrationRoot, 'src', 'entity-schema-definition.d.ts'), syntheticEntitySchema);
    });

    afterEach(() => {
        cleanupTempProject(projectRoot);
    });

    it('adopts cached verified verdicts while inputs match and prunes removed extensions', () => {
        writeFile(path.join(projectRoot, 'custom/plugins/Probe/composer.json'), '{}\n');
        writeFile(path.join(projectRoot, 'custom/plugins/Probe/src/Resources/app/administration/src/main.ts'), [
            'export {};',
        ]);
        writeFile(path.join(projectRoot, 'custom/plugins/Probe/src/Resources/app/administration/tsconfig.json'), [
            '{ "compilerOptions": { "strict": true } }',
        ]);
        writePluginsConfig(projectRoot, [
            {
                technicalName: 'Probe',
                basePath: 'custom/plugins/Probe/src',
                administrationPath: 'Resources/app/administration/src',
            },
        ]);

        const staticRun = setupExtensionTooling({ projectRoot, administrationRoot });
        const target = staticRun.manifest.projects[0].targets[0];

        expect(target.ts).toMatchObject({
            mode: 'unmanaged',
            reason: 'not-extending',
            verified: false,
        });

        const inputs = probeInputFiles(target, projectRoot, administrationRoot);
        const cacheKey = probeCacheEntryKey('Probe', target);

        writeProbeCache(projectRoot, {
            version: 2,
            entries: {
                [cacheKey]: {
                    ts: {
                        key: probeCacheKey(inputs.ts),
                        resolution: { mode: 'unmanaged', reason: 'surface-not-injected', verified: true },
                    },
                },
                Ghost: {
                    ts: { key: 'stale', resolution: { mode: 'bridged', verified: true } },
                },
            },
        });

        const cachedRun = setupExtensionTooling({ projectRoot, administrationRoot });

        expect(cachedRun.manifest.projects[0].targets[0].ts).toEqual({
            mode: 'unmanaged',
            reason: 'surface-not-injected',
            verified: true,
        });
        expect(readProbeCache(projectRoot)?.entries.Ghost).toBeUndefined();

        // A config edit invalidates the hash — back to the static verdict.
        writeFile(path.join(projectRoot, 'custom/plugins/Probe/src/Resources/app/administration/tsconfig.json'), [
            '{ "compilerOptions": { "strict": false } }',
        ]);

        const invalidatedRun = setupExtensionTooling({ projectRoot, administrationRoot });

        expect(invalidatedRun.manifest.projects[0].targets[0].ts.verified).toBe(false);
    });
});
