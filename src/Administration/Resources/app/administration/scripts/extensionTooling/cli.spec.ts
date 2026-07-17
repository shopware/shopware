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
        { name: '--explain', description: 'Verbose report.' },
        { name: '--shim', value: 'required', valueName: '<TechnicalName>|all-custom', description: 'Bridge one extension.' },
        { name: '--project-root', value: 'required', valueName: '<path>', description: '', internal: true },
    ],
};

describe('scripts/extensionTooling/cli', () => {
    describe('parseCli', () => {
        it('parses boolean flags and value options', () => {
            const parsed = parseCli(
                [
                    '--check',
                    '--shim=SwagPayPal',
                    '--project-root=/srv/shop',
                ],
                COMMAND,
            );

            expect(parsed.help).toBe(false);
            expect(parsed.flags.has('--check')).toBe(true);
            expect(parsed.values['--shim']).toBe('SwagPayPal');
            expect(parsed.values['--project-root']).toBe('/srv/shop');
        });

        it('rejects unknown flags with a suggestion for close misspellings', () => {
            expect(() => parseCli(['--chekc'], COMMAND)).toThrow(CliUsageError);
            expect(() => parseCli(['--chekc'], COMMAND)).toThrow('Unknown option --chekc. Did you mean --check?');
        });

        it('rejects unknown flags without a suggestion when nothing is close', () => {
            expect(() => parseCli(['--frobnicate'], COMMAND)).toThrow('Unknown option --frobnicate.');
            expect(() => parseCli(['--frobnicate'], COMMAND)).not.toThrow('Did you mean');
        });

        it('rejects value flags without a value', () => {
            expect(() => parseCli(['--shim'], COMMAND)).toThrow('requires a value: --shim=<TechnicalName>|all-custom');
            expect(() => parseCli(['--shim='], COMMAND)).toThrow('requires a value');
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
        it('renders the composer usage with the "--" separator and hides internal flags', () => {
            const help = renderHelp(COMMAND);

            expect(help).toContain('composer admin:setup-extension-tooling -- [options]');
            expect(help).toContain('composer swallows options placed before it');
            expect(help).toContain('--check');
            expect(help).toContain('--shim=<TechnicalName>|all-custom');
            expect(help).toContain('-h, --help');
            expect(help).not.toContain('--project-root');
        });
    });
});
