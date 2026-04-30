/**
 * @sw-package framework
 */

import { buildShellCommand, quoteShellArg, runCommand } from './command';

describe('admin-plugin-compatibility command helpers', () => {
    it('quotes shell arguments with spaces', () => {
        expect(quoteShellArg('docker compose exec web bin/console')).toBe("'docker compose exec web bin/console'");
    });

    it('escapes single quotes in shell arguments', () => {
        expect(quoteShellArg("host'with-quote")).toBe("'host'\\''with-quote'");
    });

    it('builds shell commands with escaped arguments', () => {
        expect(buildShellCommand('bin/console', [
            'system:config:get',
            'core.store.licenseHost',
            'value with space',
        ])).toBe("bin/console system:config:get core.store.licenseHost 'value with space'");
    });

    it('captures command output, duration, and exit code', async () => {
        const result = await runCommand({
            name: 'test:command',
            phase: 'setup',
            cwd: process.cwd(),
            command: 'node -e "process.stdout.write(\'out\'); process.stderr.write(\'err\')"',
        });

        expect(result).toEqual(expect.objectContaining({
            name: 'test:command',
            stdout: 'out',
            stderr: 'err',
            exitCode: 0,
        }));
        expect(result.durationMs).toBeGreaterThanOrEqual(0);
        expect(result.startedAt).toEqual(expect.any(String));
    });
});
