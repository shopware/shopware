/**
 * @sw-package framework
 */

import { EXIT_CODES } from './constants';
import { runCli } from './index';

describe('admin-plugin-compatibility runner entrypoint', () => {
    it('prints help and exits successfully', async () => {
        const { io, output } = createIo();

        await expect(runCli(['--help'], {}, io)).resolves.toBe(EXIT_CODES.success);

        expect(output.stdout).toContain('Usage: composer admin:plugin-compatibility');
        expect(output.stderr).toBe('');
    });

    it('fails before plugin work when CI is detected', async () => {
        const { io, output } = createIo();

        await expect(runCli([], { CI: '1' }, io)).resolves.toBe(EXIT_CODES.ci);

        expect(output.stderr).toContain('"status": "failed"');
        expect(output.stderr).toContain('"CI"');
        expect(output.stdout).toBe('');
    });
});

function createIo() {
    const output = {
        stdout: '',
        stderr: '',
    };

    const io = {
        stdout: {
            write: (message: string) => {
                output.stdout += message;
            },
        },
        stderr: {
            write: (message: string) => {
                output.stderr += message;
            },
        },
    };

    return { io, output };
}
