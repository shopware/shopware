/**
 * @sw-package framework
 *
 * Spec runner for Administration extensions: runs each selected extension's
 * own Jest config with the Administration's Jest, so an extension needs no
 * Jest installation of its own. Setup runs first, so the `.shopware/jest.mjs`
 * bridge those configs import is current.
 *
 * vendor/ extensions are read-only third-party code, so their specs run only
 * when the selection names them. A run that tests nothing fails, so CI cannot
 * pass without running a spec.
 */

import { spawn, spawnSync } from 'child_process';
import { once } from 'events';
import fs from 'fs';
import path from 'path';
import type { StdioOptions } from 'child_process';
import { filterSelectedProjects, normalizeSelection } from './check';
import { CliUsageError, parseCli, renderHelp } from './cli';
import type { CommandSpec } from './cli';
import { promptSelection } from './select';
import { discoverProjects, setupExtensionTooling } from './setup';
import { pluginsConfigPath, relativePosix } from './shared';
import type { ExtensionToolingProject } from './shared';

/** The config file names Jest itself looks for, in its own order of preference. */
export const JEST_CONFIG_FILE_NAMES = [
    'jest.config.js',
    'jest.config.ts',
    'jest.config.mjs',
    'jest.config.cjs',
];

const COMPONENT_IMPORTS_FILE = 'test/_helper_/componentWrapper/component-imports.js';

/** Runs Jest with one config file and resolves with its exit code. */
export type JestRunner = (configPath: string, jestArgs: string[]) => Promise<number>;

export interface TestExtensionsOptions {
    projectRoot: string;
    administrationRoot: string;
    /**
     * Extension or bundle names to test. When empty or absent, every extension
     * with a Jest config runs, except vendor/ extensions.
     */
    only?: string | string[];
    /** Forwarded to every Jest run, e.g. `['--watch']` or a spec path pattern. */
    jestArgs?: string[];
    runJest?: JestRunner;
    /** Generates the component import map `wrapTestComponent` reads; returns the exit code. */
    generateComponentImports?: () => number;
}

export interface JestRun {
    extension: string;
    /** Relative to the project root (posix). */
    config: string;
    exitCode: number;
}

export interface TestExtensionsResult {
    runs: JestRun[];
    /** Extensions of the selection that have no Jest config, so nothing ran for them. */
    withoutConfig: string[];
    /** vendor/ extensions with a Jest config that did not run, because no `only` named them. */
    vendorNotTested: string[];
    fatalDiagnostics: string[];
    exitCode: number;
}

/**
 * The Jest configs of an extension, at most one per directory: at the extension
 * root, beside each Administration folder, or beside an extension-owned tsconfig
 * or ESLint config (where the `.shopware/` bridge sits).
 *
 * @example
 * findJestConfigs('/shop', swagPayPal);
 * // → ['/shop/custom/plugins/SwagPayPal/src/Resources/app/administration/jest.config.mjs']
 */
export function findJestConfigs(projectRoot: string, project: ExtensionToolingProject): string[] {
    const directories = new Set([
        project.basePath,
        ...project.targets.flatMap((target) => [
            target.adminFolder,
            ...[target.tsconfig, target.eslintConfig].flatMap((config) => (config ? [path.dirname(config.path)] : [])),
        ]),
    ]);

    return [...directories].flatMap((directory) => {
        const configName = JEST_CONFIG_FILE_NAMES.find((fileName) =>
            fs.existsSync(path.join(projectRoot, directory, fileName)),
        );

        return configName ? [path.join(projectRoot, directory, configName)] : [];
    });
}

/**
 * Runs the Administration's Jest from the config's directory, so relative paths
 * in the config and Jest's own config lookup behave as if Jest ran there.
 */
export function createJestRunner(administrationRoot: string, stdio: StdioOptions = 'inherit'): JestRunner {
    const jestBin = path.join(administrationRoot, 'node_modules', 'jest', 'bin', 'jest.js');

    return async (configPath, jestArgs) => {
        const jest = spawn(
            process.execPath,
            [
                jestBin,
                '--config',
                configPath,
                ...jestArgs,
            ],
            {
                cwd: path.dirname(configPath),
                stdio,
            },
        );
        // `once` also rejects when the process fails to start.
        const [exitCode] = (await once(jest, 'close')) as [number | null];

        return exitCode ?? 1;
    };
}

/** Runs the Administration's `unit-setup` generator, which scans its `src/` from the Administration root. */
function createComponentImportsGenerator(administrationRoot: string): () => number {
    return () =>
        spawnSync(
            path.join(administrationRoot, 'node_modules', '.bin', 'ts-node'),
            ['--transpileOnly', 'scripts/componentImportResolver/generate.ts'],
            { cwd: administrationRoot, stdio: 'inherit' },
        ).status ?? 1;
}

/**
 * Explains a run without a single Jest config, so it fails instead of passing green.
 *
 * @example
 * nothingTestedDiagnostic(['custom-admin']);
 * // → 'Nothing was tested: … Name a vendor extension via --only to test it: custom-admin.'
 */
function nothingTestedDiagnostic(vendorNotTested: string[]): string {
    return [
        `Nothing was tested: no extension in this run has a Jest config (${JEST_CONFIG_FILE_NAMES.join(', ')}).`,
        ...(vendorNotTested.length > 0
            ? [`Name a vendor extension via --only to test it: ${vendorNotTested.join(', ')}.`]
            : []),
    ].join(' ');
}

export async function testExtensions(options: TestExtensionsOptions): Promise<TestExtensionsResult> {
    const projectRoot = path.resolve(options.projectRoot);
    const administrationRoot = path.resolve(options.administrationRoot);
    const runJest = options.runJest ?? createJestRunner(administrationRoot);
    const generateComponentImports = options.generateComponentImports ?? createComponentImportsGenerator(administrationRoot);
    const selected = normalizeSelection(options.only);
    const setupResult = setupExtensionTooling({ projectRoot, administrationRoot });
    const selection = filterSelectedProjects(setupResult.manifest.projects, selected);
    const fatalDiagnostics = selection.fatalDiagnostic ? [selection.fatalDiagnostic] : [];
    const candidates = selection.projects.map((project) => ({
        project,
        configs: findJestConfigs(projectRoot, project),
    }));
    // A selection names its extensions on purpose; without one, vendor/ extensions stay out.
    const vendorNotTested =
        selected.length > 0
            ? []
            : candidates
                  .filter(({ project, configs }) => project.vendor && configs.length > 0)
                  .map(({ project }) => project.name);
    const configsByExtension = candidates.filter(({ project }) => selected.length > 0 || !project.vendor);
    const withoutConfig = configsByExtension
        .filter(({ configs }) => configs.length === 0)
        .map(({ project }) => project.name);
    const configCount = configsByExtension.reduce((count, { configs }) => count + configs.length, 0);

    // An extension named on purpose that has nothing to run is a mistake, not a pass.
    if (selected.length > 0 && withoutConfig.length > 0) {
        fatalDiagnostics.push(
            `No Jest config (${JEST_CONFIG_FILE_NAMES.join(', ')}) found for: ${withoutConfig.join(', ')}. ` +
                'Add one at the extension root or beside its Administration folder.',
        );
    }

    if (fatalDiagnostics.length === 0 && configCount === 0) {
        fatalDiagnostics.push(nothingTestedDiagnostic(vendorNotTested));
    }

    const runs: JestRun[] = [];

    // A Composer update replaces the Administration package and drops the generated map with it.
    if (
        fatalDiagnostics.length === 0 &&
        !fs.existsSync(path.join(administrationRoot, COMPONENT_IMPORTS_FILE)) &&
        generateComponentImports() !== 0
    ) {
        fatalDiagnostics.push(`Generating ${COMPONENT_IMPORTS_FILE} failed, see the output above.`);
    }

    if (fatalDiagnostics.length === 0) {
        for (const { project, configs } of configsByExtension) {
            for (const config of configs) {
                runs.push({
                    extension: project.name,
                    config: relativePosix(projectRoot, config),
                    exitCode: await runJest(config, options.jestArgs ?? []),
                });
            }
        }
    }

    return {
        runs,
        withoutConfig,
        vendorNotTested,
        fatalDiagnostics,
        exitCode: fatalDiagnostics.length > 0 || runs.some((run) => run.exitCode !== 0) ? 1 : 0,
    };
}

/** One line per run, then the extensions that did not run and any fatal diagnostic. */
export function renderTestReport(result: TestExtensionsResult): string {
    return [
        '',
        ...result.runs.map((run) => `${run.exitCode === 0 ? '✓' : '✗'} ${run.extension} · ${run.config}`),
        ...(result.withoutConfig.length > 0 && result.fatalDiagnostics.length === 0
            ? [`No Jest config, not tested: ${result.withoutConfig.join(', ')}`]
            : []),
        ...(result.vendorNotTested.length > 0 && result.fatalDiagnostics.length === 0
            ? [`Vendor extensions not tested (name them via --only): ${result.vendorNotTested.join(', ')}`]
            : []),
        ...result.fatalDiagnostics.map((diagnostic) => `✗ ${diagnostic}`),
    ].join('\n');
}

const TEST_COMMAND: CommandSpec = {
    command: 'admin:test-extensions',
    description:
        "Run the specs of Administration extensions with the Administration's own Jest and test harness. " +
        'Options for Jest go after a second "--", e.g. -- --only=MyPlugin -- --watch.',
    flags: [
        {
            name: '--only',
            value: 'required',
            valueName: '<name[,name]>',
            description: 'Test only the named extensions, vendor ones included (skips the interactive picker).',
        },
        {
            name: '--all',
            description: 'Test every non-vendor extension that has a Jest config (skips the interactive picker).',
        },
        {
            name: '--project-root',
            value: 'required',
            valueName: '<path>',
            description: 'Shop root (defaults to the PROJECT_ROOT env).',
        },
        {
            name: '--administration-root',
            value: 'required',
            valueName: '<path>',
            description: 'Administration app root (defaults to the installed one running this script).',
        },
    ],
};

/** Runs the test command; returns the process exit code (0 passed, 1 failed, 2 usage error). */
export async function runTestCli(argv: string[]): Promise<number> {
    const separator = argv.indexOf('--');
    const toolingArgs = separator === -1 ? argv : argv.slice(0, separator);
    const jestArgs = separator === -1 ? [] : argv.slice(separator + 1);
    let parsed;

    try {
        parsed = parseCli(toolingArgs, TEST_COMMAND);
    } catch (error) {
        if (error instanceof CliUsageError) {
            console.error(`${error.message}\n\n${renderHelp(TEST_COMMAND)}`);

            return 2;
        }

        throw error;
    }

    if (parsed.help) {
        console.log(renderHelp(TEST_COMMAND));

        return 0;
    }

    const administrationRoot = path.resolve(parsed.values['--administration-root'] ?? path.resolve(__dirname, '../..'));
    const projectRoot = parsed.values['--project-root'] ?? process.env.PROJECT_ROOT;

    if (!projectRoot) {
        console.error(`PROJECT_ROOT or --project-root is required.\n\n${renderHelp(TEST_COMMAND)}`);

        return 2;
    }

    let only: string | string[] | undefined = parsed.values['--only'];

    if (only === undefined && !parsed.flags.has('--all') && process.stdin.isTTY && process.stdout.isTTY) {
        const resolvedRoot = path.resolve(projectRoot);
        const testable = discoverProjects(resolvedRoot, administrationRoot, pluginsConfigPath(resolvedRoot)).filter(
            (project) => findJestConfigs(resolvedRoot, project).length > 0,
        );

        if (testable.length === 0) {
            console.log(`No Administration extension has a Jest config (${JEST_CONFIG_FILE_NAMES.join(', ')}).`);

            return 0;
        }

        const choice = await promptSelection(testable, 'Select extensions to test (Jest):');

        if (choice === 'cancel') {
            console.log('Nothing selected.');

            return 0;
        }

        // Name every listed extension, so "all" includes the vendor ones the picker showed.
        only = choice === 'all' ? testable.map((project) => project.name) : choice.names;
    }

    const result = await testExtensions({ projectRoot, administrationRoot, only, jestArgs });

    console.log(renderTestReport(result));

    return result.exitCode;
}

if (require.main === module) {
    runTestCli(process.argv.slice(2)).then(
        (exitCode) => {
            process.exitCode = exitCode;
        },
        (error: unknown) => {
            console.error(error instanceof Error ? error.message : error);
            process.exitCode = 1;
        },
    );
}
