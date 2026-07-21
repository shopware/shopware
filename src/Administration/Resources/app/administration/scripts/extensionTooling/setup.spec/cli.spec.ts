/**
 * @sw-package framework
 *
 * CLI entry behavior of `admin:setup-extension-tooling`: usage errors and
 * help must exit before anything is written.
 */

import fs from 'fs';
import path from 'path';
import { runSetupCli } from '../setup';
import {
    cleanupTempProject,
    createSkeletonAdmin,
    createTempProject,
    syntheticEntitySchema,
    writeFile,
    writePluginsConfig,
    writeZeroConfigPlugin,
} from '../test-helpers';

describe('scripts/extensionTooling/setup runSetupCli', () => {
    let projectRoot: string;
    let administrationRoot: string;

    beforeEach(() => {
        projectRoot = createTempProject('sw-tooling-setup-cli-');
        administrationRoot = createSkeletonAdmin(projectRoot);
        fs.mkdirSync(path.join(administrationRoot, 'src'), { recursive: true });
        writeFile(path.join(administrationRoot, 'src', 'entity-schema-definition.d.ts'), syntheticEntitySchema);
        writeZeroConfigPlugin({ projectRoot, pluginPath: 'custom/plugins/ZeroConfig' });
        writePluginsConfig(projectRoot, [
            {
                technicalName: 'ZeroConfig',
                basePath: 'custom/plugins/ZeroConfig/src',
                administrationPath: 'Resources/app/administration/src',
            },
        ]);
    });

    afterEach(() => {
        cleanupTempProject(projectRoot);
    });

    function listTree(root: string): string[] {
        return (fs.readdirSync(root, { recursive: true }) as string[]).sort();
    }

    it('rejects a mistyped flag with exit 2 and writes nothing', () => {
        const errorSpy = jest.spyOn(console, 'error').mockImplementation(() => {});
        const treeBefore = listTree(projectRoot);

        const exitCode = runSetupCli([
            '--chekc',
            `--project-root=${projectRoot}`,
            `--administration-root=${administrationRoot}`,
        ]);

        expect(exitCode).toBe(2);
        expect(listTree(projectRoot)).toEqual(treeBefore);
        expect(errorSpy.mock.calls.join('\n')).toContain('Did you mean --check?');
        errorSpy.mockRestore();
    });

    it('prints help with exit 0 before resolving PROJECT_ROOT and writes nothing', () => {
        const previousProjectRoot = process.env.PROJECT_ROOT;
        delete process.env.PROJECT_ROOT;

        const logSpy = jest.spyOn(console, 'log').mockImplementation(() => {});
        const treeBefore = listTree(projectRoot);

        const exitCode = runSetupCli(['--help']);

        expect(exitCode).toBe(0);
        expect(listTree(projectRoot)).toEqual(treeBefore);
        expect(logSpy.mock.calls.join('\n')).toContain('composer admin:setup-extension-tooling -- [options]');
        logSpy.mockRestore();

        if (previousProjectRoot !== undefined) {
            process.env.PROJECT_ROOT = previousProjectRoot;
        }
    });

    it('makes --explain read-only — it writes nothing and exits 0 even when setup is stale', () => {
        const logSpy = jest.spyOn(console, 'log').mockImplementation(() => {});
        const treeBefore = listTree(projectRoot);

        const exitCode = runSetupCli([
            '--explain',
            `--project-root=${projectRoot}`,
            `--administration-root=${administrationRoot}`,
        ]);

        // Read-only: nothing on disk changed, and (unlike --check) drift does not
        // gate the exit code — a human inspecting the setup gets exit 0.
        expect(exitCode).toBe(0);
        expect(listTree(projectRoot)).toEqual(treeBefore);
        expect(logSpy.mock.calls.join('\n')).toContain('would create');
        logSpy.mockRestore();
    });

    it('rejects --root-config without --shim as a usage error and writes nothing', () => {
        const errorSpy = jest.spyOn(console, 'error').mockImplementation(() => {});
        const treeBefore = listTree(projectRoot);

        const exitCode = runSetupCli([
            '--root-config=.',
            `--project-root=${projectRoot}`,
            `--administration-root=${administrationRoot}`,
        ]);

        expect(exitCode).toBe(2);
        expect(listTree(projectRoot)).toEqual(treeBefore);
        expect(errorSpy.mock.calls.join('\n')).toContain('--root-config only applies together with --shim');
        errorSpy.mockRestore();
    });

    it('treats a missing PROJECT_ROOT as a usage error with exit 2', () => {
        const previousProjectRoot = process.env.PROJECT_ROOT;
        delete process.env.PROJECT_ROOT;

        const errorSpy = jest.spyOn(console, 'error').mockImplementation(() => {});

        const exitCode = runSetupCli(['--check']);

        expect(exitCode).toBe(2);
        expect(errorSpy.mock.calls.join('\n')).toContain('PROJECT_ROOT or --project-root is required.');
        errorSpy.mockRestore();

        if (previousProjectRoot !== undefined) {
            process.env.PROJECT_ROOT = previousProjectRoot;
        }
    });
});
