/**
 * @sw-package framework
 *
 * Check runner for Administration extensions: runs setup first (fresh
 * state), then per extension vue-tsc and ESLint with the Administration's
 * pinned toolchain. Tool output is passed through natively under a
 * per-extension header — no parsing layer rewrites or drops diagnostics;
 * parsing only informs exit codes and summary counts.
 *
 * Severity policy: findings in writable extensions (custom/plugins and
 * in-repo platform bundles) fail the check; findings in vendor/ extensions
 * are reported non-fatally unless --strict-vendor is set. Custom configs
 * that do not compose the Shopware preset are visibly skipped as
 * "unmanaged" — never silently green.
 */

import fs from 'fs';
import os from 'os';
import path from 'path';
import { discoverProjects, setupExtensionTooling } from './setup';
import { renderCheckReport } from './report';
import { promptSelection } from './select';
import { relativePosix } from './shared';
import type { ExtensionToolingProject, ModeResolution } from './shared';
import {
    PROCESS_TIMEOUT_MS,
    probeCacheKey,
    probeEslintMode,
    probeInputFiles,
    probeTsMode,
    readProbeCache,
    runCommand,
    toCacheableResolution,
    writeProbeCache,
} from './probe';
import type { ProbeCacheFile } from './probe';
import { CliUsageError, parseCli, renderHelp } from './cli';
import type { CommandSpec } from './cli';

export type ToolStatus = 'passed' | 'failed' | 'unmanaged' | 'no-files' | 'blocked' | 'tooling-error';

export interface ToolRunResult {
    status: ToolStatus;
    output: string;
    durationMs: number;
    findings: number;
}

export interface ExtensionCheckResult {
    project: ExtensionToolingProject;
    tsResolution: ModeResolution;
    eslintResolution: ModeResolution;
    typescript: ToolRunResult;
    eslint: ToolRunResult;
}

export interface CheckExtensionsOptions {
    projectRoot: string;
    administrationRoot: string;
    pluginsConfigPath?: string;
    only?: string | string[];
    strictVendor?: boolean;
    maxWorkers?: number;
}

export interface CheckExtensionsResult {
    results: ExtensionCheckResult[];
    fatalDiagnostics: string[];
    warnings: string[];
    exitCode: number;
}

/** Minimal bounded-parallelism pool: runs all jobs with at most `limit` in flight. */
export async function runPool<T>(jobs: Array<() => Promise<T>>, limit: number): Promise<T[]> {
    const results: T[] = new Array<T>(jobs.length);
    let nextIndex = 0;

    async function worker(): Promise<void> {
        while (nextIndex < jobs.length) {
            const jobIndex = nextIndex;

            nextIndex += 1;
            results[jobIndex] = await jobs[jobIndex]();
        }
    }

    await Promise.all(Array.from({ length: Math.max(1, Math.min(limit, jobs.length)) }, () => worker()));

    return results;
}

function findFirstSourceFile(projectRoot: string, sourcePaths: string[]): string | null {
    const lintableExtensions = [
        '.ts',
        '.tsx',
        '.vue',
        '.js',
        '.mjs',
        '.cjs',
    ];

    for (const sourcePath of sourcePaths) {
        const absoluteSource = path.resolve(projectRoot, sourcePath);
        const queue = [absoluteSource];

        while (queue.length > 0) {
            const currentDir = queue.shift() as string;

            if (!fs.existsSync(currentDir)) {
                continue;
            }

            for (const entry of fs
                .readdirSync(currentDir, { withFileTypes: true })
                .sort((a, b) => a.name.localeCompare(b.name))) {
                const entryPath = path.join(currentDir, entry.name);

                if (entry.isDirectory() && entry.name !== 'node_modules') {
                    queue.push(entryPath);
                } else if (entry.isFile() && lintableExtensions.includes(path.extname(entry.name))) {
                    return entryPath;
                }
            }
        }
    }

    return null;
}

function readEslintMajorVersion(administrationRoot: string): number {
    const eslintPackagePath = path.join(administrationRoot, 'node_modules', 'eslint', 'package.json');

    try {
        const eslintPackage = JSON.parse(fs.readFileSync(eslintPackagePath, 'utf8')) as { version: string };

        return Number(eslintPackage.version.split('.')[0]);
    } catch {
        return 9;
    }
}

/**
 * Counts the files vue-tsc would actually type-check (`checkJs` is off in the
 * preset, so plain `.js` sources are parsed but never checked). Spec files are
 * excluded to mirror the generated tsconfigs. Zero means a TypeScript "pass"
 * would be vacuous — reported as `no-files` instead of a bare green.
 */
export function countTypeCheckableFiles(projectRoot: string, sourcePaths: string[]): number {
    const typeCheckableExtensions = [
        '.ts',
        '.tsx',
        '.vue',
    ];
    let count = 0;

    for (const sourcePath of sourcePaths) {
        const queue = [path.resolve(projectRoot, sourcePath)];

        while (queue.length > 0) {
            const currentDir = queue.shift() as string;

            if (!fs.existsSync(currentDir)) {
                continue;
            }

            for (const entry of fs.readdirSync(currentDir, { withFileTypes: true })) {
                const entryPath = path.join(currentDir, entry.name);

                if (entry.isDirectory() && entry.name !== 'node_modules') {
                    queue.push(entryPath);
                } else if (
                    entry.isFile() &&
                    typeCheckableExtensions.includes(path.extname(entry.name)) &&
                    !/\.spec\.(ts|tsx|js)$/.test(entry.name)
                ) {
                    count += 1;
                }
            }
        }
    }

    return count;
}

export function countTypeScriptFindings(output: string): number {
    return output.split(/\r?\n/).filter((line) => /error TS\d+:/.test(line)).length;
}

export function countEslintFindings(output: string): number {
    const summaryMatch = output.match(/✖ (\d+) problems? \((\d+) errors?, (\d+) warnings?\)/);

    if (summaryMatch) {
        return Number(summaryMatch[1]);
    }

    return 0;
}

/** Normalizes the selection (single value, comma list, or array) to trimmed, non-empty names. */
export function normalizeSelection(only: string | string[] | undefined): string[] {
    if (!only) {
        return [];
    }

    return (Array.isArray(only) ? only : only.split(',')).map((value) => value.trim()).filter((value) => value !== '');
}

export async function checkExtensions(options: CheckExtensionsOptions): Promise<CheckExtensionsResult> {
    const projectRoot = path.resolve(options.projectRoot);
    const administrationRoot = path.resolve(options.administrationRoot);
    const setupResult = setupExtensionTooling({
        projectRoot,
        administrationRoot,
        pluginsConfigPath: options.pluginsConfigPath,
    });
    const fatalDiagnostics: string[] = [];
    const warnings: string[] = [...setupResult.warnings];

    if (!setupResult.manifest.entitySchemaAvailable) {
        fatalDiagnostics.push(
            'Entity schema types are missing — entity names cannot be type-checked against this installation, ' +
                'so TypeScript checks were not run. Fix: composer admin:generate-entity-schema-types',
        );
    }

    if (setupResult.manifest.rootConfigs.tsconfig === 'conflict') {
        fatalDiagnostics.push(
            'The root tsconfig.json is user-owned and does not come from this tool — the IDE view and this check ' +
                'may diverge. Fix: integrate the printed references or remove the file and re-run ' +
                'composer admin:setup-extension-tooling',
        );
    }

    if (setupResult.manifest.rootConfigs.eslintConfig === 'conflict') {
        fatalDiagnostics.push(
            'The root eslint.config.mjs is user-owned and does not come from this tool. Fix: compose the shared ' +
                'factory as printed, or remove the file and re-run composer admin:setup-extension-tooling',
        );
    }

    let projects = setupResult.manifest.projects;
    const selected = normalizeSelection(options.only);

    if (selected.length > 0) {
        projects = projects.filter(
            (project) => selected.includes(project.name) || project.technicalNames.some((name) => selected.includes(name)),
        );

        if (projects.length === 0) {
            const available = setupResult.manifest.projects.map((project) => project.name).join(', ');

            fatalDiagnostics.push(
                `No extension matches --only=${selected.join(',')}. Discovered: ${available || '(none)'}.`,
            );
        }
    }

    const eslintBaseArguments =
        readEslintMajorVersion(administrationRoot) < 10
            ? [
                  '--flag',
                  'v10_config_lookup_from_file',
              ]
            : [];
    const maxWorkers = options.maxWorkers ?? Math.max(1, Math.min(4, os.cpus().length - 1));
    const vueTscPath = path.join(administrationRoot, 'node_modules', 'vue-tsc', 'bin', 'vue-tsc.js');
    const eslintPath = path.join(administrationRoot, 'node_modules', 'eslint', 'bin', 'eslint.js');

    if (projects.length > 0 && !fs.existsSync(vueTscPath)) {
        fatalDiagnostics.push(
            `vue-tsc is not installed in the Administration (${relativePosix(projectRoot, vueTscPath)}). ` +
                'Fix: composer init:js',
        );
    }

    const modeJobs = projects.map((project) => async () => {
        const [
            tsResolution,
            eslintResolution,
        ] = await Promise.all([
            probeTsMode(project, projectRoot, administrationRoot),
            probeEslintMode(
                project,
                projectRoot,
                administrationRoot,
                eslintBaseArguments,
                findFirstSourceFile(projectRoot, project.sourcePaths),
            ),
        ]);

        return { project, tsResolution, eslintResolution };
    });
    const resolvedModes = await runPool(modeJobs, maxWorkers);

    // Persist the verified verdicts so subsequent setup runs render the same
    // state. Merge with existing entries (a --only run must not drop other
    // extensions' verdicts); prune extensions that no longer exist.
    const knownNames = new Set(setupResult.manifest.projects.map((project) => project.name));
    const probeCache: ProbeCacheFile = {
        version: 1,
        entries: Object.fromEntries(
            Object.entries(readProbeCache(projectRoot)?.entries ?? {}).filter(([name]) => knownNames.has(name)),
        ),
    };

    for (const { project, tsResolution, eslintResolution } of resolvedModes) {
        if (!project.tsconfig && !project.eslintConfig) {
            continue;
        }

        const inputs = probeInputFiles(project, projectRoot, administrationRoot);

        probeCache.entries[project.name] = {
            ...(project.tsconfig
                ? { ts: { key: probeCacheKey(inputs.ts), resolution: toCacheableResolution(tsResolution) } }
                : {}),
            ...(project.eslintConfig
                ? { eslint: { key: probeCacheKey(inputs.eslint), resolution: toCacheableResolution(eslintResolution) } }
                : {}),
        };
    }

    writeProbeCache(projectRoot, probeCache);

    const checkJobs = resolvedModes.map(({ project, tsResolution, eslintResolution }) => async () => {
        let typescript: ToolRunResult;
        let eslint: ToolRunResult;

        if (!setupResult.manifest.entitySchemaAvailable) {
            // Running vue-tsc against the empty-schema stub would bury the one
            // real cause under hundreds of cascade findings, most of them in
            // the Administration's own files. Refuse instead; the fatal
            // diagnostic names the fix. ESLint still runs.
            typescript = { status: 'blocked', output: '', durationMs: 0, findings: 0 };
        } else if (tsResolution.mode === 'unmanaged') {
            typescript = {
                status: 'unmanaged',
                output: tsResolution.probeOutput ?? '',
                durationMs: 0,
                findings: 0,
            };
        } else if (tsResolution.mode === 'managed' && countTypeCheckableFiles(projectRoot, project.sourcePaths) === 0) {
            // A custom tsconfig may pull in files from elsewhere, so the
            // shortcut only applies to managed (generated) configs.
            typescript = { status: 'no-files', output: '', durationMs: 0, findings: 0 };
        } else if (!fs.existsSync(vueTscPath)) {
            typescript = { status: 'tooling-error', output: 'vue-tsc is not installed.', durationMs: 0, findings: 0 };
        } else {
            const tsconfigPath = path.resolve(
                projectRoot,
                tsResolution.mode === 'custom' && project.tsconfig ? project.tsconfig : project.checkTsconfig,
            );
            const run = await runCommand(
                process.execPath,
                [
                    vueTscPath,
                    '--noEmit',
                    '--project',
                    tsconfigPath,
                ],
                projectRoot,
            );
            const findings = countTypeScriptFindings(run.output);

            if (run.timedOut) {
                typescript = {
                    status: 'tooling-error',
                    output: `vue-tsc timed out after ${PROCESS_TIMEOUT_MS / 1000}s.\n${run.output}`,
                    durationMs: run.durationMs,
                    findings,
                };
            } else if (run.status !== 0 && findings === 0 && run.output.trim() === '') {
                typescript = {
                    status: 'tooling-error',
                    output: `vue-tsc exited with status ${run.status} and no output.`,
                    durationMs: run.durationMs,
                    findings: 0,
                };
            } else {
                typescript = {
                    status: run.status === 0 ? 'passed' : 'failed',
                    output: run.output,
                    durationMs: run.durationMs,
                    findings,
                };
            }
        }

        if (eslintResolution.mode === 'unmanaged') {
            eslint = {
                status: 'unmanaged',
                output: eslintResolution.probeOutput ?? '',
                durationMs: 0,
                findings: 0,
            };
        } else {
            const sampleFile = findFirstSourceFile(projectRoot, project.sourcePaths);

            if (!sampleFile) {
                eslint = { status: 'no-files', output: '', durationMs: 0, findings: 0 };
            } else {
                const run = await runCommand(
                    process.execPath,
                    [
                        eslintPath,
                        ...eslintBaseArguments,
                        '--no-error-on-unmatched-pattern',
                        ...project.sourcePaths,
                    ],
                    projectRoot,
                );
                const findings = countEslintFindings(run.output);

                if (run.timedOut) {
                    eslint = {
                        status: 'tooling-error',
                        output: `ESLint timed out after ${PROCESS_TIMEOUT_MS / 1000}s.\n${run.output}`,
                        durationMs: run.durationMs,
                        findings,
                    };
                } else if (run.status !== 0 && findings === 0) {
                    eslint = {
                        status: 'tooling-error',
                        output: run.output || `ESLint exited with status ${run.status} and no output.`,
                        durationMs: run.durationMs,
                        findings: 0,
                    };
                } else {
                    eslint = {
                        status: run.status === 0 ? 'passed' : 'failed',
                        output: run.output,
                        durationMs: run.durationMs,
                        findings,
                    };
                }
            }
        }

        return {
            project,
            tsResolution,
            eslintResolution,
            typescript,
            eslint,
        };
    });
    const results = await runPool(checkJobs, maxWorkers);

    let exitCode = fatalDiagnostics.length > 0 ? 1 : 0;

    for (const result of results) {
        const hasFailure =
            result.typescript.status === 'failed' ||
            result.eslint.status === 'failed' ||
            result.typescript.status === 'tooling-error' ||
            result.eslint.status === 'tooling-error';

        if (hasFailure && (!result.project.vendor || options.strictVendor)) {
            exitCode = 1;
        }
    }

    return {
        results,
        fatalDiagnostics,
        warnings,
        exitCode,
    };
}

const CHECK_COMMAND: CommandSpec = {
    command: 'admin:check-extensions',
    description: "Type-check and lint Administration extensions with the Administration's own pinned toolchain.",
    flags: [
        {
            name: '--only',
            value: 'required',
            valueName: '<name[,name]>',
            description: 'Check only the named extensions (skips the interactive picker).',
        },
        { name: '--all', description: 'Check every discovered extension (skips the interactive picker).' },
        { name: '--strict-vendor', description: 'Fail on findings in vendor-installed (read-only) extensions.' },
        { name: '--verbose', description: 'Also print tool output for passing and skipped extensions.' },
        {
            name: '--max-workers',
            value: 'required',
            valueName: '<n>',
            description: 'Bound the number of parallel tool runs.',
        },
        { name: '--project-root', value: 'required', valueName: '<path>', description: '', internal: true },
        { name: '--administration-root', value: 'required', valueName: '<path>', description: '', internal: true },
        { name: '--plugins-config', value: 'required', valueName: '<path>', description: '', internal: true },
    ],
};

/** Runs the check command; returns the process exit code (0 ok, 1 findings/error, 2 usage error). */
export async function runCheckCli(argv: string[]): Promise<number> {
    let parsed;

    try {
        parsed = parseCli(argv, CHECK_COMMAND);
    } catch (error) {
        if (error instanceof CliUsageError) {
            console.error(`${error.message}\n\n${renderHelp(CHECK_COMMAND)}`);

            return 2;
        }

        throw error;
    }

    if (parsed.help) {
        console.log(renderHelp(CHECK_COMMAND));

        return 0;
    }

    const administrationRoot = path.resolve(parsed.values['--administration-root'] ?? path.resolve(__dirname, '../..'));
    const projectRoot = parsed.values['--project-root'] ?? process.env.PROJECT_ROOT;

    if (!projectRoot) {
        console.error(`PROJECT_ROOT or --project-root is required.\n\n${renderHelp(CHECK_COMMAND)}`);

        return 2;
    }

    const maxWorkersValue = parsed.values['--max-workers'];
    const maxWorkers = maxWorkersValue === undefined ? undefined : Number(maxWorkersValue);

    if (maxWorkers !== undefined && (!Number.isInteger(maxWorkers) || maxWorkers < 1)) {
        console.error(`--max-workers must be a positive integer, got "${maxWorkersValue}".\n\n${renderHelp(CHECK_COMMAND)}`);

        return 2;
    }

    const pluginsConfigPath = parsed.values['--plugins-config'];
    const only = parsed.values['--only'];
    let selection: string[] | undefined;

    if (only !== undefined) {
        selection = normalizeSelection(only);
    } else if (parsed.flags.has('--all')) {
        selection = undefined;
    } else if (process.stdin.isTTY && process.stdout.isTTY) {
        const pluginsPath = path.resolve(projectRoot, pluginsConfigPath ?? path.join('var', 'plugins.json'));
        const projects = discoverProjects(path.resolve(projectRoot), administrationRoot, pluginsPath);

        if (projects.length === 0) {
            console.log('No Administration extensions discovered.');

            return 0;
        }

        const choice = await promptSelection(projects);

        if (choice === 'cancel') {
            console.log('Nothing selected.');

            return 0;
        }

        selection = choice === 'all' ? undefined : choice.names;
    } else {
        console.log(
            'Not an interactive terminal and no selection given — checking all extensions. ' +
                'Pass --only=<name[,name]> or --all to be explicit.',
        );
    }

    const check = await checkExtensions({
        projectRoot,
        administrationRoot,
        pluginsConfigPath,
        only: selection,
        strictVendor: parsed.flags.has('--strict-vendor'),
        maxWorkers,
    });

    console.log(renderCheckReport(check, { verbose: parsed.flags.has('--verbose') }));

    return check.exitCode;
}

if (require.main === module) {
    runCheckCli(process.argv.slice(2)).then(
        (exitCode) => {
            process.exitCode = exitCode;
        },
        (error: unknown) => {
            console.error(error instanceof Error ? error.message : error);
            process.exitCode = 1;
        },
    );
}
