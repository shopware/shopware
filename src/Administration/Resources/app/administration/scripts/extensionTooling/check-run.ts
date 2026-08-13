/**
 * @sw-package framework
 *
 * Per-project execution pipeline for the check runner: probes each target's
 * TypeScript/ESLint mode, runs the vue-tsc and ESLint streams for one
 * extension, records a project baseline, and derives the process exit code.
 * The orchestrator in `./check` composes these steps; the ESLint/output helpers
 * below are used only by this pipeline.
 */

import fs from 'fs';
import path from 'path';
import { firstDrift, relativePosix, relativizeToolOutput, SHIM_DIR_NAME, toPosix } from './shared';
import type { AdministrationTarget, ExtensionToolingProject, OwnedConfig, ToolingCommands } from './shared';
import { DEFAULT_TOOLING_COMMANDS } from './shared';
import { PROCESS_TIMEOUT_MS, runCommand } from './probe-command';
import { probeEslintMode, probeTsMode } from './probe-live';
import { buildBaseline, canHoldBaseline, diffEslint, readBaseline, writeBaselineFile } from './baseline';
import type { BaselineEslintEntry, BaselineTsEntry, TypeScriptFinding } from './baseline';
import {
    countEslintFindings,
    countSpecFiles,
    countTypeCheckableFiles,
    deduplicateByMaximumMultiplicity,
    findFirstSourceFile,
    listSpecFiles,
    listTypeCheckableFiles,
    parseEslintFindings,
    parseTypeScriptFindings,
} from './check-parsing';
import { applyBaseline, formatCommand, groupTargetsByConfig, runPool } from './check-pipeline';
import { runTypeScriptPrograms } from './check-typescript-program';
import { collectSkippedTargets, isUnmanagedConfig } from './check-types';
import type { CheckExtensionsOptions, ExtensionCheckResult, Limiter, ToolRunResult } from './check-types';

/**
 * The tsconfig the check runner feeds to vue-tsc for a source root: the
 * extension's own config when it composes, otherwise the generated bridge
 * tsconfig that sits in `<source-root-parent>/.shopware/` (see setup-bridge.ts).
 */
export function checkTsconfigPath(target: AdministrationTarget): string {
    if (target.tsconfig?.composes) {
        return target.tsconfig.path;
    }

    return path.posix.join(target.adminFolder, SHIM_DIR_NAME, 'tsconfig.json');
}

/**
 * The dedicated spec tsconfig for a source root: the generated companion beside
 * the runtime bridge that adds jest types and includes only the spec files the
 * runtime program excludes. It always lives in the generated `.shopware/`
 * bridge (never the extension's own config, which type-checks no specs), so it
 * sits next to the config that composes it or beside the source root otherwise.
 */
export function specTsconfigPath(target: AdministrationTarget): string {
    const bridgeParent = target.tsconfig?.composes ? path.posix.dirname(target.tsconfig.path) : target.adminFolder;

    return path.posix.join(bridgeParent, SHIM_DIR_NAME, 'tsconfig.specs.json');
}

export function buildEslintArguments(
    eslintPath: string,
    baseArguments: string[],
    sourcePaths: string[],
    fix: boolean,
): string[] {
    return [
        eslintPath,
        ...baseArguments,
        '--no-error-on-unmatched-pattern',
        ...(fix ? ['--fix'] : []),
        ...sourcePaths,
    ];
}

/**
 * ESLint's native "potentially fixable with the `--fix` option" hint is a
 * dead-end inside this toolchain — append the actual command that applies it.
 */
export function appendFixHint(
    output: string,
    extensionName: string,
    commands: ToolingCommands = DEFAULT_TOOLING_COMMANDS,
): string {
    if (!output.includes('potentially fixable with the `--fix` option')) {
        return output;
    }

    return `${output}\n  → auto-fixable: ${commands.check} -- --only=${extensionName} --fix`;
}

interface ProbedTarget {
    projectName: string;
    target: AdministrationTarget;
    tsResolution: OwnedConfig | null;
    eslintResolution: OwnedConfig | null;
}

interface ResolvedProjectModes {
    project: ExtensionToolingProject;
    tsResolution: OwnedConfig | null;
    eslintResolution: OwnedConfig | null;
}

/**
 * Probes every target's TypeScript and ESLint mode in parallel, then folds the
 * per-target verdicts back into each project and aggregates the project-level
 * resolution the report renders.
 */
export async function probeExtensionModes(context: {
    projects: ExtensionToolingProject[];
    projectRoot: string;
    administrationRoot: string;
    eslintBaseArguments: string[];
    maxWorkers: number;
    limit: Limiter;
}): Promise<{ resolvedTargets: ProbedTarget[]; resolvedModes: ResolvedProjectModes[] }> {
    const { projects, projectRoot, administrationRoot, eslintBaseArguments, maxWorkers, limit } = context;
    const modeJobs = projects.flatMap((project) =>
        project.targets.map((target) => async (): Promise<ProbedTarget> => {
            const sampleFile = findFirstSourceFile(projectRoot, [target.sourcePath]);
            const [
                tsResolution,
                eslintResolution,
            ] = await Promise.all([
                limit(() => probeTsMode(target, projectRoot, administrationRoot)),
                limit(() => probeEslintMode(target, projectRoot, administrationRoot, eslintBaseArguments, sampleFile)),
            ]);

            return { projectName: project.name, target, tsResolution, eslintResolution };
        }),
    );
    const resolvedTargets = await runPool(modeJobs, maxWorkers);
    const resolvedModes = projects.map((project) => {
        const targetResolutions = new Map(
            resolvedTargets
                .filter((entry) => entry.projectName === project.name)
                .map((entry) => [
                    entry.target.sourcePath,
                    entry,
                ]),
        );
        const resolvedProject: ExtensionToolingProject = {
            ...project,
            targets: project.targets.map((target) => {
                const resolution = targetResolutions.get(target.sourcePath);

                return resolution
                    ? { ...target, tsconfig: resolution.tsResolution, eslintConfig: resolution.eslintResolution }
                    : target;
            }),
        };

        return {
            project: resolvedProject,
            tsResolution: firstDrift(resolvedProject, 'tsconfig'),
            eslintResolution: firstDrift(resolvedProject, 'eslintConfig'),
        };
    });

    return { resolvedTargets, resolvedModes };
}

/**
 * Runs one vue-tsc stream (runtime or spec) for an extension. Gating comes
 * first: a missing schema blocks it, no matching files is a no-files pass (not
 * a green), a missing binary is a tooling error. When only unmanaged targets
 * remain, a would-be pass is reported as unmanaged instead.
 */
async function runTypeScriptStream(context: {
    entitySchemaAvailable: boolean;
    streamTargets: AdministrationTarget[];
    unmanagedTargets: AdministrationTarget[];
    unmanagedOutput: string;
    vueTscPath: string;
    projectRoot: string;
    basePath: string;
    configOf: (target: AdministrationTarget) => string;
    baselineEntries: BaselineTsEntry[];
    expectedFiles: (target: AdministrationTarget) => string[];
    limit: Limiter;
}): Promise<{ result: ToolRunResult; commands?: string[] }> {
    const {
        entitySchemaAvailable,
        streamTargets,
        unmanagedTargets,
        unmanagedOutput,
        vueTscPath,
        projectRoot,
        basePath,
        configOf,
        baselineEntries,
        expectedFiles,
        limit,
    } = context;

    if (!entitySchemaAvailable) {
        // Running vue-tsc against the empty-schema stub would bury the one real
        // cause under hundreds of cascade findings, most of them in the
        // Administration's own files. Refuse instead; the fatal diagnostic
        // names the fix. ESLint still runs.
        return { result: { status: 'blocked', output: '', durationMs: 0, findings: 0 } };
    }

    if (streamTargets.length === 0 && unmanagedTargets.length > 0) {
        return { result: { status: 'unmanaged', output: unmanagedOutput, durationMs: 0, findings: 0 } };
    }

    if (streamTargets.length === 0) {
        return { result: { status: 'no-files', output: '', durationMs: 0, findings: 0 } };
    }

    if (!fs.existsSync(vueTscPath)) {
        return { result: { status: 'tooling-error', output: 'vue-tsc is not installed.', durationMs: 0, findings: 0 } };
    }

    const program = await runTypeScriptPrograms(
        vueTscPath,
        groupTargetsByConfig(projectRoot, streamTargets, configOf),
        projectRoot,
        basePath,
        baselineEntries,
        expectedFiles,
        limit,
    );
    const result = program.result;

    if (unmanagedTargets.length > 0 && result.status === 'passed') {
        result.status = 'unmanaged';
    }

    return { result, commands: program.commands };
}

/**
 * Runs ESLint for an extension across its config groups and interprets the
 * outcome through the baseline. Vendor extensions are not ours to rewrite, so
 * --fix only reaches them when they are named explicitly via --only.
 */
async function runEslintStream(context: {
    project: ExtensionToolingProject;
    projectRoot: string;
    eslintPath: string;
    eslintBaseArguments: string[];
    baselineEntries: BaselineEslintEntry[];
    basePath: string;
    options: CheckExtensionsOptions;
    limit: Limiter;
    toolingCommands: ToolingCommands;
}): Promise<{ result: ToolRunResult; commands?: string[]; warnings: string[] }> {
    const {
        project,
        projectRoot,
        eslintPath,
        eslintBaseArguments,
        baselineEntries,
        basePath,
        options,
        limit,
        toolingCommands,
    } = context;
    const unmanagedTargets = project.targets.filter((target) => isUnmanagedConfig(target.eslintConfig));
    const eslintTargets = project.targets.filter(
        (target) =>
            !isUnmanagedConfig(target.eslintConfig) && findFirstSourceFile(projectRoot, [target.sourcePath]) !== null,
    );

    if (eslintTargets.length === 0 && unmanagedTargets.length > 0) {
        return {
            result: {
                status: 'unmanaged',
                output: unmanagedTargets
                    .map((target) => target.eslintConfig?.detail ?? '')
                    .filter(Boolean)
                    .join('\n\n'),
                durationMs: 0,
                findings: 0,
            },
            warnings: [],
        };
    }

    if (eslintTargets.length === 0) {
        return { result: { status: 'no-files', output: '', durationMs: 0, findings: 0 }, warnings: [] };
    }

    const warnings: string[] = [];
    const explicitlyNamed =
        (options.explicitOnly ?? []).includes(project.name) ||
        project.technicalNames.some((name) => (options.explicitOnly ?? []).includes(name));
    const applyFix = options.fix === true && (!project.vendor || explicitlyNamed);

    if (options.fix === true && !applyFix) {
        warnings.push(
            `${project.name} is vendor-installed — not yours to rewrite; --fix skipped ` +
                `(name it via --only=${project.name} to fix anyway).`,
        );
    }

    const eslintGroups = groupTargetsByConfig(
        projectRoot,
        eslintTargets,
        (target) => target.eslintConfig?.path ?? path.join(projectRoot, 'eslint.config.mjs'),
    );
    const startedAt = Date.now();
    const runs = await Promise.all(
        eslintGroups.map(async (group) => {
            const sourcePaths = [...new Set(group.targets.map((target) => target.sourcePath))];
            const eslintArguments = buildEslintArguments(eslintPath, eslintBaseArguments, sourcePaths, applyFix);
            const command = formatCommand(projectRoot, eslintArguments);
            const run = await limit(() => runCommand(process.execPath, eslintArguments, projectRoot));
            const output = relativizeToolOutput(run.output, projectRoot);
            const nativeFindings = countEslintFindings(output);
            const parsedFindings = parseEslintFindings(output);

            return { run, output, nativeFindings, parsedFindings, command };
        }),
    );
    const outputs = runs.map((run) => run.output).filter((output) => output.trim() !== '');
    const parsedFindings = deduplicateByMaximumMultiplicity(
        runs.map((run) => run.parsedFindings),
        (finding) => `${finding.file}\u0000${finding.rule}\u0000${finding.message}\u0000${finding.severity}`,
    );
    const parseMismatch = runs.some((run) => run.nativeFindings !== run.parsedFindings.length);
    const split = diffEslint(
        parsedFindings,
        baselineEntries,
        basePath,
        parseMismatch ? parsedFindings.length + 1 : parsedFindings.length,
    );
    const toolingError = runs.find(({ run, nativeFindings }) => run.timedOut || (run.status !== 0 && nativeFindings === 0));
    const commands = runs.map((run) => run.command);

    if (toolingError) {
        return {
            result: {
                status: 'tooling-error',
                output: toolingError.run.timedOut
                    ? `ESLint timed out after ${PROCESS_TIMEOUT_MS / 1000}s.\n${outputs.join('\n\n')}`
                    : outputs.join('\n\n') || `ESLint exited with status ${toolingError.run.status} and no output.`,
                durationMs: Date.now() - startedAt,
                findings: parsedFindings.length,
                eslintFindings: parsedFindings,
                parseMismatch,
            },
            commands,
            warnings,
        };
    }

    const output = outputs.join('\n\n');
    const result: ToolRunResult = {
        ...applyBaseline(runs.some((run) => run.run.status !== 0) ? 1 : 0, parsedFindings.length, split, (finding) => ({
            file: finding.file,
            code: finding.rule,
        })),
        output: applyFix ? output : appendFixHint(output, project.name, toolingCommands),
        durationMs: Date.now() - startedAt,
        eslintFindings: parsedFindings,
        parseMismatch: split.parseMismatch,
    };

    if (unmanagedTargets.length > 0 && result.status === 'passed') {
        result.status = 'unmanaged';
    }

    return { result, commands, warnings };
}

/** Runs the runtime vue-tsc, the dedicated spec vue-tsc, and ESLint for one extension and assembles its result. */
export async function checkProject(context: {
    project: ExtensionToolingProject;
    tsResolution: OwnedConfig | null;
    eslintResolution: OwnedConfig | null;
    projectRoot: string;
    vueTscPath: string;
    eslintPath: string;
    eslintBaseArguments: string[];
    entitySchemaAvailable: boolean;
    options: CheckExtensionsOptions;
    limit: Limiter;
    commands: ToolingCommands;
}): Promise<{ result: ExtensionCheckResult; warnings: string[] }> {
    const {
        project,
        tsResolution,
        eslintResolution,
        projectRoot,
        vueTscPath,
        eslintPath,
        eslintBaseArguments,
        entitySchemaAvailable,
        options,
        limit,
        commands: toolingCommands,
    } = context;
    const commands: ExtensionCheckResult['commands'] = {};
    // Read once — the runtime and (later) spec runs share one baseline file.
    const baseline = readBaseline(projectRoot, project);
    const unmanagedTsTargets = project.targets.filter((target) => isUnmanagedConfig(target.tsconfig));
    const skippedTargets = collectSkippedTargets(project);
    const runtimeTargets = project.targets.filter(
        (target) => !isUnmanagedConfig(target.tsconfig) && countTypeCheckableFiles(projectRoot, [target.sourcePath]) > 0,
    );
    const specTargets = project.targets.filter(
        (target) => !isUnmanagedConfig(target.tsconfig) && countSpecFiles(projectRoot, [target.sourcePath]) > 0,
    );

    const runtime = await runTypeScriptStream({
        entitySchemaAvailable,
        streamTargets: runtimeTargets,
        unmanagedTargets: unmanagedTsTargets,
        unmanagedOutput: unmanagedTsTargets
            .map((target) => target.tsconfig?.detail ?? '')
            .filter(Boolean)
            .join('\n\n'),
        vueTscPath,
        projectRoot,
        basePath: project.basePath,
        configOf: (target) => checkTsconfigPath(target),
        baselineEntries: baseline?.typescript ?? [],
        expectedFiles: (target) => listTypeCheckableFiles(projectRoot, [target.sourcePath]),
        limit,
    });
    const typescript = runtime.result;

    if (runtime.commands) {
        commands.typescript = runtime.commands;
    }

    // The spec program adds jest types over the same surface and checks only the
    // spec files the runtime program excludes. It is gated on real specs existing
    // and mirrors the runtime program's blocked/unmanaged states (empty unmanaged
    // output — the probe output is shown once, under the runtime stream).
    const specs = await runTypeScriptStream({
        entitySchemaAvailable,
        streamTargets: specTargets,
        unmanagedTargets: unmanagedTsTargets,
        unmanagedOutput: '',
        vueTscPath,
        projectRoot,
        basePath: project.basePath,
        configOf: (target) => specTsconfigPath(target),
        baselineEntries: baseline?.typescriptSpecs ?? [],
        expectedFiles: (target) => listSpecFiles(projectRoot, [target.sourcePath]),
        limit,
    });
    const typescriptSpecs = specs.result;

    if (specs.commands) {
        commands.typescriptSpecs = specs.commands;
    }

    const eslintRun = await runEslintStream({
        project,
        projectRoot,
        eslintPath,
        eslintBaseArguments,
        baselineEntries: baseline?.eslint ?? [],
        basePath: project.basePath,
        options,
        limit,
        toolingCommands,
    });
    const eslint = eslintRun.result;

    if (eslintRun.commands) {
        commands.eslint = eslintRun.commands;
    }

    return {
        result: {
            project,
            tsResolution,
            eslintResolution,
            typescript,
            typescriptSpecs,
            eslint,
            commands,
            coverage: project.targets.map((target) => ({
                target,
                runtimeConfig: checkTsconfigPath(target),
                specConfig: specTsconfigPath(target),
                eslintConfig: target.eslintConfig?.path ?? 'eslint.config.mjs',
            })),
            skippedTargets,
        },
        warnings: eslintRun.warnings,
    };
}

/**
 * Records one extension's current findings as its baseline. Refuses when a
 * stream did not complete or a parser disagreed with its counter (a partial
 * baseline would hide real debt); type-surface diagnostics outside the
 * extension root are warned about, never recorded.
 */
export function recordProjectBaseline(
    result: ExtensionCheckResult,
    projectRoot: string,
    commands: ToolingCommands = DEFAULT_TOOLING_COMMANDS,
): { baselineUpdates: string[]; fatalDiagnostics: string[]; warnings: string[] } {
    // An extension that cannot hold a baseline must say so: silently recording
    // nothing let --update-baseline read as success while the very next plain
    // run failed again on the same findings.
    if (!canHoldBaseline(result.project)) {
        return {
            baselineUpdates: [],
            fatalDiagnostics: [],
            warnings: [
                result.project.vendor
                    ? `${result.project.name} is vendor-installed — no baseline was recorded; its findings are ` +
                      'already non-fatal unless --strict-vendor.'
                    : `${result.project.name}: baselines are only supported under custom/plugins/ — ` +
                      `${result.project.basePath} cannot record one, so its findings keep failing the check.`,
            ],
        };
    }

    const incompleteStreamNames = new Set<string>();

    if (result.project.targets.some((target) => isUnmanagedConfig(target.tsconfig))) {
        incompleteStreamNames.add('TypeScript');
        incompleteStreamNames.add('TS (specs)');
    }

    if (result.project.targets.some((target) => isUnmanagedConfig(target.eslintConfig))) {
        incompleteStreamNames.add('ESLint');
    }

    const streams: Array<[string, ToolRunResult]> = [
        [
            'TypeScript',
            result.typescript,
        ],
        [
            'TS (specs)',
            result.typescriptSpecs,
        ],
        [
            'ESLint',
            result.eslint,
        ],
    ];

    for (const [
        name,
        run,
    ] of streams) {
        if (
            [
                'unmanaged',
                'blocked',
                'tooling-error',
            ].includes(run.status)
        ) {
            incompleteStreamNames.add(name);
        }
    }

    const parserMismatch = [
        result.typescript,
        result.typescriptSpecs,
        result.eslint,
    ].some((run) => run.parseMismatch === true);

    if (incompleteStreamNames.size > 0 || parserMismatch) {
        return {
            baselineUpdates: [],
            fatalDiagnostics: [
                `${result.project.name}: baseline not updated — ` +
                    (parserMismatch
                        ? 'native and structured finding counts disagree.'
                        : `${[...incompleteStreamNames].join(', ')} did not complete.`),
            ],
            warnings: [],
        };
    }

    // Surface diagnostics (files outside the extension root) are never
    // baselineable; record only the extension's own in-root findings and warn
    // that the surface conflict must be fixed rather than recorded.
    const inRoot = (finding: TypeScriptFinding): boolean => toPosix(finding.file).startsWith(`${result.project.basePath}/`);
    const typescriptFindings = (
        result.typescript.typeScriptFindings ?? parseTypeScriptFindings(result.typescript.output)
    ).filter(inRoot);
    const typescriptSpecFindings = (
        result.typescriptSpecs.typeScriptFindings ?? parseTypeScriptFindings(result.typescriptSpecs.output)
    ).filter(inRoot);
    const eslintFindings = result.eslint.eslintFindings ?? parseEslintFindings(result.eslint.output);
    const surfaceCount = (result.typescript.surfaceDiagnostics ?? 0) + (result.typescriptSpecs.surfaceDiagnostics ?? 0);
    const warnings: string[] = [];

    if (surfaceCount > 0) {
        warnings.push(
            `${result.project.name}: ${surfaceCount} type-surface diagnostic(s) were not baselined — ` +
                'they originate outside the extension and must be fixed, not recorded (they keep failing the check).',
        );
    }

    const recorded =
        typescriptFindings.length +
        typescriptSpecFindings.length +
        eslintFindings.filter((finding) => finding.severity === 'error').length;
    const pruned =
        (result.typescript.staleBaseline ?? 0) +
        (result.typescriptSpecs.staleBaseline ?? 0) +
        (result.eslint.staleBaseline ?? 0);

    // Nothing to record and nothing to prune — do not litter a clean plugin with an empty file.
    if (recorded === 0 && readBaseline(projectRoot, result.project) === null) {
        return { baselineUpdates: [], fatalDiagnostics: [], warnings };
    }

    const write = writeBaselineFile(
        projectRoot,
        result.project,
        buildBaseline(
            { typescript: typescriptFindings, typescriptSpecs: typescriptSpecFindings, eslint: eslintFindings },
            result.project.basePath,
        ),
        false,
        commands,
    );

    if (write?.state === 'conflict') {
        return {
            baselineUpdates: [],
            fatalDiagnostics: [
                `${relativePosix(projectRoot, write.file)} is user-owned and not managed by this tool — ` +
                    'remove it (or restore the marker) and re-run --update-baseline.',
            ],
            warnings,
        };
    }

    if (write) {
        return {
            baselineUpdates: [
                `${relativePosix(projectRoot, write.file)} — ${recorded} recorded` +
                    `${pruned > 0 ? `, ${pruned} pruned` : ''}`,
            ],
            fatalDiagnostics: [],
            warnings,
        };
    }

    return { baselineUpdates: [], fatalDiagnostics: [], warnings };
}

/**
 * Derives the process exit code. Surface diagnostics and broken toolchains
 * fail even under --update-baseline; ordinary findings are accepted while
 * recording a baseline. --fail-on-skipped turns a skipped writable extension
 * into a failure, since a silent exit 0 there is a false green for CI.
 */
export function computeExitCode(
    results: ExtensionCheckResult[],
    options: CheckExtensionsOptions,
    hasFatalDiagnostics: boolean,
): number {
    let exitCode = hasFatalDiagnostics ? 1 : 0;

    for (const result of results) {
        // Only an extension that can actually record one gets its findings
        // absorbed: forgiving them for every project under --update-baseline
        // returned exit 0 for extensions where nothing was written at all.
        const baselineAbsorbs = options.updateBaseline === true && canHoldBaseline(result.project);
        const hasSurfaceDiagnostics =
            (result.typescript.surfaceDiagnostics ?? 0) > 0 || (result.typescriptSpecs.surfaceDiagnostics ?? 0) > 0;
        const hasFailure =
            hasSurfaceDiagnostics ||
            result.typescript.status === 'tooling-error' ||
            result.typescriptSpecs.status === 'tooling-error' ||
            result.eslint.status === 'tooling-error' ||
            (!baselineAbsorbs &&
                (result.typescript.status === 'failed' ||
                    result.typescriptSpecs.status === 'failed' ||
                    result.eslint.status === 'failed'));

        if (hasFailure && (!result.project.vendor || options.strictVendor)) {
            exitCode = 1;
        }

        if (
            options.failOnSkipped &&
            !result.project.vendor &&
            [
                result.typescript,
                result.typescriptSpecs,
                result.eslint,
            ].some((run) => run.status === 'unmanaged' || run.status === 'blocked')
        ) {
            exitCode = 1;
        }
    }

    return exitCode;
}
