/**
 * @sw-package framework
 */

import { EXIT_FINDINGS, EXIT_OK, EXIT_TOOL_ERROR, EXIT_USAGE, runCli } from './cli';
import type { CliDependencies } from './cli';
import type { AdminRoot, CheckReport, RootReport } from './shared';
import type { RunnerOptions } from './runner';

const adminRoot = (overrides: Partial<AdminRoot> = {}): AdminRoot => ({
    bundleName: 'SwagExample',
    technicalName: 'swag-example',
    extensionName: 'SwagExample',
    extensionRoot: '/project/custom/plugins/SwagExample',
    sourcePath: '/project/custom/plugins/SwagExample/src/Resources/app/administration/src',
    adminFolder: '/project/custom/plugins/SwagExample/src/Resources/app/administration',
    slug: 'swag-example',
    platform: false,
    ...overrides,
});

function setup(options: {
    roots?: AdminRoot[];
    discoverError?: Error;
    report?: (runnerOptions: RunnerOptions) => CheckReport;
}) {
    const out: string[] = [];
    const err: string[] = [];
    const runnerCalls: RunnerOptions[] = [];
    const cleanRun = (root: AdminRoot): RootReport => ({
        root,
        runs: [
            {
                tool: 'types',
                filesChecked: 3,
                findings: [],
                externalFindings: 0,
                unresolvedHostModules: 0,
                errors: [],
            },
        ],
    });

    const dependencies: Partial<CliDependencies> = {
        io: {
            out: (text) => out.push(text),
            err: (text) => err.push(text),
        },
        projectRoot: '/project',
        administrationRoot: '/project/src/Administration/Resources/app/administration',
        discover: () => {
            if (options.discoverError) {
                throw options.discoverError;
            }

            return options.roots ?? [adminRoot()];
        },
        run: (runnerOptions) => {
            runnerCalls.push(runnerOptions);

            return options.report?.(runnerOptions) ?? { roots: runnerOptions.roots.map(cleanRun), errors: [] };
        },
    };

    return {
        out,
        err,
        runnerCalls,
        run: (argv: string[]) => runCli(argv, dependencies),
    };
}

describe('scripts/extensionTooling/cli', () => {
    it('exits 0 for a clean run and reports the file count', () => {
        const cli = setup({});

        expect(cli.run([])).toBe(EXIT_OK);
        expect(cli.out.join('')).toContain('types: 3 files type-checked');
    });

    it('exits 1 on findings', () => {
        const cli = setup({
            report: (options) => ({
                roots: options.roots.map((root) => ({
                    root,
                    runs: [
                        {
                            tool: 'types',
                            filesChecked: 3,
                            findings: [
                                {
                                    file: 'custom/plugins/SwagExample/main.ts',
                                    line: 1,
                                    column: 1,
                                    severity: 'error',
                                    rule: 'TS2322',
                                    message: 'nope',
                                },
                            ],
                            externalFindings: 0,
                            unresolvedHostModules: 0,
                            errors: [],
                        },
                    ],
                })),
                errors: [],
            }),
        });

        expect(cli.run([])).toBe(EXIT_FINDINGS);
        expect(cli.out.join('')).toContain('TS2322');
    });

    it('exits 2 on an unknown option', () => {
        const cli = setup({});

        expect(cli.run(['--nope'])).toBe(EXIT_USAGE);
        expect(cli.err.join('')).toContain('Usage: administration:extension:check');
    });

    it('exits 2 on an unknown extension name and lists the known ones', () => {
        const cli = setup({});

        expect(cli.run(['NotInstalled'])).toBe(EXIT_USAGE);
        expect(cli.err.join('')).toContain('Unknown extension: NotInstalled.');
        expect(cli.err.join('')).toContain('SwagExample');
    });

    it('exits 2 when --fix is combined with --types only', () => {
        const cli = setup({});

        expect(
            cli.run([
                '--types',
                '--fix',
            ]),
        ).toBe(EXIT_USAGE);
        expect(cli.err.join('')).toContain('requires linting');
    });

    it('exits 3 when the bundle configuration cannot be read', () => {
        const cli = setup({
            discoverError: new Error('No bundle configuration found. Run "bin/console bundle:dump" first.'),
        });

        expect(cli.run([])).toBe(EXIT_TOOL_ERROR);
        expect(cli.err.join('')).toContain('bin/console bundle:dump');
    });

    it('exits 3 when a run checked zero files', () => {
        const cli = setup({
            report: (options) => ({
                roots: options.roots.map((root) => ({
                    root,
                    runs: [
                        {
                            tool: 'lint',
                            filesChecked: 0,
                            findings: [],
                            externalFindings: 0,
                            unresolvedHostModules: 0,
                            errors: [],
                        },
                    ],
                })),
                errors: [],
            }),
        });

        expect(cli.run([])).toBe(EXIT_TOOL_ERROR);
        expect(cli.out.join('')).toContain('Checked 0 files for SwagExample (lint)');
    });

    it('exits 3 when nothing was discovered', () => {
        const cli = setup({ roots: [] });

        expect(cli.run([])).toBe(EXIT_TOOL_ERROR);
        expect(cli.out.join('')).toContain('No Administration extension sources were checked.');
    });

    it('runs both tools by default', () => {
        const cli = setup({});

        cli.run([]);

        expect(cli.runnerCalls[0]).toMatchObject({ types: true, lint: true, fix: false });
    });

    it('runs only the requested tool', () => {
        const types = setup({});
        types.run(['--types']);

        expect(types.runnerCalls[0]).toMatchObject({ types: true, lint: false });

        const lint = setup({});
        lint.run([
            '--lint',
            '--fix',
        ]);

        expect(lint.runnerCalls[0]).toMatchObject({ types: false, lint: true, fix: true });
    });

    it('excludes platform roots by default and says so', () => {
        const cli = setup({
            roots: [
                adminRoot(),
                adminRoot({ bundleName: 'Storefront', extensionName: 'Storefront', slug: 'storefront', platform: true }),
            ],
        });

        expect(cli.run([])).toBe(EXIT_OK);
        expect(cli.runnerCalls[0].roots.map((root) => root.bundleName)).toEqual(['SwagExample']);
        expect(cli.out.join('')).toContain('Skipped 1 platform source roots.');
    });

    it('includes platform roots with --include-platform', () => {
        const cli = setup({
            roots: [
                adminRoot(),
                adminRoot({ bundleName: 'Storefront', extensionName: 'Storefront', slug: 'storefront', platform: true }),
            ],
        });

        cli.run(['--include-platform']);

        expect(cli.runnerCalls[0].roots.map((root) => root.bundleName)).toEqual([
            'SwagExample',
            'Storefront',
        ]);
    });

    it('prints the help without running anything', () => {
        const cli = setup({});

        expect(cli.run(['--help'])).toBe(EXIT_OK);
        expect(cli.runnerCalls).toHaveLength(0);
        expect(cli.out.join('')).toContain('Exit codes:');
    });
});
