/**
 * @sw-package framework
 */

import { loadRegistry } from '../../eslint-rules/deprecation-rules/registry/load-registry';
import {
    getFixLevel,
    getLeastAutomaticFixLevel,
    getRunnableMigrations,
    parseArgs,
    selectMigrations,
    validateSelection,
} from './migration-selection';

type Usage = { kind: string; fix: string };

function migration(id: string, usage: Usage[] = [{ kind: 'rename-prop', fix: 'auto' }]) {
    return { id, usage };
}

const migrations = [
    migration('component.sw-alert'),
    migration('component.sw-button'),
];

describe('scripts/deprecation-migrate/migration-selection', () => {
    describe('parseArgs', () => {
        it('reads the boolean flags', () => {
            expect(
                parseArgs([
                    '--all',
                    '--fix',
                    '--list',
                    '--no-partial',
                    '-h',
                ]),
            ).toEqual({
                all: true,
                fix: true,
                help: true,
                ids: [],
                list: true,
                partial: false,
            });
        });

        it('allows partial conversions by default', () => {
            expect(parseArgs(['--all']).partial).toBe(true);
        });

        it('reads an id given as a separate value or with an equals sign', () => {
            expect(parseArgs(['--id', 'component.sw-alert']).ids).toEqual(['component.sw-alert']);
            expect(parseArgs(['--id=component.sw-alert']).ids).toEqual(['component.sw-alert']);
        });

        it('collects every repeated id', () => {
            expect(parseArgs(['--id', 'component.sw-alert', '--id=component.sw-button']).ids).toEqual([
                'component.sw-alert',
                'component.sw-button',
            ]);
        });

        it('rejects an id option without a value', () => {
            expect(() => parseArgs(['--id'])).toThrow('--id requires a registry id.');
            expect(() => parseArgs(['--id', '--fix'])).toThrow('--id requires a registry id.');
        });

        it('rejects an unknown option', () => {
            expect(() => parseArgs(['--dry-run'])).toThrow('Unknown option "--dry-run"');
            expect(() => parseArgs(['--report'])).toThrow('Unknown option "--report"');
        });
    });

    describe('validateSelection', () => {
        it('rejects --all together with --id', () => {
            expect(() => validateSelection(parseArgs(['--all', '--id', 'component.sw-alert']), migrations)).toThrow(
                'Use either --all or --id, not both.',
            );
        });

        it('rejects a run without a selection', () => {
            expect(() => validateSelection(parseArgs(['--fix']), migrations)).toThrow(
                'Choose --all, --id <id>, --list, or --help.',
            );
        });

        it('accepts a selection without --fix as a report run', () => {
            const args = parseArgs(['--all']);

            expect(() => validateSelection(args, migrations)).not.toThrow();
            expect(args.fix).toBe(false);
        });

        it('rejects unknown ids and lists them', () => {
            expect(() =>
                validateSelection(
                    parseArgs([
                        '--id',
                        'component.sw-alert',
                        '--id',
                        'x',
                        '--id',
                        'y',
                    ]),
                    migrations,
                ),
            ).toThrow('Unknown migration id(s): x, y.');
        });
    });

    describe('selectMigrations', () => {
        it('selects every migration for --all and only the listed ids otherwise', () => {
            expect(selectMigrations(parseArgs(['--all']), migrations)).toEqual(migrations);
            expect(selectMigrations(parseArgs(['--id', 'component.sw-button']), migrations)).toEqual([migrations[1]]);
        });
    });

    describe('fix levels', () => {
        it('ranks manual over unsafe-auto over auto', () => {
            expect(getLeastAutomaticFixLevel(['auto', 'unsafe-auto', 'manual'])).toBe('manual');
            expect(getLeastAutomaticFixLevel(['auto', 'unsafe-auto'])).toBe('unsafe-auto');
            expect(getLeastAutomaticFixLevel(['auto'])).toBe('auto');
        });


        it('rates a migration by its least automatic usage', () => {
            expect(
                getFixLevel(
                    migration('component.sw-alert', [
                        { kind: 'rename-prop', fix: 'auto' },
                        { kind: 'remove-slot', fix: 'unsafe-auto' },
                    ]),
                ),
            ).toBe('unsafe-auto');
        });
    });

    describe('getRunnableMigrations', () => {
        it('offers every migration list of the registry', () => {
            const registry = {
                componentApiMigrations: [migration('component.sw-button')],
                futureMigrations: [migration('future.example')],
                assetMigrations: [migration('asset.example')],
            };

            expect(getRunnableMigrations(registry).map((entry: { id: string }) => entry.id)).toEqual([
                'component.sw-button',
                'future.example',
            ]);
        });

        it('does not offer asset migrations, which no lint rule can fix', () => {
            const runnableMigrations = getRunnableMigrations(loadRegistry());

            expect(runnableMigrations.filter((entry: { id: string }) => entry.id.startsWith('asset.'))).toEqual([]);
            expect(() =>
                validateSelection({ all: false, ids: ['asset.admin-png-jpg-to-webp'], fix: true }, runnableMigrations),
            ).toThrow('Unknown migration id(s): asset.admin-png-jpg-to-webp');
        });
    });
});
