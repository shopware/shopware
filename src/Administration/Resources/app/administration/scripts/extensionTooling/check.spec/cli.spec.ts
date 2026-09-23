/**
 * @sw-package framework
 */

import path from 'path';
import { runCheckCli } from '../check';
import {
    cleanupTempProject,
    createSkeletonAdmin,
    createTempProject,
    syntheticEntitySchema,
    writeFile,
} from '../test-helpers';

describe('scripts/extensionTooling/check runCheckCli', () => {
    let projectRoot: string;
    let administrationRoot: string;

    beforeEach(() => {
        projectRoot = createTempProject('sw-tooling-check-');
        administrationRoot = createSkeletonAdmin(projectRoot);
        writeFile(path.join(administrationRoot, 'src', 'entity-schema-definition.d.ts'), syntheticEntitySchema);
    });

    afterEach(() => {
        cleanupTempProject(projectRoot);
    });

    it('rejects unknown flags with exit 2', async () => {
        const errorSpy = jest.spyOn(console, 'error').mockImplementation(() => {});

        const exitCode = await runCheckCli([
            '--nope',
            `--project-root=${projectRoot}`,
            `--administration-root=${administrationRoot}`,
        ]);

        expect(exitCode).toBe(2);
        expect(errorSpy.mock.calls.join('\n')).toContain('Unknown option --nope');
        errorSpy.mockRestore();
    });

    it('rejects --update-baseline together with --fix as a usage error', async () => {
        const errorSpy = jest.spyOn(console, 'error').mockImplementation(() => {});

        const exitCode = await runCheckCli([
            '--update-baseline',
            '--fix',
            `--project-root=${projectRoot}`,
            `--administration-root=${administrationRoot}`,
        ]);

        expect(exitCode).toBe(2);
        expect(errorSpy.mock.calls.join('\n')).toContain('mutually exclusive');
        errorSpy.mockRestore();
    });

    it('rejects a non-numeric --max-workers with exit 2', async () => {
        const errorSpy = jest.spyOn(console, 'error').mockImplementation(() => {});

        const exitCode = await runCheckCli([
            '--max-workers=abc',
            `--project-root=${projectRoot}`,
            `--administration-root=${administrationRoot}`,
        ]);

        expect(exitCode).toBe(2);
        expect(errorSpy.mock.calls.join('\n')).toContain('--max-workers must be a positive integer');
        errorSpy.mockRestore();
    });

    it('prints help with exit 0 without running any check', async () => {
        const logSpy = jest.spyOn(console, 'log').mockImplementation(() => {});

        const exitCode = await runCheckCli(['--help']);

        expect(exitCode).toBe(0);
        expect(logSpy.mock.calls.join('\n')).toContain('composer admin:check-extensions -- [options]');
        logSpy.mockRestore();
    });

    it('documents the layout flags so a Composer/Flex install can point at its shop root', async () => {
        const logSpy = jest.spyOn(console, 'log').mockImplementation(() => {});

        await runCheckCli(['--help']);
        const help = logSpy.mock.calls.join('\n');

        // These were internal (hidden) plumbing; a standard-install developer
        // needs them discoverable to run the tooling against a vendor/ layout.
        expect(help).toContain('--project-root');
        expect(help).toContain('--administration-root');
        logSpy.mockRestore();
    });
});
