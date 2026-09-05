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
        expect(errorSpy.mock.calls.join('\n')).toContain('Unknown option --chekc. See --help');
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
        // --help is the flag reference, so it must carry the caveat that the flags
        // it documents are not a stable contract.
        expect(logSpy.mock.calls.join('\n')).toContain('EXPERIMENTAL');
        logSpy.mockRestore();

        if (previousProjectRoot !== undefined) {
            process.env.PROJECT_ROOT = previousProjectRoot;
        }
    });

    it('rejects a malformed --root-config value as a usage error and writes nothing', () => {
        const errorSpy = jest.spyOn(console, 'error').mockImplementation(() => {});
        const treeBefore = listTree(projectRoot);

        const exitCode = runSetupCli([
            '--root-config=just-a-dir',
            `--project-root=${projectRoot}`,
            `--administration-root=${administrationRoot}`,
        ]);

        expect(exitCode).toBe(2);
        expect(listTree(projectRoot)).toEqual(treeBefore);
        expect(errorSpy.mock.calls.join('\n')).toContain('--root-config expects <Extension>:<dir>');
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

    describe('--if-enabled feature-flag gate', () => {
        const previousFlag = process.env.ADMIN_EXTENSION_TOOLING;

        afterEach(() => {
            if (previousFlag === undefined) {
                delete process.env.ADMIN_EXTENSION_TOOLING;
            } else {
                process.env.ADMIN_EXTENSION_TOOLING = previousFlag;
            }
        });

        it('skips with exit 0 and writes nothing when the flag is unset', () => {
            delete process.env.ADMIN_EXTENSION_TOOLING;

            const logSpy = jest.spyOn(console, 'log').mockImplementation(() => {});
            const treeBefore = listTree(projectRoot);

            const exitCode = runSetupCli([
                '--if-enabled',
                `--project-root=${projectRoot}`,
                `--administration-root=${administrationRoot}`,
            ]);

            expect(exitCode).toBe(0);
            expect(listTree(projectRoot)).toEqual(treeBefore);
            expect(logSpy.mock.calls.join('\n')).toContain('ADMIN_EXTENSION_TOOLING');
            logSpy.mockRestore();
        });

        it.each([
            '',
            '0',
            'false',
        ])('treats ADMIN_EXTENSION_TOOLING=%j as disabled, mirroring Feature::isTrue', (value) => {
            process.env.ADMIN_EXTENSION_TOOLING = value;

            const logSpy = jest.spyOn(console, 'log').mockImplementation(() => {});
            const treeBefore = listTree(projectRoot);

            const exitCode = runSetupCli([
                '--if-enabled',
                `--project-root=${projectRoot}`,
                `--administration-root=${administrationRoot}`,
            ]);

            expect(exitCode).toBe(0);
            expect(listTree(projectRoot)).toEqual(treeBefore);
            logSpy.mockRestore();
        });

        it.each([
            '1',
            'true',
        ])('runs the full setup when ADMIN_EXTENSION_TOOLING=%j', (value) => {
            process.env.ADMIN_EXTENSION_TOOLING = value;

            const logSpy = jest.spyOn(console, 'log').mockImplementation(() => {});

            const exitCode = runSetupCli([
                '--if-enabled',
                `--project-root=${projectRoot}`,
                `--administration-root=${administrationRoot}`,
            ]);

            expect(exitCode).toBe(0);
            expect(fs.existsSync(path.join(projectRoot, 'var/admin-extension-tooling/manifest.json'))).toBe(true);
            logSpy.mockRestore();
        });

        it('skips before root resolution: no PROJECT_ROOT plus a disabled flag is not an error', () => {
            delete process.env.ADMIN_EXTENSION_TOOLING;
            const previousProjectRoot = process.env.PROJECT_ROOT;
            delete process.env.PROJECT_ROOT;

            const logSpy = jest.spyOn(console, 'log').mockImplementation(() => {});

            // The exit-2 "PROJECT_ROOT required" path must never fire for a
            // disabled flag — composer setup must stay green without any env.
            expect(runSetupCli(['--if-enabled'])).toBe(0);
            logSpy.mockRestore();

            if (previousProjectRoot !== undefined) {
                process.env.PROJECT_ROOT = previousProjectRoot;
            }
        });

        it('documents the flag in --help', () => {
            const logSpy = jest.spyOn(console, 'log').mockImplementation(() => {});

            runSetupCli(['--help']);

            expect(logSpy.mock.calls.join('\n')).toContain('--if-enabled');
            logSpy.mockRestore();
        });
    });
});
