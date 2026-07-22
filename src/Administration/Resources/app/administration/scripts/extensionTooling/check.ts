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
import { aggregateModeResolution, canonicalizePath, collectSkippedTargets, relativePosix, toPosix } from './shared';
import type { AdministrationTarget, ExtensionToolingProject, ModeResolution, SkippedTarget } from './shared';
import {
    PROCESS_TIMEOUT_MS,
    probeCacheEntryKey,
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
import { baselineFilePath, buildBaseline, diffEslint, diffTypeScript, readBaseline, writeBaselineFile } from './baseline';
import type { BaselineSplit, BaselineTsEntry, EslintFinding, TypeScriptFinding } from './baseline';

export type ToolStatus = 'passed' | 'failed' | 'unmanaged' | 'no-files' | 'blocked' | 'tooling-error';

export interface ToolRunResult {
    status: ToolStatus;
    output: string;
    /** Wall-clock time of this tool's stage, including time queued behind other tool runs of the same check. */
    durationMs: number;
    /** Total findings the tool reported (native count, never baseline-adjusted). */
    findings: number;
    /** Findings not covered by the baseline — the count that drives the exit code. */
    newFindings?: number;
    /** Findings suppressed by a matching baseline entry. */
    baselinedFindings?: number;
    /** Baseline entries that matched nothing this run (prunable via --update-baseline). */
    staleBaseline?: number;
    /** Identities of the new findings, for the report to point at them among the baselined ones. */
    newFindingRefs?: Array<{ file: string; code: string }>;
    /** Structured diagnostics retained for safe aggregate baselines across multiple programs. */
    typeScriptFindings?: TypeScriptFinding[];
    eslintFindings?: EslintFinding[];
    /** Whether any native counter disagreed with its structured parser. */
    parseMismatch?: boolean;
    /**
     * TypeScript diagnostics whose file lies outside the extension root — they
     * come from the shared type surface (or a global the extension pulled into
     * it), are always fatal, and are never recorded in the extension baseline.
     */
    surfaceDiagnostics?: number;
}

export interface AdministrationTargetCoverage {
    target: AdministrationTarget;
    /** Effective runtime config; identical canonical paths are executed once. */
    runtimeConfig: string;
    /** Dedicated spec config for this target. */
    specConfig: string;
    /** Effective ESLint config; identical canonical paths are executed once. */
    eslintConfig: string;
}

export interface ExtensionCheckResult {
    project: ExtensionToolingProject;
    tsResolution: ModeResolution;
    eslintResolution: ModeResolution;
    typescript: ToolRunResult;
    /** The dedicated spec type-check program (jest types, spec files only). */
    typescriptSpecs: ToolRunResult;
    eslint: ToolRunResult;
    /** Reproduction commands for the tool runs that actually happened. */
    commands: { typescript?: string[]; typescriptSpecs?: string[]; eslint?: string[] };
    /** Target/config routing used by this aggregate extension result. */
    coverage: AdministrationTargetCoverage[];
    /** Targets whose own config kept a tool from covering them, regardless of the run status. */
    skippedTargets?: SkippedTarget[];
}

export interface CheckExtensionsOptions {
    projectRoot: string;
    administrationRoot: string;
    pluginsConfigPath?: string;
    only?: string | string[];
    strictVendor?: boolean;
    maxWorkers?: number;
    /** Forward --fix to ESLint (never to vue-tsc). */
    fix?: boolean;
    /** Names passed literally via --only — vendor extensions are only fixed when named here. */
    explicitOnly?: string[];
    /** Record the current findings as the baseline instead of failing on them. */
    updateBaseline?: boolean;
    /** Fail (exit 1) when a writable extension's tool run was skipped/blocked, not only on findings. */
    failOnSkipped?: boolean;
}

export interface CheckExtensionsResult {
    results: ExtensionCheckResult[];
    fatalDiagnostics: string[];
    warnings: string[];
    /** Human lines describing baselines written under --update-baseline. */
    baselineUpdates: string[];
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

export type Limiter = <T>(job: () => Promise<T>) => Promise<T>;

/**
 * Counting semaphore shared across every child-process fan-out of one check
 * run: at most `capacity` limited jobs execute concurrently, FIFO. The
 * per-extension pool alone cannot bound a single extension's internal fan-out.
 */
export function createLimiter(capacity: number): Limiter {
    const limit = Math.max(1, capacity);
    let active = 0;
    const waiting: Array<() => void> = [];

    return async <T>(job: () => Promise<T>): Promise<T> => {
        if (active < limit) {
            active += 1;
        } else {
            await new Promise<void>((resolve) => waiting.push(resolve));
        }

        try {
            return await job();
        } finally {
            // Hand the slot to the next waiter directly — decrementing first
            // would let a fresh caller and the woken waiter both claim it.
            const next = waiting.shift();

            if (next) {
                next();
            } else {
                active -= 1;
            }
        }
    };
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
 * excluded to mirror the generated tsconfigs. Ambient declaration files
 * (`*.d.ts`) are excluded too: they carry no checkable source, and TypeScript
 * resolves them into the program on its own terms rather than as listed input
 * files — counting them would make a config that legitimately includes them via
 * a broad source glob look like it left a discovered file uncovered. Zero means
 * a TypeScript "pass" would be vacuous — reported as `no-files` instead of a
 * bare green.
 */
export function listTypeCheckableFiles(projectRoot: string, sourcePaths: string[]): string[] {
    const typeCheckableExtensions = [
        '.ts',
        '.tsx',
        '.vue',
    ];
    const files: string[] = [];

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
                    !/\.spec\.(ts|tsx|js)$/.test(entry.name) &&
                    !entry.name.endsWith('.d.ts')
                ) {
                    files.push(canonicalizePath(entryPath));
                }
            }
        }
    }

    return files.sort();
}

export function countTypeCheckableFiles(projectRoot: string, sourcePaths: string[]): number {
    return listTypeCheckableFiles(projectRoot, sourcePaths).length;
}

/**
 * Counts the spec files the dedicated spec program would type-check
 * (`.spec.ts`/`.spec.tsx`; `.spec.js` is parsed but not type-checked, like any
 * `.js`). Zero means the spec program would be vacuous — reported as no-files.
 */
export function listSpecFiles(projectRoot: string, sourcePaths: string[]): string[] {
    const files: string[] = [];

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
                } else if (entry.isFile() && /\.spec\.(ts|tsx)$/.test(entry.name)) {
                    files.push(canonicalizePath(entryPath));
                }
            }
        }
    }

    return files.sort();
}

export function countSpecFiles(projectRoot: string, sourcePaths: string[]): number {
    return listSpecFiles(projectRoot, sourcePaths).length;
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

/**
 * Structures the TypeScript findings the counter counts — one per
 * `error TSxxxx:` line, in both the compact `file(l,c):` and the pretty
 * `file:l:c -` location formats. Line and column are dropped on purpose: the
 * baseline keys on file + code + message so a recorded finding survives line
 * drift. Indented related-information lines carry no `error TSxxxx:` and are
 * skipped, so the result length matches countTypeScriptFindings.
 */
export function parseTypeScriptFindings(output: string): TypeScriptFinding[] {
    const findings: TypeScriptFinding[] = [];

    for (const line of output.split(/\r?\n/)) {
        const compact = line.match(/^(.+?)\(\d+,\d+\): error (TS\d+): (.*)$/);
        const match = compact ?? line.match(/^(.+?):\d+:\d+ - error (TS\d+): (.*)$/);

        if (match) {
            findings.push({ file: match[1], code: match[2], message: match[3].trim() });
        }
    }

    return findings;
}

/** A file-header line in ESLint's stylish output is a path ending in a lintable-file suffix. */
const ESLINT_FILE_HEADER_PATTERN = /\.(ts|tsx|js|jsx|mjs|cjs|vue|twig|html|json)$/;

/**
 * Structures ESLint's stylish output — a bare file-header line followed by
 * indented `line:col severity message rule` rows (rule id last, separated from
 * the message by column padding). Line and column are dropped (the baseline
 * keys on file + rule + message). Both severities are returned so the length
 * matches countEslintFindings; callers baseline only error-severity findings,
 * since warnings never fail the check.
 */
export function parseEslintFindings(output: string): EslintFinding[] {
    const findings: EslintFinding[] = [];
    let currentFile: string | null = null;
    let lastFinding: EslintFinding | null = null;

    for (const line of output.split(/\r?\n/)) {
        if (line.trim() === '') {
            lastFinding = null;

            continue;
        }

        const row = line.match(/^\s+\d+:\d+\s+(error|warning)\s+(.+)$/);

        if (row && currentFile) {
            const ruleMatch = row[2].match(/^(.*?)\s{2,}(\S+)$/);
            const finding: EslintFinding = {
                file: currentFile,
                rule: ruleMatch ? ruleMatch[2] : '',
                message: (ruleMatch ? ruleMatch[1] : row[2]).trim(),
                severity: row[1] as 'error' | 'warning',
            };

            findings.push(finding);
            lastFinding = finding;

            continue;
        }

        // A file header is a path to a lintable file. ESLint prints multi-line
        // rule messages (e.g. @typescript-eslint/unbound-method) with their
        // continuation lines un-indented; requiring a source-file suffix keeps
        // those from being mistaken for a new file header, which would otherwise
        // corrupt the file attribution of every finding below them.
        if (!/^\s/.test(line) && ESLINT_FILE_HEADER_PATTERN.test(line.trim())) {
            currentFile = line.trim();
            lastFinding = null;

            continue;
        }

        // The rule id of a multi-line message is printed on its last
        // (un-indented) continuation line — attribute it to the finding above.
        const continuationRule = line.match(/\s{2,}(\S+)$/);

        if (lastFinding && continuationRule && continuationRule[1].includes('/')) {
            lastFinding.rule = continuationRule[1];
        }
    }

    return findings;
}

/**
 * Turns a completed tool run and its baseline split into the reported status
 * and the new/baselined/stale counts. A clean run passes; a run whose findings
 * are all baselined also passes (its output is then suppressed like any pass);
 * a non-zero exit we cannot attribute to baselined findings stays failed —
 * including the parse-mismatch case, so a parser bug never greens real findings.
 */
function applyBaseline<F>(
    runStatus: number,
    totalFindings: number,
    split: BaselineSplit<F>,
    refOf: (finding: F) => { file: string; code: string },
): Pick<ToolRunResult, 'status' | 'findings' | 'newFindings' | 'baselinedFindings' | 'staleBaseline' | 'newFindingRefs'> {
    const newFindings = split.newFindings.length;
    let status: ToolStatus;

    if (runStatus === 0) {
        status = 'passed';
    } else if (newFindings > 0 || split.parseMismatch || totalFindings === 0) {
        status = 'failed';
    } else {
        // Non-zero exit, but every reported finding matched the baseline.
        status = 'passed';
    }

    return {
        status,
        findings: totalFindings,
        newFindings,
        baselinedFindings: split.baselinedCount,
        staleBaseline: split.staleCount,
        newFindingRefs: split.newFindings.map(refOf),
    };
}

/**
 * Runs one vue-tsc program (runtime or spec) against a tsconfig and interprets
 * the outcome through the baseline. Timeouts and empty non-zero exits surface as
 * tooling errors; otherwise the findings are split against the given baseline
 * stream. Gating (which program, whether to run at all) stays with the caller.
 */
async function runTypeScriptProgram(
    vueTscPath: string,
    tsconfigPath: string,
    projectRoot: string,
    basePath: string,
    baselineEntries: BaselineTsEntry[],
): Promise<{ result: ToolRunResult; command: string }> {
    const vueTscArguments = buildVueTscArguments(vueTscPath, tsconfigPath);
    const command = formatCommand(projectRoot, vueTscArguments);
    const run = await runCommand(process.execPath, vueTscArguments, projectRoot);
    const findings = countTypeScriptFindings(run.output);

    if (run.timedOut) {
        return {
            command,
            result: {
                status: 'tooling-error',
                output: `vue-tsc timed out after ${PROCESS_TIMEOUT_MS / 1000}s.\n${run.output}`,
                durationMs: run.durationMs,
                findings,
            },
        };
    }

    if (run.status !== 0 && findings === 0 && run.output.trim() === '') {
        return {
            command,
            result: {
                status: 'tooling-error',
                output: `vue-tsc exited with status ${run.status} and no output.`,
                durationMs: run.durationMs,
                findings: 0,
            },
        };
    }

    const parsedFindings = parseTypeScriptFindings(run.output);
    const split = diffTypeScript(parsedFindings, baselineEntries, basePath, findings);

    return {
        command,
        result: {
            ...applyBaseline(run.status, findings, split, (finding) => ({ file: finding.file, code: finding.code })),
            output: run.output,
            durationMs: run.durationMs,
            typeScriptFindings: parsedFindings,
            parseMismatch: split.parseMismatch,
        },
    };
}

interface TargetProgramGroup {
    configPath: string;
    targets: AdministrationTarget[];
}

function groupTargetsByConfig(
    projectRoot: string,
    targets: AdministrationTarget[],
    configOf: (target: AdministrationTarget) => string,
): TargetProgramGroup[] {
    const groups = new Map<string, TargetProgramGroup>();

    for (const target of targets) {
        const configPath = path.resolve(projectRoot, configOf(target));
        const key = canonicalizePath(configPath);
        const group = groups.get(key) ?? { configPath: key, targets: [] };

        group.targets.push(target);
        groups.set(key, group);
    }

    return [...groups.values()].sort((left, right) => left.configPath.localeCompare(right.configPath));
}

function deduplicateByMaximumMultiplicity<F>(groups: F[][], keyOf: (finding: F) => string): F[] {
    const representatives = new Map<string, F>();
    const maximumCounts = new Map<string, number>();

    for (const findings of groups) {
        const groupCounts = new Map<string, number>();

        for (const finding of findings) {
            const key = keyOf(finding);

            representatives.set(key, representatives.get(key) ?? finding);
            groupCounts.set(key, (groupCounts.get(key) ?? 0) + 1);
        }

        for (const [
            key,
            count,
        ] of groupCounts) {
            maximumCounts.set(key, Math.max(maximumCounts.get(key) ?? 0, count));
        }
    }

    return [...maximumCounts.entries()].flatMap(
        ([
            key,
            count,
        ]) => Array.from({ length: count }, () => representatives.get(key) as F),
    );
}

async function verifyVueTscCoverage(
    vueTscPath: string,
    group: TargetProgramGroup,
    projectRoot: string,
    expectedFiles: (target: AdministrationTarget) => string[],
): Promise<{ error?: string; command: string }> {
    const args = [
        vueTscPath,
        '--showConfig',
        '--project',
        group.configPath,
    ];
    const command = formatCommand(projectRoot, args);
    const resolved = await runCommand(process.execPath, args, projectRoot);

    if (resolved.status !== 0) {
        return {
            error: resolved.output || `vue-tsc --showConfig exited with status ${resolved.status}.`,
            command,
        };
    }

    let files: string[];

    try {
        const config = JSON.parse(resolved.output) as { files?: string[] };

        files = (config.files ?? []).map((file) => canonicalizePath(path.resolve(path.dirname(group.configPath), file)));
    } catch {
        return { error: 'vue-tsc --showConfig returned invalid JSON.', command };
    }

    const covered = new Set(files);
    const missing = group.targets.flatMap((target) => expectedFiles(target).filter((file) => !covered.has(file)));

    if (missing.length > 0) {
        return {
            error:
                `${relativePosix(projectRoot, group.configPath)} does not cover ${missing.length} discovered file(s): ` +
                missing
                    .slice(0, 3)
                    .map((file) => relativePosix(projectRoot, file))
                    .join(', ') +
                (missing.length > 3 ? ', …' : ''),
            command,
        };
    }

    return { command };
}

async function runTypeScriptPrograms(
    vueTscPath: string,
    groups: TargetProgramGroup[],
    projectRoot: string,
    basePath: string,
    baselineEntries: BaselineTsEntry[],
    expectedFiles: (target: AdministrationTarget) => string[],
    limit: Limiter,
): Promise<{ result: ToolRunResult; commands: string[] }> {
    const startedAt = Date.now();
    const coverage = await Promise.all(
        groups.map((group) => limit(() => verifyVueTscCoverage(vueTscPath, group, projectRoot, expectedFiles))),
    );
    const coverageErrors = coverage.flatMap((entry) => (entry.error ? [entry.error] : []));

    if (coverageErrors.length > 0) {
        return {
            result: {
                status: 'tooling-error',
                output: coverageErrors.join('\n'),
                durationMs: Date.now() - startedAt,
                findings: 0,
            },
            commands: coverage.map((entry) => entry.command),
        };
    }

    const runs = await Promise.all(
        groups.map((group) => limit(() => runTypeScriptProgram(vueTscPath, group.configPath, projectRoot, basePath, []))),
    );
    const outputs = runs.map((run) => run.result.output).filter((output) => output.trim() !== '');
    const parsedGroups = runs.map((run) => run.result.typeScriptFindings ?? []);
    const findings = deduplicateByMaximumMultiplicity(
        parsedGroups,
        (finding) => `${finding.file}\u0000${finding.code}\u0000${finding.message}`,
    );
    const parseMismatch = runs.some((run) => run.result.parseMismatch === true);
    const durationMs = Date.now() - startedAt;
    const toolingErrors = runs.filter((run) => run.result.status === 'tooling-error');

    if (toolingErrors.length > 0) {
        return {
            result: {
                status: 'tooling-error',
                output: outputs.join('\n\n'),
                durationMs,
                findings: findings.length,
                typeScriptFindings: findings,
                parseMismatch,
            },
            commands: runs.map((run) => run.command),
        };
    }

    // Split findings by origin. A diagnostic whose file lies outside the
    // extension root comes from the shared Administration type surface (or a
    // global declaration the extension pulled into it), not the extension's own
    // code: it is always fatal and never baselineable. In-root findings are the
    // extension's own debt and go through the baseline as usual — a surface
    // conflict must not block baselining the legitimate in-root findings.
    const isInRoot = (finding: TypeScriptFinding): boolean => toPosix(finding.file).startsWith(`${basePath}/`);
    const surfaceFindings = findings.filter((finding) => !isInRoot(finding));
    const inRootFindings = findings.filter(isInRoot);
    const split = diffTypeScript(
        inRootFindings,
        baselineEntries,
        basePath,
        parseMismatch ? inRootFindings.length + 1 : inRootFindings.length,
    );
    const status: ToolStatus =
        surfaceFindings.length > 0 || split.newFindings.length > 0 || split.parseMismatch ? 'failed' : 'passed';
    const surfaceHeader =
        surfaceFindings.length > 0
            ? `The shared Administration type surface emitted ${surfaceFindings.length} diagnostic(s) outside ` +
              `${basePath} (${[...new Set(surfaceFindings.map((finding) => finding.file))]
                  .slice(0, 3)
                  .join(', ')}${surfaceFindings.length > 3 ? ', …' : ''}). This is a type-surface failure, not ` +
              'extension debt — it cannot be baselined. A global declaration in the extension may conflict with the ' +
              'shipped surface; check the named file.'
            : '';

    return {
        result: {
            status,
            findings: findings.length,
            newFindings: split.newFindings.length + surfaceFindings.length,
            baselinedFindings: split.baselinedCount,
            staleBaseline: split.staleCount,
            newFindingRefs: [
                ...surfaceFindings,
                ...split.newFindings,
            ].map((finding) => ({ file: finding.file, code: finding.code })),
            surfaceDiagnostics: surfaceFindings.length,
            output: [
                surfaceHeader,
                outputs.join('\n\n'),
            ]
                .filter((part) => part.trim() !== '')
                .join('\n\n'),
            durationMs,
            typeScriptFindings: findings,
            parseMismatch: split.parseMismatch,
        },
        commands: runs.map((run) => run.command),
    };
}

export function buildVueTscArguments(vueTscPath: string, tsconfigPath: string): string[] {
    return [
        vueTscPath,
        '--noEmit',
        '--project',
        tsconfigPath,
    ];
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
export function appendFixHint(output: string, extensionName: string): string {
    if (!output.includes('potentially fixable with the `--fix` option')) {
        return output;
    }

    return `${output}\n  → auto-fixable: composer admin:check-extensions -- --only=${extensionName} --fix`;
}

function formatCommand(cwd: string, args: string[]): string {
    const quote = (value: string): string => (/\s/.test(value) ? JSON.stringify(value) : value);

    return `cd ${quote(cwd)} && ${quote(process.execPath)} ${args.map(quote).join(' ')}`;
}

/**
 * ESLint prints absolute paths (vue-tsc already prints project-relative ones
 * because of its cwd). Strip the project root — including its canonicalized
 * form, macOS resolves /var to /private/var — so both tools read the same.
 */
export function relativizeToolOutput(output: string, projectRoot: string): string {
    let relativized = output;

    for (const root of new Set([
        projectRoot,
        canonicalizePath(projectRoot),
    ])) {
        relativized = relativized.split(`${root}${path.sep}`).join('');
    }

    return relativized;
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
        const matches = (project: ExtensionToolingProject, name: string): boolean =>
            project.name === name || project.technicalNames.includes(name);
        // Resolve every requested name independently. A single unknown name
        // fails the whole run before any tool executes — a renamed/removed
        // target must never leave CI green while it is silently unchecked.
        const unmatched = selected.filter((name) => !projects.some((project) => matches(project, name)));

        if (unmatched.length > 0) {
            const available = setupResult.manifest.projects.map((project) => project.name).join(', ');

            fatalDiagnostics.push(
                `--only names unknown extension(s): ${unmatched.join(', ')}. Discovered: ${available || '(none)'}.`,
            );
            projects = [];
        } else {
            projects = projects.filter((project) => selected.some((name) => matches(project, name)));
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
    const limit = createLimiter(maxWorkers);
    const vueTscPath = path.join(administrationRoot, 'node_modules', 'vue-tsc', 'bin', 'vue-tsc.js');
    const eslintPath = path.join(administrationRoot, 'node_modules', 'eslint', 'bin', 'eslint.js');

    if (projects.length > 0 && !fs.existsSync(vueTscPath)) {
        fatalDiagnostics.push(
            `vue-tsc is not installed in the Administration (${relativePosix(projectRoot, vueTscPath)}). ` +
                'Fix: composer init:js',
        );
    }

    const modeJobs = projects.flatMap((project) =>
        project.targets.map((target) => async () => {
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

                return resolution ? { ...target, ts: resolution.tsResolution, eslint: resolution.eslintResolution } : target;
            }),
        };

        return {
            project: resolvedProject,
            tsResolution: aggregateModeResolution(resolvedProject, 'ts'),
            eslintResolution: aggregateModeResolution(resolvedProject, 'eslint'),
        };
    });

    // Persist the verified verdicts so subsequent setup runs render the same
    // state. Merge with existing entries (a --only run must not drop other
    // extensions' verdicts); prune extensions that no longer exist.
    const knownNames = new Set(
        setupResult.manifest.projects.flatMap((project) =>
            project.targets.map((target) => probeCacheEntryKey(project.name, target)),
        ),
    );
    const probeCache: ProbeCacheFile = {
        version: 2,
        entries: Object.fromEntries(
            Object.entries(readProbeCache(projectRoot)?.entries ?? {}).filter(([name]) => knownNames.has(name)),
        ),
    };

    for (const { projectName, target, tsResolution, eslintResolution } of resolvedTargets) {
        if (!target.tsconfig && !target.eslintConfig) {
            continue;
        }

        const inputs = probeInputFiles(target, projectRoot, administrationRoot);

        probeCache.entries[probeCacheEntryKey(projectName, target)] = {
            ...(target.tsconfig
                ? { ts: { key: probeCacheKey(inputs.ts), resolution: toCacheableResolution(tsResolution) } }
                : {}),
            ...(target.eslintConfig
                ? { eslint: { key: probeCacheKey(inputs.eslint), resolution: toCacheableResolution(eslintResolution) } }
                : {}),
        };
    }

    writeProbeCache(projectRoot, probeCache);

    const checkJobs = resolvedModes.map(({ project, tsResolution, eslintResolution }) => async () => {
        let typescript: ToolRunResult;
        let typescriptSpecs: ToolRunResult;
        let eslint: ToolRunResult;
        const commands: ExtensionCheckResult['commands'] = {};
        // Read once — the runtime and (later) spec runs share one baseline file.
        const baseline = readBaseline(projectRoot, project);
        const unmanagedTsTargets = project.targets.filter((target) => target.ts.mode === 'unmanaged');
        const unmanagedEslintTargets = project.targets.filter((target) => target.eslint.mode === 'unmanaged');
        const skippedTargets = collectSkippedTargets(project);
        const runtimeTargets = project.targets.filter(
            (target) =>
                target.ts.mode !== 'unmanaged' &&
                (target.ts.mode === 'bridged' || countTypeCheckableFiles(projectRoot, [target.sourcePath]) > 0),
        );
        const specTargets = project.targets.filter(
            (target) => target.ts.mode !== 'unmanaged' && countSpecFiles(projectRoot, [target.sourcePath]) > 0,
        );

        if (!setupResult.manifest.entitySchemaAvailable) {
            // Running vue-tsc against the empty-schema stub would bury the one
            // real cause under hundreds of cascade findings, most of them in
            // the Administration's own files. Refuse instead; the fatal
            // diagnostic names the fix. ESLint still runs.
            typescript = { status: 'blocked', output: '', durationMs: 0, findings: 0 };
        } else if (runtimeTargets.length === 0 && unmanagedTsTargets.length > 0) {
            typescript = {
                status: 'unmanaged',
                output: unmanagedTsTargets
                    .map((target) => relativizeToolOutput(target.ts.probeOutput ?? '', projectRoot))
                    .filter(Boolean)
                    .join('\n\n'),
                durationMs: 0,
                findings: 0,
            };
        } else if (runtimeTargets.length === 0) {
            typescript = { status: 'no-files', output: '', durationMs: 0, findings: 0 };
        } else if (!fs.existsSync(vueTscPath)) {
            typescript = { status: 'tooling-error', output: 'vue-tsc is not installed.', durationMs: 0, findings: 0 };
        } else {
            const program = await runTypeScriptPrograms(
                vueTscPath,
                groupTargetsByConfig(projectRoot, runtimeTargets, (target) => target.checkTsconfig),
                projectRoot,
                project.basePath,
                baseline?.typescript ?? [],
                (target) => listTypeCheckableFiles(projectRoot, [target.sourcePath]),
                limit,
            );

            typescript = program.result;
            commands.typescript = program.commands;

            if (unmanagedTsTargets.length > 0 && typescript.status === 'passed') {
                typescript.status = 'unmanaged';
            }
        }

        // The spec program adds jest types over the same surface and checks only
        // the spec files the runtime program excludes. It is gated on real specs
        // existing and mirrors the runtime program's blocked/unmanaged states.
        if (!setupResult.manifest.entitySchemaAvailable) {
            typescriptSpecs = { status: 'blocked', output: '', durationMs: 0, findings: 0 };
        } else if (specTargets.length === 0 && unmanagedTsTargets.length > 0) {
            typescriptSpecs = { status: 'unmanaged', output: '', durationMs: 0, findings: 0 };
        } else if (specTargets.length === 0) {
            typescriptSpecs = { status: 'no-files', output: '', durationMs: 0, findings: 0 };
        } else if (!fs.existsSync(vueTscPath)) {
            typescriptSpecs = { status: 'tooling-error', output: 'vue-tsc is not installed.', durationMs: 0, findings: 0 };
        } else {
            const program = await runTypeScriptPrograms(
                vueTscPath,
                groupTargetsByConfig(projectRoot, specTargets, (target) => target.specTsconfig),
                projectRoot,
                project.basePath,
                baseline?.typescriptSpecs ?? [],
                (target) => listSpecFiles(projectRoot, [target.sourcePath]),
                limit,
            );

            typescriptSpecs = program.result;
            commands.typescriptSpecs = program.commands;

            if (unmanagedTsTargets.length > 0 && typescriptSpecs.status === 'passed') {
                typescriptSpecs.status = 'unmanaged';
            }
        }

        const eslintTargets = project.targets.filter(
            (target) => target.eslint.mode !== 'unmanaged' && findFirstSourceFile(projectRoot, [target.sourcePath]) !== null,
        );

        if (eslintTargets.length === 0 && unmanagedEslintTargets.length > 0) {
            eslint = {
                status: 'unmanaged',
                output: unmanagedEslintTargets
                    .map((target) => relativizeToolOutput(target.eslint.probeOutput ?? '', projectRoot))
                    .filter(Boolean)
                    .join('\n\n'),
                durationMs: 0,
                findings: 0,
            };
        } else if (eslintTargets.length === 0) {
            eslint = { status: 'no-files', output: '', durationMs: 0, findings: 0 };
        } else {
            // Vendor-installed extensions are not ours to rewrite: --fix only
            // applies to them when named explicitly via --only.
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
                (target) => target.eslintConfig ?? path.join(projectRoot, 'eslint.config.mjs'),
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
                baseline?.eslint ?? [],
                project.basePath,
                parseMismatch ? parsedFindings.length + 1 : parsedFindings.length,
            );
            const toolingError = runs.find(
                ({ run, nativeFindings }) => run.timedOut || (run.status !== 0 && nativeFindings === 0),
            );

            commands.eslint = runs.map((run) => run.command);

            if (toolingError) {
                eslint = {
                    status: 'tooling-error',
                    output: toolingError.run.timedOut
                        ? `ESLint timed out after ${PROCESS_TIMEOUT_MS / 1000}s.\n${outputs.join('\n\n')}`
                        : outputs.join('\n\n') || `ESLint exited with status ${toolingError.run.status} and no output.`,
                    durationMs: Date.now() - startedAt,
                    findings: parsedFindings.length,
                    eslintFindings: parsedFindings,
                    parseMismatch,
                };
            } else {
                const output = outputs.join('\n\n');

                eslint = {
                    ...applyBaseline(
                        runs.some((run) => run.run.status !== 0) ? 1 : 0,
                        parsedFindings.length,
                        split,
                        (finding) => ({ file: finding.file, code: finding.rule }),
                    ),
                    output: applyFix ? output : appendFixHint(output, project.name),
                    durationMs: Date.now() - startedAt,
                    eslintFindings: parsedFindings,
                    parseMismatch: split.parseMismatch,
                };

                if (unmanagedEslintTargets.length > 0 && eslint.status === 'passed') {
                    eslint.status = 'unmanaged';
                }
            }
        }

        return {
            project,
            tsResolution,
            eslintResolution,
            typescript,
            typescriptSpecs,
            eslint,
            commands,
            coverage: project.targets.map((target) => ({
                target,
                runtimeConfig: target.checkTsconfig,
                specConfig: target.specTsconfig,
                eslintConfig: target.eslintConfig ?? 'eslint.config.mjs',
            })),
            skippedTargets,
        };
    });
    const results = await runPool(checkJobs, maxWorkers);
    const baselineUpdates: string[] = [];

    // Record the current findings as the baseline. Only meaningful once the
    // full TypeScript surface ran, so it is skipped while the schema is missing.
    if (options.updateBaseline && setupResult.manifest.entitySchemaAvailable) {
        for (const result of results) {
            if (!baselineFilePath(projectRoot, result.project)) {
                continue;
            }

            const incompleteStreamNames = new Set<string>();

            if (result.project.targets.some((target) => target.ts.mode === 'unmanaged')) {
                incompleteStreamNames.add('TypeScript');
                incompleteStreamNames.add('TS (specs)');
            }

            if (result.project.targets.some((target) => target.eslint.mode === 'unmanaged')) {
                incompleteStreamNames.add('ESLint');
            }

            for (const [
                name,
                run,
            ] of [
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
            ] as Array<[string, ToolRunResult]>) {
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
                fatalDiagnostics.push(
                    `${result.project.name}: baseline not updated — ` +
                        (parserMismatch
                            ? 'native and structured finding counts disagree.'
                            : `${[...incompleteStreamNames].join(', ')} did not complete.`),
                );

                continue;
            }

            // Surface diagnostics (files outside the extension root) are never
            // baselineable; record only the extension's own in-root findings and
            // warn that the surface conflict must be fixed rather than recorded.
            const inRoot = (finding: TypeScriptFinding): boolean =>
                toPosix(finding.file).startsWith(`${result.project.basePath}/`);
            const typescriptFindings = (
                result.typescript.typeScriptFindings ?? parseTypeScriptFindings(result.typescript.output)
            ).filter(inRoot);
            const typescriptSpecFindings = (
                result.typescriptSpecs.typeScriptFindings ?? parseTypeScriptFindings(result.typescriptSpecs.output)
            ).filter(inRoot);
            const eslintFindings = result.eslint.eslintFindings ?? parseEslintFindings(result.eslint.output);
            const surfaceCount =
                (result.typescript.surfaceDiagnostics ?? 0) + (result.typescriptSpecs.surfaceDiagnostics ?? 0);

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
                continue;
            }

            const write = writeBaselineFile(
                projectRoot,
                result.project,
                buildBaseline(
                    { typescript: typescriptFindings, typescriptSpecs: typescriptSpecFindings, eslint: eslintFindings },
                    result.project.basePath,
                ),
            );

            if (write?.state === 'conflict') {
                fatalDiagnostics.push(
                    `${relativePosix(projectRoot, write.file)} is user-owned and not managed by this tool — ` +
                        'remove it (or restore the marker) and re-run --update-baseline.',
                );
            } else if (write) {
                baselineUpdates.push(
                    `${relativePosix(projectRoot, write.file)} — ${recorded} recorded` +
                        `${pruned > 0 ? `, ${pruned} pruned` : ''}`,
                );
            }
        }
    }

    let exitCode = fatalDiagnostics.length > 0 ? 1 : 0;

    for (const result of results) {
        // Surface diagnostics (findings outside the extension root) are never
        // baselineable, so they fail even under --update-baseline.
        const hasSurfaceDiagnostics =
            (result.typescript.surfaceDiagnostics ?? 0) > 0 || (result.typescriptSpecs.surfaceDiagnostics ?? 0) > 0;
        // Under --update-baseline the current findings are being accepted, so
        // they are not failures; a broken toolchain still is.
        const hasFailure =
            hasSurfaceDiagnostics ||
            result.typescript.status === 'tooling-error' ||
            result.typescriptSpecs.status === 'tooling-error' ||
            result.eslint.status === 'tooling-error' ||
            (!options.updateBaseline &&
                (result.typescript.status === 'failed' ||
                    result.typescriptSpecs.status === 'failed' ||
                    result.eslint.status === 'failed'));

        if (hasFailure && (!result.project.vendor || options.strictVendor)) {
            exitCode = 1;
        }

        // --fail-on-skipped: a writable extension whose config never composed
        // the preset was not checked. Silent exit 0 there is a false green for
        // CI; vendor extensions keep their separate --strict-vendor policy.
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

    return {
        results,
        fatalDiagnostics,
        warnings,
        baselineUpdates,
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
        {
            name: '--fail-on-skipped',
            description: 'Fail (exit 1) when a writable extension is skipped/blocked instead of checked (for CI).',
        },
        {
            name: '--fix',
            description:
                'Apply ESLint autofixes, incl. Shopware deprecation codemods (sw-* → mt-*), not only formatting ' +
                '(vendor extensions only when named via --only).',
        },
        {
            name: '--update-baseline',
            description: 'Record the current findings as the per-plugin baseline; the check then fails only on new ones.',
        },
        { name: '--show-commands', description: 'Print the underlying vue-tsc/ESLint invocation per extension.' },
        { name: '--verbose', description: 'Also print tool output for passing and skipped extensions.' },
        {
            name: '--summary',
            description: 'Add a triage summary grouping findings by rule/code and by file (additive to native output).',
        },
        {
            name: '--summary-only',
            description: 'Print only the triage summary, suppressing the raw per-finding output (for very large logs).',
        },
        {
            name: '--summary-top',
            value: 'required',
            valueName: '<n>',
            description: 'How many top rules/codes and files to list per stream in the summary (default 10).',
        },
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

    const summaryTopValue = parsed.values['--summary-top'];
    const summaryTop = summaryTopValue === undefined ? undefined : Number(summaryTopValue);

    if (summaryTop !== undefined && (!Number.isInteger(summaryTop) || summaryTop < 1)) {
        console.error(`--summary-top must be a positive integer, got "${summaryTopValue}".\n\n${renderHelp(CHECK_COMMAND)}`);

        return 2;
    }

    if (parsed.flags.has('--update-baseline') && parsed.flags.has('--fix')) {
        console.error(
            `--update-baseline and --fix are mutually exclusive — fix first, then record the baseline.\n\n${renderHelp(CHECK_COMMAND)}`,
        );

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
        fix: parsed.flags.has('--fix'),
        explicitOnly: only !== undefined ? normalizeSelection(only) : [],
        updateBaseline: parsed.flags.has('--update-baseline'),
        failOnSkipped: parsed.flags.has('--fail-on-skipped'),
    });

    console.log(
        renderCheckReport(check, {
            verbose: parsed.flags.has('--verbose'),
            showCommands: parsed.flags.has('--show-commands'),
            failOnSkipped: parsed.flags.has('--fail-on-skipped'),
            fix: parsed.flags.has('--fix'),
            summary: parsed.flags.has('--summary') || parsed.flags.has('--summary-only'),
            summaryOnly: parsed.flags.has('--summary-only'),
            summaryTop,
        }),
    );

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
