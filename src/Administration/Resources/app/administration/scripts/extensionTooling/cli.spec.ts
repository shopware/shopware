/**
 * @sw-package framework
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

        it('rejects unknown flags with a usage error pointing at --help', () => {
            expect(() => parseCli(['--chekc'], COMMAND)).toThrow(CliUsageError);
            expect(() => parseCli(['--chekc'], COMMAND)).toThrow(
                'Unknown option --chekc. See --help for the available options.',
            );
        });

        it('rejects value flags without a value', () => {
            expect(() => parseCli(['--root-config'], COMMAND)).toThrow('requires a value: --root-config=<Extension>:<dir>');
            expect(() => parseCli(['--root-config='], COMMAND)).toThrow('requires a value');
        });

        it('rejects values on boolean flags and bare positionals', () => {
            expect(() => parseCli(['--check=yes'], COMMAND)).toThrow('--check does not take a value.');
            expect(() => parseCli(['SwagPayPal'], COMMAND)).toThrow('Unexpected argument "SwagPayPal"');
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
