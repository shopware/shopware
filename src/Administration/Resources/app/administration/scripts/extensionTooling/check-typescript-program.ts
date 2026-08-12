/**
 * @sw-package framework
 *
 * Runs the vue-tsc programs for one extension: verifies each generated tsconfig
 * actually covers the files discovered on disk, runs the programs under the
 * shared limiter, and splits the aggregated diagnostics against the baseline.
 * Diagnostics whose file lies outside the extension root are treated as a
 * type-surface failure — always fatal, never baselineable.
 */

import path from 'path';
import { PROCESS_TIMEOUT_MS, runCommand } from './probe-command';
import { diffTypeScript } from './baseline';
import type { BaselineTsEntry, TypeScriptFinding } from './baseline';
import { canonicalizePath, relativePosix, relativizeToolOutput, toPosix, wrapText } from './shared';
import type { AdministrationTarget } from './shared';
import {
    countTypeScriptFindings,
    deduplicateByMaximumMultiplicity,
    joinProgramOutputs,
    parseTypeScriptFindings,
} from './check-parsing';
import { formatCommand } from './check-pipeline';
import type { TargetProgramGroup } from './check-pipeline';
import type { Limiter, ToolRunResult, ToolStatus } from './check-types';

export function buildVueTscArguments(vueTscPath: string, tsconfigPath: string): string[] {
    return [
        vueTscPath,
        '--noEmit',
        '--project',
        tsconfigPath,
    ];
}

/**
 * Runs one vue-tsc program (runtime or spec) against a tsconfig and reports what
 * it saw. Timeouts and non-zero exits without a parseable diagnostic surface as
 * tooling errors. The baseline is deliberately not applied here: a project's
 * programs overlap on the shared type surface, so only the caller — after
 * de-duplicating across every program — can split findings against it. Gating
 * (which program, whether to run at all) stays with the caller too.
 */
async function runTypeScriptProgram(
    vueTscPath: string,
    tsconfigPath: string,
    projectRoot: string,
): Promise<{ result: ToolRunResult; command: string }> {
    const vueTscArguments = buildVueTscArguments(vueTscPath, tsconfigPath);
    const command = formatCommand(projectRoot, vueTscArguments);
    const run = await runCommand(process.execPath, vueTscArguments, projectRoot);
    // vue-tsc prints cwd-relative paths only while its --project argument shares
    // the cwd's form. The runner canonicalizes that path, so under a symlinked
    // root the diagnostics come back absolute — relativize them like the ESLint
    // stream, or the in-root/surface split below misreads every finding.
    const output = relativizeToolOutput(run.output, projectRoot);
    const findings = countTypeScriptFindings(output);

    if (run.timedOut) {
        return {
            command,
            result: {
                status: 'tooling-error',
                output: `vue-tsc timed out after ${PROCESS_TIMEOUT_MS / 1000}s.\n${output}`,
                durationMs: run.durationMs,
                findings,
            },
        };
    }

    // A non-zero exit that produced no parseable diagnostic is a crashed tool,
    // not a clean run — whether or not it printed noise (a panic, an OOM
    // stack trace). Treating only the empty-output case as an error let a
    // crash-with-output run fall through to the baseline and report `passed`.
    // Mirrors the ESLint stream's predicate in check-run.ts.
    if (run.status !== 0 && findings === 0) {
        const detail = output.trim();

        return {
            command,
            result: {
                status: 'tooling-error',
                output:
                    detail === ''
                        ? `vue-tsc exited with status ${run.status} and no output.`
                        : `vue-tsc exited with status ${run.status} without a parseable diagnostic:\n${detail}`,
                durationMs: run.durationMs,
                findings: 0,
            },
        };
    }

    const parsedFindings = parseTypeScriptFindings(output);

    return {
        command,
        result: {
            status: findings > 0 ? 'failed' : 'passed',
            findings,
            output,
            durationMs: run.durationMs,
            typeScriptFindings: parsedFindings,
            // The structured parse must account for every finding the regex
            // counter saw; a disagreement disables baseline suppression upstream.
            parseMismatch: parsedFindings.length !== findings,
        },
    };
}

/**
 * Confirms a generated tsconfig resolves every file discovered on disk. A
 * config that silently drops a discovered file would report a false green, so
 * an uncovered file is a tooling error naming the config and the misses.
 */
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
        // stdout alone, never the merged output: a Node deprecation notice on
        // stderr would otherwise be glued onto the JSON and fail the parse,
        // turning a healthy config into a whole-extension tooling error.
        const config = JSON.parse(resolved.stdout) as { files?: string[] };

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

export async function runTypeScriptPrograms(
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
        groups.map((group) => limit(() => runTypeScriptProgram(vueTscPath, group.configPath, projectRoot))),
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
                output: joinProgramOutputs(outputs),
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
            ? wrapText(
                  `The shared Administration type surface emitted ${surfaceFindings.length} diagnostic(s) outside ` +
                      `${basePath} (${[...new Set(surfaceFindings.map((finding) => finding.file))]
                          .slice(0, 3)
                          .join(', ')}${surfaceFindings.length > 3 ? ', …' : ''}). This is a type-surface failure, not ` +
                      'extension debt — it cannot be baselined. A global declaration in the extension may conflict ' +
                      'with the shipped surface; check the named file.',
              )
            : '';

    return {
        result: {
            status,
            findings: findings.length,
            newFindings: split.newFindings.length + surfaceFindings.length,
            baselinedFindings: split.baselinedFindings.length,
            staleBaseline: split.staleCount,
            newFindingRefs: [
                ...surfaceFindings,
                ...split.newFindings,
            ].map((finding) => ({ file: finding.file, code: finding.code })),
            // Surface findings are never baselineable, so they only ever appear
            // among the new ones above.
            baselinedFindingRefs: split.baselinedFindings.map((finding) => ({ file: finding.file, code: finding.code })),
            surfaceDiagnostics: surfaceFindings.length,
            output: [
                surfaceHeader,
                joinProgramOutputs(outputs),
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
