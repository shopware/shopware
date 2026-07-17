/**
 * @sw-package framework
 *
 * End-to-end findings-baseline round-trip against the real vue-tsc/ESLint
 * toolchain: a plugin with a genuine type error is recorded once, then reads
 * green until the underlying finding disappears, at which point the stale entry
 * is pruned on the next --update-baseline.
 */

import fs from 'fs';
import path from 'path';
import { checkExtensions } from '../check';
import { BASELINE_FILE_NAME } from '../baseline';
import type { FindingsBaseline } from '../baseline';
import { cleanupTempProject, createTempProject, createVendorAdmin, writeFile, writePluginsConfig } from '../test-helpers';

const PLUGIN_PATH = 'custom/plugins/BaselinePlugin';
const SOURCE = path.join(PLUGIN_PATH, 'src/Resources/app/administration/src/main.ts');

describe('extension tooling findings baseline (e2e)', () => {
    let projectRoot: string;
    let administrationRoot: string;

    beforeAll(() => {
        projectRoot = createTempProject('sw-tooling-baseline-e2e-');
        administrationRoot = createVendorAdmin(projectRoot, { entitySchema: 'real' });

        writeFile(path.join(projectRoot, PLUGIN_PATH, 'composer.json'), '{}\n');
        writeFile(path.join(projectRoot, SOURCE), ["export const brokenValue: number = 'not a number';"]);
        writePluginsConfig(projectRoot, [
            {
                technicalName: 'BaselinePlugin',
                basePath: `${PLUGIN_PATH}/src`,
                administrationPath: 'Resources/app/administration/src',
            },
        ]);
    });

    afterAll(() => {
        cleanupTempProject(projectRoot);
    });

    function readBaselineFile(): FindingsBaseline {
        return JSON.parse(
            fs.readFileSync(path.join(projectRoot, PLUGIN_PATH, BASELINE_FILE_NAME), 'utf8'),
        ) as FindingsBaseline;
    }

    it('records a real type error, greens it, then prunes it once fixed', async () => {
        const options = { projectRoot, administrationRoot, only: 'BaselinePlugin' };

        // 1. The genuine type error fails the check with no baseline in play.
        const initial = await checkExtensions(options);

        expect(initial.exitCode).toBe(1);
        expect(initial.results[0].typescript.status).toBe('failed');
        expect(initial.results[0].typescript.newFindings).toBeGreaterThan(0);

        // 2. Recording the baseline accepts the current findings and exits 0.
        const recorded = await checkExtensions({ ...options, updateBaseline: true });

        expect(recorded.exitCode).toBe(0);
        expect(recorded.baselineUpdates).toHaveLength(1);

        const baseline = readBaselineFile();

        expect(baseline.typescript.length).toBeGreaterThan(0);
        // Stored relative to the plugin root (no custom/plugins prefix) so it travels with the plugin.
        expect(baseline.typescript[0].file).toBe('src/Resources/app/administration/src/main.ts');

        // 3. The next check reads the same error as baselined — a green pass.
        const rechecked = await checkExtensions(options);

        expect(rechecked.exitCode).toBe(0);
        expect(rechecked.results[0].typescript.status).toBe('passed');
        expect(rechecked.results[0].typescript.baselinedFindings).toBeGreaterThan(0);

        // 4. Fixing the source leaves the baseline entry stale; re-recording prunes it.
        writeFile(path.join(projectRoot, SOURCE), ['export const goodValue: number = 42;']);

        const pruned = await checkExtensions({ ...options, updateBaseline: true });

        expect(pruned.exitCode).toBe(0);
        expect(pruned.baselineUpdates.join('\n')).toContain('pruned');
        expect(readBaselineFile().typescript).toHaveLength(0);
    }, 180000);
});
