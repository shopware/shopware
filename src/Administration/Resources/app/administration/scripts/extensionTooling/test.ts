/**
 * @sw-package framework
 *
 * Spec runner for Administration extensions: runs each selected extension's
 * own Jest config. A config on the Administration's test harness runs on the
 * Administration's Jest, so the extension needs no Jest installation of its own;
 * any other config runs on the Jest the extension installs. Setup runs first,
 * so the `.shopware/jest.mjs` bridge those configs import is current.
 *
 * A run in which no spec ran fails, so CI cannot pass without running a spec.
 */

import { spawn, spawnSync } from 'child_process';
import { once } from 'events';
import fs from 'fs';
import os from 'os';
import path from 'path';
import colors from 'picocolors';
import type { StdioOptions } from 'child_process';
import { filterSelectedProjects, normalizeSelection, selectedSetupWarnings } from './check';
import { CliUsageError, parseCli, renderHelp } from './cli';
import type { CommandSpec } from './cli';
import { promptSelection } from './select';
import { discoverProjects, setupExtensionTooling } from './setup';
import { isWithin, pluginsConfigPath, relativePosix } from './shared';
import type { ExtensionToolingProject } from './shared';

/** The config file names Jest itself looks for, in its own order of preference. */
export const JEST_CONFIG_FILE_NAMES = [
    'jest.config.js',
    'jest.config.ts',
    'jest.config.mjs',
    'jest.config.cjs',
];

const COMPONENT_IMPORTS_FILE = 'test/_helper_/componentWrapper/component-imports.js';

const RESULTS_PROCESSOR = path.join(__dirname, 'jest-results-processor.js');

/** Matches a Jest config that builds on the Administration's test harness: the bridge, the factory, or a call of it. */
const HARNESS_IMPORT = /shopwareAdminJest|\.shopware\/jest\.mjs|extension-tooling\/jest\.mjs/;

export interface JestOutcome {
    exitCode: number;
    /** Tests that passed or failed; undefined when Jest wrote no results, e.g. with the caller's own --testResultsProcessor. */
    testCount?: number;
}

/**
 * Runs Jest with one config file and resolves with its exit code and test count.
 * `ownJestBin` is the extension's own Jest; without it, the Administration's runs.
 */
export type JestRunner = (configPath: string, jestArgs: string[], ownJestBin?: string) => Promise<JestOutcome>;

export interface TestExtensionsOptions {
    projectRoot: string;
    administrationRoot: string;
    /**
     * Extension or bundle names to test. When empty or absent, every extension
     * with a Jest config runs.
     */
    only?: string | string[];
    /** False leaves vendor/ extensions out of a run without `only`. Defaults to true. */
    includeVendors?: boolean;
    /**
     * Forwarded to every Jest run, e.g. a spec path pattern. `--watch` needs
     * exactly one config; over several configs, `--passWithNoTests` is added.
     */
    jestArgs?: string[];
    runJest?: JestRunner;
    /** Called before each Jest run, so the caller can label the Jest output that follows. */
    onJestRun?: (extension: string, config: string, ownJest: boolean) => void;
    /** Generates the component import map `wrapTestComponent` reads; returns the exit code. */
    generateComponentImports?: () => number;
}

export interface JestRun {
    extension: string;
    /** Relative to the project root (posix). */
    config: string;
    exitCode: number;
    testCount?: number;
    /** Set when the config ran on the extension's own Jest instead of the Administration's. */
    ownJest?: boolean;
}

/**
 * A config that is not on the Administration's harness and whose own Jest is
 * declared in a package.json but not installed, so it cannot run yet.
 */
export interface NotRunnableConfig {
    extension: string;
    /** Relative to the project root (posix). */
    config: string;
    /** The directory of the package.json that declares Jest, relative to the project root (posix). */
    installDirectory: string;
    installCommand: 'npm ci' | 'npm install';
    /** Whether the extension was named via --only or the picker, so the config not running fails the run. */
    failsRun: boolean;
}

export interface TestExtensionsResult {
    runs: JestRun[];
    notRunnable: NotRunnableConfig[];
    /** Extensions of the selection that have no Jest config, so nothing ran for them. */
    withoutConfig: string[];
    /** vendor/ extensions with a Jest config that did not run, because of `includeVendors: false`. */
    vendorNotTested: string[];
    /** Setup warnings of the selected extensions and the project, e.g. a bridge that could not be written. */
    setupWarnings: string[];
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
 * Picks the Jest for a config. A config on the Administration's harness runs on
 * the Administration's Jest, which the harness is built for. Any other config
 * runs on the Jest the extension installs between the config's directory and the
 * extension root. A package.json there that declares Jest which is not installed
 * means the config cannot run yet; without such a declaration, the
 * Administration's Jest runs it.
 *
 * @example
 * chooseJest('/shop', swagCmsExtensions, `${cmsAdmin}/jest.config.js`);
 * // → { missingJest: { directory: cmsAdmin, command: 'npm ci' } } before `npm ci` there
 * // → { ownJestBin: `${cmsAdmin}/node_modules/jest/bin/jest.js` } after it
 */
function chooseJest(
    projectRoot: string,
    project: ExtensionToolingProject,
    configPath: string,
): { ownJestBin?: string } | { missingJest: { directory: string; command: 'npm ci' | 'npm install' } } {
    if (HARNESS_IMPORT.test(fs.readFileSync(configPath, 'utf8'))) {
        return {};
    }

    const directories = directoriesUpTo(path.dirname(configPath), path.join(projectRoot, project.basePath));
    const ownJestBin = directories
        .map((directory) => path.join(directory, 'node_modules', 'jest', 'bin', 'jest.js'))
        .find((jestBin) => fs.existsSync(jestBin));

    if (ownJestBin) {
        return { ownJestBin };
    }

    const declaringDirectory = directories.find((directory) => declaresJest(path.join(directory, 'package.json')));

    if (!declaringDirectory) {
        return {};
    }

    return {
        missingJest: {
            directory: declaringDirectory,
            command: fs.existsSync(path.join(declaringDirectory, 'package-lock.json')) ? 'npm ci' : 'npm install',
        },
    };
}

/**
 * The directory and its ancestors up to and including `root`.
 *
 * @example
 * directoriesUpTo('/shop/custom/plugins/A/src', '/shop/custom/plugins/A');
 * // → ['/shop/custom/plugins/A/src', '/shop/custom/plugins/A']
 */
function directoriesUpTo(directory: string, root: string): string[] {
    const directories = [directory];
    let current = directory;

    while (current !== root && isWithin(current, root)) {
        current = path.dirname(current);
        directories.push(current);
    }

    return directories;
}

/** Whether the package.json at `packageJsonPath` lists Jest in its dependencies or devDependencies. */
function declaresJest(packageJsonPath: string): boolean {
    if (!fs.existsSync(packageJsonPath)) {
        return false;
    }

    try {
        const manifest = JSON.parse(fs.readFileSync(packageJsonPath, 'utf8')) as {
            dependencies?: Record<string, string>;
            devDependencies?: Record<string, string>;
        };

        return 'jest' in { ...manifest.dependencies, ...manifest.devDependencies };
    } catch {
        return false;
    }
}

/**
 * Runs Jest from the config's directory, so relative CLI paths (e.g. --outputFile)
 * and process.cwd() inside the config resolve there: the extension's own Jest when
 * the caller passes it, otherwise the Administration's.
 * A results processor writes the test counts to a temp file, unless the caller
 * passes their own --testResultsProcessor or starts watch mode, which never ends.
 */
export function createJestRunner(administrationRoot: string, stdio: StdioOptions = 'inherit'): JestRunner {
    const administrationJestBin = path.join(administrationRoot, 'node_modules', 'jest', 'bin', 'jest.js');

    return async (configPath, jestArgs, ownJestBin) => {
        const countsTests =
            !isWatchRun(jestArgs) &&
            !jestArgs.some((arg) => new RegExp(`^--(?:${optionPattern('testResultsProcessor')})(=|$)`).test(arg));
        const resultsDirectory = countsTests ? fs.mkdtempSync(path.join(os.tmpdir(), 'sw-test-extensions-')) : undefined;
        const resultsFile = resultsDirectory && path.join(resultsDirectory, 'results.json');

        try {
            const jest = spawn(
                process.execPath,
                [
                    ownJestBin ?? administrationJestBin,
                    '--config',
                    configPath,
                    ...jestArgs,
                    ...(resultsFile ? [`--testResultsProcessor=${RESULTS_PROCESSOR}`] : []),
                ],
                {
                    cwd: path.dirname(configPath),
                    env: resultsFile ? { ...process.env, SW_TEST_EXTENSIONS_RESULTS_FILE: resultsFile } : process.env,
                    stdio,
                },
            );
            // `once` also rejects when the process fails to start.
            const [exitCode] = (await once(jest, 'close')) as [number | null];

            return { exitCode: exitCode ?? 1, testCount: resultsFile ? readTestCount(resultsFile) : undefined };
        } finally {
            if (resultsDirectory) {
                fs.rmSync(resultsDirectory, { recursive: true, force: true });
            }
        }
    };
}

/** The passed and failed tests in the results processor's file, or undefined when Jest wrote none. */
function readTestCount(resultsFile: string): number | undefined {
    if (!fs.existsSync(resultsFile)) {
        return undefined;
    }

    const results = JSON.parse(fs.readFileSync(resultsFile, 'utf8')) as { numPassedTests: number; numFailedTests: number };

    return results.numPassedTests + results.numFailedTests;
}

/** Runs the Administration's `unit-setup` generator, which scans its `src/` from the Administration root. */
function createComponentImportsGenerator(administrationRoot: string): () => number {
    return () => {
        const result = spawnSync(
            path.join(administrationRoot, 'node_modules', '.bin', 'ts-node'),
            ['--transpileOnly', 'scripts/componentImportResolver/generate.ts'],
            { cwd: administrationRoot, stdio: 'inherit' },
        );

        if (result.error) {
            console.error(`Could not start ts-node: ${result.error.message}`);

            return 1;
        }

        return result.status ?? 1;
    };
}

/** A Jest option name in both spellings Jest accepts, e.g. `--watchAll` and `--watch-all`. */
function optionPattern(camelCaseName: string): string {
    const kebabCaseName = camelCaseName.replace(/[A-Z]/g, (letter) => `-${letter.toLowerCase()}`);

    return kebabCaseName === camelCaseName ? camelCaseName : `${camelCaseName}|${kebabCaseName}`;
}

/**
 * The value of a boolean Jest option, read like Jest's own parser: either
 * spelling, `--no-<option>`, `=<value>` or a separate `true` / `false`
 * argument, and the last occurrence wins. Undefined when the option is absent.
 *
 * @example
 * booleanJestOption(['--watch-all'], 'watchAll'); // → true
 * booleanJestOption(['--watch', 'false'], 'watch'); // → false
 * booleanJestOption(['--no-passWithNoTests'], 'passWithNoTests'); // → false
 * booleanJestOption(['--watchman'], 'watch'); // → undefined
 */
function booleanJestOption(jestArgs: string[], camelCaseName: string): boolean | undefined {
    const pattern = new RegExp(`^--(?<negation>no-)?(?:${optionPattern(camelCaseName)})(?:=(?<value>.*))?$`);
    let enabled: boolean | undefined;

    jestArgs.forEach((arg, index) => {
        const groups = pattern.exec(arg)?.groups;

        if (!groups) {
            return;
        }

        const nextArg = jestArgs[index + 1];
        const value = groups.value ?? (nextArg === 'true' || nextArg === 'false' ? nextArg : undefined);
        enabled = !groups.negation && value?.toLowerCase() !== 'false';
    });

    return enabled;
}

/**
 * Whether the Jest options start watch mode, which takes over the terminal and
 * never returns, so the configs after the first would never run.
 *
 * @example
 * isWatchRun(['--watch-all']); // → true
 * isWatchRun(['--watch', 'false']); // → false
 */
function isWatchRun(jestArgs: string[]): boolean {
    return booleanJestOption(jestArgs, 'watch') === true || booleanJestOption(jestArgs, 'watchAll') === true;
}

/**
 * Adds `--passWithNoTests` when Jest options go to several configs: a spec path
 * or name filter usually matches in one extension only, and the others must not
 * fail the run for having no matching spec.
 *
 * @example
 * withPassWithNoTests(['main.spec'], 2); // → ['main.spec', '--passWithNoTests']
 * withPassWithNoTests(['main.spec'], 1); // → ['main.spec']
 */
function withPassWithNoTests(jestArgs: string[], configCount: number): string[] {
    if (configCount < 2 || jestArgs.length === 0 || booleanJestOption(jestArgs, 'passWithNoTests') !== undefined) {
        return jestArgs;
    }

    return [
        ...jestArgs,
        '--passWithNoTests',
    ];
}

/**
 * Explains a run without a single Jest config that could run, so it fails instead of passing green.
 *
 * @example
 * nothingTestedDiagnostic(['custom-admin'], []);
 * // → 'Nothing was tested: … Drop --no-vendors to test the vendor extensions: custom-admin.'
 */
function nothingTestedDiagnostic(vendorNotTested: string[], notRunnable: NotRunnableConfig[]): string {
    return [
        notRunnable.length > 0
            ? 'Nothing was tested: no Jest config of this run can run yet, see the fixes above.'
            : `Nothing was tested: no extension in this run has a Jest config (${JEST_CONFIG_FILE_NAMES.join(', ')}).`,
        ...(vendorNotTested.length > 0
            ? [`Drop --no-vendors to test the vendor extensions: ${vendorNotTested.join(', ')}.`]
            : []),
    ].join(' ');
}

/**
 * Whether every Jest run reported zero tests. A Jest option such as a spec
 * filter gets `--passWithNoTests` over several configs, so each run can pass
 * empty; the run as a whole must not. Passing `--passWithNoTests` yourself
 * allows an empty run.
 */
function ranNoTest(runs: JestRun[], jestArgs: string[]): boolean {
    return (
        runs.length > 0 &&
        runs.every((run) => run.testCount === 0) &&
        booleanJestOption(jestArgs, 'passWithNoTests') !== true
    );
}

/**
 * Explains why watch mode cannot start. One extension with several Jest configs
 * cannot be narrowed down via --only, so the message names its configs instead.
 *
 * @example
 * watchUsageMessage('/shop', [{ project: zeroConfig, configs: [rootConfig, adminConfig] }], 2);
 * // → 'ZeroConfig has 2 Jest configs: custom/plugins/ZeroConfig/jest.config.js, …'
 */
function watchUsageMessage(
    projectRoot: string,
    configsByExtension: Array<{ project: ExtensionToolingProject; configs: string[] }>,
    configCount: number,
): string {
    const withConfigs = configsByExtension.filter(({ configs }) => configs.length > 0);

    if (withConfigs.length === 1 && configCount > 1) {
        const [{ project, configs }] = withConfigs;

        return (
            `${project.name} has ${configCount} Jest configs: ` +
            `${configs.map((config) => relativePosix(projectRoot, config)).join(', ')}. ` +
            "Jest's watch mode needs a single Jest config."
        );
    }

    return (
        `Jest's watch mode needs exactly one Jest config, but this run has ${configCount}. ` +
        'Name one extension via --only, e.g. -- --only=MyPlugin -- --watch.'
    );
}

/** The Jest configs of the extensions a run covers, grouped per extension. */
interface TestRunPlan {
    /** The configs that can run; a config that cannot run yet is in `notRunnable` instead. */
    configsByExtension: Array<{ project: ExtensionToolingProject; configs: string[] }>;
    /** The extension's own Jest bin of each config that runs on it. */
    ownJestBins: Map<string, string>;
    notRunnable: NotRunnableConfig[];
    withoutConfig: string[];
    vendorNotTested: string[];
    configCount: number;
}

/**
 * Decides which extensions of the selection run, which Jest configs they bring,
 * and which Jest runs each config. vendor/ extensions stay out only when the
 * caller excludes them.
 *
 * @example
 * planTestRun('/shop', [zeroConfig, customAdmin], false, false);
 * // → { configsByExtension: [{ project: zeroConfig, configs: [...] }], vendorNotTested: ['custom-admin'], … }
 */
function planTestRun(
    projectRoot: string,
    projects: ExtensionToolingProject[],
    includeVendors: boolean,
    namedOnPurpose: boolean,
): TestRunPlan {
    const configsByExtension: TestRunPlan['configsByExtension'] = [];
    const ownJestBins = new Map<string, string>();
    const notRunnable: NotRunnableConfig[] = [];
    const withoutConfig: string[] = [];
    const vendorNotTested: string[] = [];

    for (const project of projects) {
        const configs = findJestConfigs(projectRoot, project);

        if (!includeVendors && project.vendor) {
            if (configs.length > 0) {
                vendorNotTested.push(project.name);
            }

            continue;
        }

        if (configs.length === 0) {
            withoutConfig.push(project.name);
        }

        const runnable = configs.filter((config) => {
            const choice = chooseJest(projectRoot, project, config);

            if ('missingJest' in choice) {
                notRunnable.push({
                    extension: project.name,
                    config: relativePosix(projectRoot, config),
                    installDirectory: relativePosix(projectRoot, choice.missingJest.directory),
                    installCommand: choice.missingJest.command,
                    failsRun: namedOnPurpose,
                });

                return false;
            }

            if (choice.ownJestBin) {
                ownJestBins.set(config, choice.ownJestBin);
            }

            return true;
        });

        configsByExtension.push({ project, configs: runnable });
    }

    return {
        configsByExtension,
        ownJestBins,
        notRunnable,
        withoutConfig,
        vendorNotTested,
        configCount: configsByExtension.reduce((count, { configs }) => count + configs.length, 0),
    };
}

/**
 * The fatal diagnostic of a selection, if any: an unknown name, or an extension
 * named on purpose that has nothing to run, which is a mistake, not a pass.
 *
 * @example
 * selectionDiagnostic({ projects: [suite] }, plan, true);
 * // → 'No Jest config (jest.config.js, …) found for: Suite. Add one at the extension root …'
 */
function selectionDiagnostic(
    selection: { fatalDiagnostic?: string },
    plan: TestRunPlan,
    namedOnPurpose: boolean,
): string | undefined {
    if (selection.fatalDiagnostic) {
        return selection.fatalDiagnostic;
    }

    if (namedOnPurpose && plan.withoutConfig.length > 0) {
        return (
            `No Jest config (${JEST_CONFIG_FILE_NAMES.join(', ')}) found for: ${plan.withoutConfig.join(', ')}. ` +
            'Add one at the extension root or beside its Administration folder.'
        );
    }

    return undefined;
}

/**
 * Runs setup, then the Jest configs of the selected extensions one after another.
 *
 * @throws {CliUsageError} when the Jest options start watch mode and the run has
 *   no or several Jest configs; nothing runs then.
 */
export async function testExtensions(options: TestExtensionsOptions): Promise<TestExtensionsResult> {
    const projectRoot = path.resolve(options.projectRoot);
    const administrationRoot = path.resolve(options.administrationRoot);
    const runJest = options.runJest ?? createJestRunner(administrationRoot);
    const generateComponentImports = options.generateComponentImports ?? createComponentImportsGenerator(administrationRoot);
    const jestArgs = options.jestArgs ?? [];
    const selected = normalizeSelection(options.only);
    const setupResult = setupExtensionTooling({ projectRoot, administrationRoot });
    const selection = filterSelectedProjects(setupResult.manifest.projects, selected);
    const namedOnPurpose = selected.length > 0;
    const plan = planTestRun(
        projectRoot,
        selection.projects,
        namedOnPurpose || options.includeVendors !== false,
        namedOnPurpose,
    );
    const testWarnings = setupResult.warnings.filter((warning) => !warning.typeCheckOrLintOnly);

    const result = (runs: JestRun[], fatalDiagnostics: string[]): TestExtensionsResult => ({
        runs,
        notRunnable: plan.notRunnable,
        withoutConfig: plan.withoutConfig,
        vendorNotTested: plan.vendorNotTested,
        setupWarnings: selectedSetupWarnings(testWarnings, setupResult.manifest.projects, selected),
        fatalDiagnostics,
        exitCode:
            fatalDiagnostics.length > 0 ||
            runs.some((run) => run.exitCode !== 0) ||
            plan.notRunnable.some((config) => config.failsRun)
                ? 1
                : 0,
    });

    const diagnostic = selectionDiagnostic(selection, plan, namedOnPurpose);

    if (diagnostic) {
        return result([], [diagnostic]);
    }

    if (isWatchRun(jestArgs) && plan.configCount !== 1) {
        throw new CliUsageError(watchUsageMessage(projectRoot, plan.configsByExtension, plan.configCount));
    }

    if (plan.configCount === 0) {
        return result([], [nothingTestedDiagnostic(plan.vendorNotTested, plan.notRunnable)]);
    }

    // A Composer update replaces the Administration package and drops the generated map with it.
    if (!fs.existsSync(path.join(administrationRoot, COMPONENT_IMPORTS_FILE)) && generateComponentImports() !== 0) {
        return result([], [`Generating ${COMPONENT_IMPORTS_FILE} failed, see the output above.`]);
    }

    const runs: JestRun[] = [];
    const runJestArgs = withPassWithNoTests(jestArgs, plan.configCount);

    for (const { project, configs } of plan.configsByExtension) {
        for (const config of configs) {
            const relativeConfig = relativePosix(projectRoot, config);
            const ownJestBin = plan.ownJestBins.get(config);
            options.onJestRun?.(project.name, relativeConfig, ownJestBin !== undefined);
            const { exitCode, testCount } = await runJest(config, runJestArgs, ownJestBin);

            runs.push({
                extension: project.name,
                config: relativeConfig,
                exitCode,
                ...(testCount === undefined ? {} : { testCount }),
                ...(ownJestBin === undefined ? {} : { ownJest: true }),
            });
        }
    }

    return result(runs, ranNoTest(runs, jestArgs) ? ['Nothing was tested: no spec matched in any Jest config.'] : []);
}

/**
 * The setup warnings first, as they may explain a failed run, then one line per
 * run, the configs that could not run with their fix, and any fatal diagnostic.
 */
export function renderTestReport(result: TestExtensionsResult): string {
    return [
        '',
        ...result.setupWarnings.map((warning) => `⚠ ${warning}`),
        ...result.runs.map(renderRunLine),
        ...result.notRunnable.map(renderNotRunnable),
        ...result.fatalDiagnostics.map((diagnostic) => `✗ ${diagnostic}`),
    ].join('\n');
}

/** ✓ passed, ✗ failed, – passed without running a spec. */
function renderRunLine(run: JestRun): string {
    const mark = run.exitCode !== 0 ? '✗' : run.testCount === 0 ? '–' : '✓';
    const notes = [
        ...(run.ownJest ? ['own Jest'] : []),
        ...(run.exitCode === 0 && run.testCount === 0 ? ['no matching spec'] : []),
    ];

    return `${mark} ${run.extension} · ${run.config}${notes.length > 0 ? ` (${notes.join(', ')})` : ''}`;
}

/**
 * ⊘ for a config left out of the run, ✗ when the extension was named on purpose,
 * followed by why it did not run and how to make it run.
 */
function renderNotRunnable(config: NotRunnableConfig): string {
    return [
        `${config.failsRun ? '✗' : '⊘'} ${config.extension} · ${config.config} (not run)`,
        "  It does not use the Administration's test harness, so it runs on its own Jest, which is not installed.",
        `  Fix: run \`${config.installCommand}\` in ${config.installDirectory},`,
        '  or build the config on the harness with `shopwareAdminJestConfig` from `.shopware/jest.mjs`.',
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
            description: 'Test only the named extensions (skips the interactive picker).',
        },
        {
            name: '--all',
            description: 'Test every extension that has a Jest config (skips the interactive picker).',
        },
        {
            name: '--no-vendors',
            description: 'Leave out extensions installed under vendor/, in --all and in the picker.',
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
    try {
        return await runTestCommand(argv);
    } catch (error) {
        if (error instanceof CliUsageError) {
            console.error(`${error.message}\n\n${renderHelp(TEST_COMMAND)}`);

            return 2;
        }

        throw error;
    }
}

/** The test command; a wrong invocation throws a `CliUsageError`. */
async function runTestCommand(argv: string[]): Promise<number> {
    const separator = argv.indexOf('--');
    const toolingArgs = separator === -1 ? argv : argv.slice(0, separator);
    const jestArgs = separator === -1 ? [] : argv.slice(separator + 1);
    const parsed = parseCli(toolingArgs, TEST_COMMAND);

    if (parsed.help) {
        console.log(renderHelp(TEST_COMMAND));

        return 0;
    }

    const administrationRoot = path.resolve(parsed.values['--administration-root'] ?? path.resolve(__dirname, '../..'));
    const projectRoot = parsed.values['--project-root'] ?? process.env.PROJECT_ROOT;

    if (!projectRoot) {
        throw new CliUsageError('PROJECT_ROOT or --project-root is required.');
    }

    let only: string | string[] | undefined = parsed.values['--only'];
    const includeVendors = !parsed.flags.has('--no-vendors');

    if (only !== undefined && !includeVendors) {
        throw new CliUsageError('--no-vendors cannot be combined with --only, which names the extensions to test.');
    }

    if (only === undefined && !parsed.flags.has('--all') && process.stdin.isTTY && process.stdout.isTTY) {
        const picked = await pickExtensionsToTest(projectRoot, administrationRoot, includeVendors);

        if (picked === undefined) {
            return 0;
        }

        only = picked;
    }

    const result = await testExtensions({
        projectRoot,
        administrationRoot,
        only,
        includeVendors,
        jestArgs,
        onJestRun: (extension, config, ownJest) =>
            console.log(colors.bold(`\n▶ ${extension} · ${config}${ownJest ? ' (own Jest)' : ''}`)),
    });

    console.log(renderTestReport(result));

    return result.exitCode;
}

/**
 * Asks in the terminal which extensions to test, listing only those with a Jest
 * config, and no vendor ones under --no-vendors. Called when neither --only nor
 * --all is given and a TTY is attached.
 * Returns the chosen extension names, or undefined when there is nothing to run.
 *
 * @example
 * await pickExtensionsToTest('/shop', '/shop/vendor/shopware/administration/Resources/app/administration', true);
 * // → ['ZeroConfig', 'custom-admin'] when "all" is chosen
 */
async function pickExtensionsToTest(
    projectRoot: string,
    administrationRoot: string,
    includeVendors: boolean,
): Promise<string[] | undefined> {
    const resolvedRoot = path.resolve(projectRoot);
    const configsByProject = new Map<ExtensionToolingProject, string[]>();

    for (const project of discoverProjects(resolvedRoot, administrationRoot, pluginsConfigPath(resolvedRoot))) {
        const configs = findJestConfigs(resolvedRoot, project);

        if (configs.length > 0 && (includeVendors || !project.vendor)) {
            configsByProject.set(project, configs);
        }
    }

    const testable = [...configsByProject.keys()];

    if (testable.length === 0) {
        console.log(`No Administration extension has a Jest config (${JEST_CONFIG_FILE_NAMES.join(', ')}).`);

        return undefined;
    }

    const choice = await promptSelection(testable, 'Select extensions to test (Jest):', (project) => {
        const configs = configsByProject.get(project) ?? [];
        const location = project.vendor ? 'vendor' : project.basePath;
        const ownJestMissing = configs.some((config) => 'missingJest' in chooseJest(resolvedRoot, project, config));

        return [
            location,
            configs.length === 1 ? '1 Jest config' : `${configs.length} Jest configs`,
            ...(ownJestMissing ? ['own Jest not installed'] : []),
        ].join(' · ');
    });

    if (choice === 'cancel') {
        console.log('Nothing selected.');

        return undefined;
    }

    // Name every listed extension, so "all" is exactly what the picker showed.
    return choice === 'all' ? testable.map((project) => project.name) : choice.names;
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
