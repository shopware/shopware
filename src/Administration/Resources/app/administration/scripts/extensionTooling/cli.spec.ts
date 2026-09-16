/**
 * @sw-package framework
 *
 * The usage contract of the shared CLI layer: what parses, what is rejected,
 * and that `--help` renders every declared flag with the composer `--` caveat.
 */

import { CliUsageError, parseCli, renderHelp } from './cli';
import type { CommandSpec } from './cli';

const COMMAND: CommandSpec = {
    command: 'admin:setup-extension-tooling',
    description: 'Generate configs for extensions.',
    flags: [
        { name: '--check', description: 'Report what would change, write nothing.' },
        {
            name: '--root-config',
            value: 'required',
            valueName: '<Extension>:<dir>',
            description: 'Bridge a multi-root extension beside one package config.',
        },
        { name: '--project-root', value: 'required', valueName: '<path>', description: 'Shop root to set up.' },
    ],
};

describe('scripts/extensionTooling/cli', () => {
    describe('parseCli', () => {
        it('parses boolean flags and value options', () => {
            const parsed = parseCli(
                [
                    '--check',
                    '--root-config=Swag:.',
                    '--project-root=/srv/shop',
                ],
                COMMAND,
            );

            expect(parsed.help).toBe(false);
            expect(parsed.flags.has('--check')).toBe(true);
            expect(parsed.values['--root-config']).toBe('Swag:.');
            expect(parsed.values['--project-root']).toBe('/srv/shop');
        });

        it('rejects unknown flags, missing values, values on boolean flags, and positionals', () => {
            const rejected = [
                ['--chekc'],
                ['--root-config'],
                ['--root-config='],
                ['--check=yes'],
                ['SwagPayPal'],
            ];

            for (const argv of rejected) {
                expect(() => parseCli(argv, COMMAND)).toThrow(CliUsageError);
            }

            // The two messages a mistyped invocation must stay actionable on.
            expect(() => parseCli(['--chekc'], COMMAND)).toThrow(
                'Unknown option --chekc. See --help for the available options.',
            );
            expect(() => parseCli(['--root-config='], COMMAND)).toThrow(
                '--root-config requires a value: --root-config=<Extension>:<dir>',
            );
        });

        it('short-circuits on --help / -h without validating the rest', () => {
            expect(
                parseCli(
                    [
                        '--bogus',
                        '--help',
                    ],
                    COMMAND,
                ).help,
            ).toBe(true);
            expect(parseCli(['-h'], COMMAND).help).toBe(true);
        });
    });

    describe('renderHelp', () => {
        it('renders the composer usage with the "--" separator and every declared flag', () => {
            const help = renderHelp(COMMAND);

            expect(help).toContain('composer admin:setup-extension-tooling -- [options]');
            expect(help).toContain('composer swallows options placed before it');
            expect(help).toContain('--check');
            expect(help).toContain('--root-config=<Extension>:<dir>');
            expect(help).toContain('--project-root=<path>');
            expect(help).toContain('-h, --help');
        });
    });
});
