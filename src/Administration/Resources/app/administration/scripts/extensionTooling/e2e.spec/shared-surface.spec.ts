/**
 * @sw-package framework
 *
 * End-to-end validation of the shared Administration type surface itself. A
 * clean extension program must emit no diagnostics from the shipped surface;
 * a diagnostic that originates outside the extension root is a tooling/surface
 * failure — reported as such, fatal, and never baselineable as plugin debt.
 */

import fs from 'fs';
import path from 'path';
import { checkExtensions } from '../check';
import { cleanupTempProject, createTempProject, createVendorAdmin, writeFile, writePluginsConfig } from '../test-helpers';

const CHECK_TIMEOUT = 300000;

function writeMinimalPlugin(projectRoot: string): void {
    writeFile(path.join(projectRoot, 'custom/plugins/Plug/composer.json'), '{}\n');
    writeFile(path.join(projectRoot, 'custom/plugins/Plug/src/Resources/app/administration/src/main.ts'), [
        'export const ok: number = 1;',
    ]);
    writePluginsConfig(projectRoot, [
        {
            technicalName: 'Plug',
            basePath: 'custom/plugins/Plug/src',
            administrationPath: 'Resources/app/administration/src',
        },
    ]);
}

describe('extension tooling shared type surface (e2e)', () => {
    it(
        'compiles the shared surface clean alongside a minimal extension (clean-room)',
        async () => {
            const projectRoot = createTempProject('sw-tooling-surface-clean-');
            const administrationRoot = createVendorAdmin(projectRoot, { entitySchema: 'real' });

            try {
                writeMinimalPlugin(projectRoot);

                const check = await checkExtensions({ projectRoot, administrationRoot, only: 'Plug' });

                // No diagnostics from the shipped surface: a real pass, not a surface tooling-error.
                expect(check.results[0].typescript.status).toBe('passed');
                expect(check.exitCode).toBe(0);
            } finally {
                cleanupTempProject(projectRoot);
            }
        },
        CHECK_TIMEOUT,
    );

    it(
        'flags a diagnostic outside the extension root as a non-baselineable surface failure',
        async () => {
            const projectRoot = createTempProject('sw-tooling-surface-broken-');
            const administrationRoot = createVendorAdmin(projectRoot, { entitySchema: 'real' });

            try {
                writeMinimalPlugin(projectRoot);

                // Inject a type error into the shared surface (copied into the fake
                // admin, so the real global.types.ts is untouched). It is pulled into
                // the extension program via admin-types.d.ts's import.
                fs.appendFileSync(
                    path.join(administrationRoot, 'src', 'global.types.ts'),
                    "\nexport const __swSurfaceProbe: number = 'not a number';\n",
                );

                const check = await checkExtensions({ projectRoot, administrationRoot, only: 'Plug' });

                // The out-of-root diagnostic is a fatal surface failure (not a
                // toolchain crash), named and counted, never a plugin finding.
                expect(check.results[0].typescript.status).toBe('failed');
                expect(check.results[0].typescript.surfaceDiagnostics).toBeGreaterThan(0);
                expect(check.results[0].typescript.output).toContain('shared Administration type surface');
                expect(check.results[0].typescript.output).toContain('global.types.ts');
                expect(check.exitCode).toBe(1);

                // A surface failure is never recorded as a plugin baseline: the
                // update warns, records nothing (no in-root debt), and still fails.
                const update = await checkExtensions({
                    projectRoot,
                    administrationRoot,
                    only: 'Plug',
                    updateBaseline: true,
                });

                expect(update.exitCode).toBe(1);
                expect(update.warnings.join('\n')).toContain('type-surface diagnostic');
                expect(fs.existsSync(path.join(projectRoot, 'custom/plugins/Plug/.shopware-admin-baseline.json'))).toBe(
                    false,
                );
            } finally {
                cleanupTempProject(projectRoot);
            }
        },
        CHECK_TIMEOUT,
    );
});
