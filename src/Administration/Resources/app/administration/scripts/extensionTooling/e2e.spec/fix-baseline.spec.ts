/**
 * @sw-package framework
 *
 * The guided two-step adoption flow end to end: `--fix` clears what it can,
 * then `--update-baseline` records the rest so the next check is green. Proves
 * the mutually-exclusive fix/baseline steps compose into a clean workflow.
 */

import fs from 'fs';
import path from 'path';
import { checkExtensions } from '../check';
import { renderCheckReport } from '../report';
import { cleanupTempProject, createTempProject, createVendorAdmin, writeFile, writePluginsConfig } from '../test-helpers';

const CHECK_TIMEOUT = 300000;

describe('extension tooling fix -> baseline workflow (e2e)', () => {
    it(
        'auto-fixes what it can, then baselines the remainder to green',
        async () => {
            const projectRoot = createTempProject('sw-tooling-fix-baseline-');
            const administrationRoot = createVendorAdmin(projectRoot, { entitySchema: 'real' });
            const sourceRoot = path.join(projectRoot, 'custom/plugins/FixBase/src/Resources/app/administration/src');
            const fixablePath = path.join(sourceRoot, 'fixable.js');
            const baselinePath = path.join(projectRoot, 'custom/plugins/FixBase/.shopware-admin-baseline.json');

            try {
                writeFile(path.join(projectRoot, 'custom/plugins/FixBase/composer.json'), '{}\n');
                // A persistent TypeScript finding — ESLint --fix cannot touch it.
                writeFile(path.join(sourceRoot, 'main.ts'), ["export const brokenValue: number = 'not a number';"]);
                // An auto-fixable ESLint finding (no-extra-boolean-cast).
                writeFile(fixablePath, [
                    'const enabled = true;',
                    'if (!!enabled) {',
                    '    document.title = String(enabled);',
                    '}',
                    'export default enabled;',
                ]);
                writePluginsConfig(projectRoot, [
                    {
                        technicalName: 'FixBase',
                        basePath: 'custom/plugins/FixBase/src',
                        administrationPath: 'Resources/app/administration/src',
                    },
                ]);

                // 1. Both findings present — the check fails.
                const before = await checkExtensions({ projectRoot, administrationRoot, only: 'FixBase' });

                expect(before.results[0].typescript.status).toBe('failed');
                expect(before.results[0].eslint.status).toBe('failed');
                expect(before.exitCode).toBe(1);

                // 2. --fix removes the auto-fixable ESLint finding; the TS one persists.
                const fixed = await checkExtensions({ projectRoot, administrationRoot, only: 'FixBase', fix: true });

                expect(fs.readFileSync(fixablePath, 'utf8')).not.toContain('!!');
                expect(fixed.results[0].eslint.status).toBe('passed');
                expect(fixed.results[0].typescript.status).toBe('failed');
                expect(fixed.exitCode).toBe(1);

                // 3. Record the remainder as the baseline (the exact hinted follow-up).
                const recorded = await checkExtensions({
                    projectRoot,
                    administrationRoot,
                    only: 'FixBase',
                    updateBaseline: true,
                });

                expect(recorded.exitCode).toBe(0);
                expect(fs.existsSync(baselinePath)).toBe(true);

                // 4. Next check is green — the fixed finding gone, the rest baselined.
                const after = await checkExtensions({ projectRoot, administrationRoot, only: 'FixBase' });

                expect(after.results[0].typescript.status).toBe('passed');
                expect(after.results[0].eslint.status).toBe('passed');
                expect(after.exitCode).toBe(0);

                // 5. Green is not a dead end: the suppressed findings are still
                // identifiable, so baselining stays reversible. Asserted on the
                // real diff of real tool output, not a fixture.
                expect(after.results[0].typescript.baselinedFindingRefs).toEqual([
                    { file: 'custom/plugins/FixBase/src/Resources/app/administration/src/main.ts', code: 'TS2322' },
                ]);
                expect(renderCheckReport(after, { verbose: false })).toContain('show with -- --verbose');
                expect(renderCheckReport(after, { verbose: true })).toContain('baselined — suppressed (1):');
            } finally {
                cleanupTempProject(projectRoot);
            }
        },
        CHECK_TIMEOUT,
    );
});
