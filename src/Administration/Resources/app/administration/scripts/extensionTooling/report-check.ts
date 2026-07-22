/**
 * @sw-package framework
 *
 * Human-readable rendering for `admin:check-extensions`. Pure: takes the silent
 * `CheckExtensionsResult` and returns the full report string. Color is applied
 * via picocolors, which decides support once at import time (FORCE_COLOR, a
 * TTY, or env.CI turn it on; NO_COLOR wins) — CI logs are colored, so the
 * report specs strip ANSI before semantic assertions and
 * report.spec/color.spec.ts covers the colored path. Per-extension technical
 * names are summarized to a count instead of dumping every bundle.
 */

import colors from 'picocolors';
import type { CheckExtensionsResult, ExtensionCheckResult, ToolRunResult } from './check';
import { describeNextStep } from './report-guidance';
import { DEFAULT_SUMMARY_TOP, renderFindingSummary, renderSkippedTargetLines } from './report-summary';
import { collectSkippedTargets, deriveExtensionState } from './shared';
import type { ModeResolution, SkippedTarget } from './shared';

interface RenderOptions {
    verbose?: boolean;
    /** Print the underlying tool invocation per extension (reproduction escape hatch). */
    showCommands?: boolean;
    /** Whether the run enforced --fail-on-skipped (shapes the skip warning wording). */
    failOnSkipped?: boolean;
    /** Whether --fix was applied this run (adds the fix → baseline handoff hint). */
    fix?: boolean;
    /** Append a triage summary grouping findings by rule/code and by file. */
    summary?: boolean;
    /** Suppress the raw per-finding output and show only the triage summary. */
    summaryOnly?: boolean;
    /** How many top rules/codes and files to list per stream (default 10). */
    summaryTop?: number;
}

function seconds(run: ToolRunResult): string {
    return `${(run.durationMs / 1000).toFixed(1)}s`;
}

function skipReason(tool: string, resolution: ModeResolution): string {
    const configNoun = tool === 'TypeScript' ? 'tsconfig' : 'config';

    if (resolution.reason === 'config-error') {
        return `own ${configNoun} fails to resolve`;
    }

    return tool === 'TypeScript'
        ? 'own tsconfig does not reach the Shopware type surface'
        : 'own config does not compose the Shopware factory';
}

/**
 * Baseline annotations for a tool run: the new findings are pointed at by
 * identity so they can be found among the verbatim baselined ones, and a stale
 * count nudges toward a re-baseline. Empty unless a baseline is in play.
 */
function baselineNotes(run: ToolRunResult): string[] {
    const notes: string[] = [];
    const refs = run.newFindingRefs ?? [];

    if (run.status === 'failed' && (run.baselinedFindings ?? 0) > 0 && refs.length > 0) {
        const shown = refs.slice(0, 10).map((ref) => `${ref.file} · ${ref.code}`);
        const overflow = refs.length > shown.length ? `, … (+${refs.length - shown.length})` : '';

        notes.push(colors.dim(`      new (not baselined): ${shown.join(', ')}${overflow}`));
    }

    if ((run.staleBaseline ?? 0) > 0) {
        const count = run.staleBaseline ?? 0;

        notes.push(
            colors.dim(
                `      ⚠ ${count} baseline entr${count === 1 ? 'y' : 'ies'} no longer match — ` +
                    'prune with -- --update-baseline',
            ),
        );
    }

    return notes;
}

function statusLine(tool: string, run: ToolRunResult, resolution: ModeResolution): string {
    const label = tool.padEnd(11, ' ');
    const meta = colors.dim(`${resolution.mode} · ${seconds(run)}`);
    const baselined = run.baselinedFindings ?? 0;

    switch (run.status) {
        case 'passed': {
            const note = baselined > 0 ? ` ${colors.dim(`(${baselined} baselined)`)}` : '';

            return `${label}${colors.green('✔ passed')}${note}       ${meta}`;
        }
        case 'failed': {
            const newCount = run.newFindings ?? run.findings;
            const text =
                baselined > 0
                    ? `✖ ${newCount || 'some'} new · ${baselined} baselined`
                    : `✖ ${newCount || 'some'} finding(s)`;

            return `${label}${colors.red(text)}  ${meta}`;
        }
        case 'unmanaged':
            return `${label}${colors.yellow('⊘ skipped')} — ${colors.dim(skipReason(tool, resolution))}`;
        case 'blocked':
            return `${label}${colors.yellow('⊘ blocked')}     ${colors.dim('(entity schema missing)')}`;
        case 'no-files':
            // An honest empty pass: nothing was checked, say so instead of a
            // bare green that reads as "my code type-checks".
            return tool === 'TypeScript'
                ? `${label}${colors.green('✔ passed')} ${colors.dim('(0 TypeScript files — .js is not type-checked)')}`
                : `${label}${colors.dim('· no lintable files')}`;
        default:
            return `${label}${colors.red('✖ TOOLING ERROR')}  ${colors.dim(seconds(run))}`;
    }
}

function indent(text: string, prefix: string): string {
    return text
        .split('\n')
        .map((line) => `${prefix}${line}`)
        .join('\n');
}

const SPEC_RUN_STATUSES = [
    'passed',
    'failed',
    'tooling-error',
];

interface ToolRow {
    label: string;
    tool: 'TypeScript' | 'ESLint';
    run: ToolRunResult;
    resolution: ModeResolution;
}

/** The per-extension status rows, in print order: TypeScript, its spec companion (only when it ran), ESLint. */
function toolRows(result: ExtensionCheckResult): ToolRow[] {
    const rows: ToolRow[] = [
        { label: 'TypeScript', tool: 'TypeScript', run: result.typescript, resolution: result.tsResolution },
    ];

    // The spec program is a companion of the TypeScript run — only shown when it
    // actually ran (a plugin without specs, an unmanaged config, or a blocked
    // schema is already conveyed by the TypeScript line).
    if (SPEC_RUN_STATUSES.includes(result.typescriptSpecs.status)) {
        rows.push({
            label: 'TS (specs)',
            tool: 'TypeScript',
            run: result.typescriptSpecs,
            resolution: result.tsResolution,
        });
    }

    rows.push({ label: 'ESLint', tool: 'ESLint', run: result.eslint, resolution: result.eslintResolution });

    return rows;
}

function renderCoverageLines(result: ExtensionCheckResult): string[] {
    const lines: string[] = [];

    for (const coverage of result.coverage) {
        lines.push(
            colors.dim(`    target ${coverage.target.technicalNames.join(', ')} · ${coverage.target.sourcePath}`),
            colors.dim(`      runtime: ${coverage.runtimeConfig}`),
            colors.dim(`      specs:   ${coverage.specConfig}`),
            colors.dim(`      eslint:  ${coverage.eslintConfig}`),
        );
    }

    return lines;
}

/**
 * One tool's status row plus everything hanging off it (skip remediation,
 * baseline notes, raw output). `unmanaged` reports whether this row leaves the
 * extension partially unchecked, which drives the trailing per-extension note.
 */
function renderToolRow(
    result: ExtensionCheckResult,
    row: ToolRow,
    skipped: SkippedTarget[],
    options: RenderOptions,
    verbose: boolean,
): { lines: string[]; unmanaged: boolean } {
    const { label, tool, run, resolution } = row;
    // The runtime TypeScript row already lists the skips the spec row shares.
    const toolSkips = label === 'TS (specs)' ? [] : skipped.filter((entry) => entry.tool === tool);
    const unmanaged = run.status === 'unmanaged' || toolSkips.length > 0;
    const lines = [`    ${statusLine(label, run, resolution)}`];

    // A vacuous TypeScript pass is not a dead end: point at the one action
    // that turns it into real coverage instead of leaving green-with-asterisk.
    if (label === 'TypeScript' && run.status === 'no-files') {
        lines.push(colors.dim('      → rename a .js source to .ts (and add types) to enable type-checking, then re-run.'));
    }

    // Rendered independently of the run status and of --summary-only: a
    // failed managed remainder must not swallow the skip remediation.
    lines.push(...renderSkippedTargetLines(result.project, tool, toolSkips, verbose));

    if (run.status === 'unmanaged') {
        if (verbose && resolution.probeOutput && resolution.probeOutput.trim() !== '') {
            lines.push(indent(resolution.probeOutput.trim(), '      '));
        }

        return { lines, unmanaged };
    }

    lines.push(...baselineNotes(run));

    // --summary-only replaces the raw per-finding dump with the grouped
    // summary appended below; everything else still prints it.
    const showOutput =
        !options.summaryOnly &&
        (run.status === 'failed' || run.status === 'tooling-error' || (verbose && run.status !== 'no-files'));

    if (showOutput && run.output.trim() !== '') {
        lines.push(indent(run.output.trim(), '      '));
    }

    return { lines, unmanaged };
}

/** The trailing note for an extension left partially unchecked — its remaining setup step, or why it can't have one. */
function renderUnmanagedNote(result: ExtensionCheckResult): string[] {
    const state = deriveExtensionState(result.project);

    if (state === 'needs-bridge' || state === 'vendor') {
        return describeNextStep(result.project).map((step) => colors.dim(`      ${step}`));
    }

    if (state === 'platform') {
        return [colors.dim('      platform bundle — its own config decides composition.')];
    }

    return [];
}

function renderReproductionCommands(result: ExtensionCheckResult): string[] {
    return [
        result.commands.typescript,
        result.commands.typescriptSpecs,
        result.commands.eslint,
    ]
        .flatMap((commands) => commands ?? [])
        .filter((command) => command)
        .map((command) => colors.dim(`      $ ${command}`));
}

function renderExtension(result: ExtensionCheckResult, options: RenderOptions): string[] {
    const verbose = options.verbose === true;
    const location = result.project.vendor ? 'vendor' : result.project.basePath;
    const moduleCount = result.project.technicalNames.length;
    const moduleNote = moduleCount > 1 ? colors.dim(` (${moduleCount} modules)`) : '';
    const lines = [`\n  ${colors.bold(result.project.name)}${moduleNote}  ${colors.dim(location)}`];

    if (verbose) {
        lines.push(...renderCoverageLines(result));
    }

    const skipped = result.skippedTargets ?? collectSkippedTargets(result.project);
    let anyUnmanaged = false;

    for (const row of toolRows(result)) {
        const rendered = renderToolRow(result, row, skipped, options, verbose);

        lines.push(...rendered.lines);
        anyUnmanaged = anyUnmanaged || rendered.unmanaged;
    }

    if (anyUnmanaged) {
        lines.push(...renderUnmanagedNote(result));
    }

    if (options.showCommands === true) {
        lines.push(...renderReproductionCommands(result));
    }

    if (options.summary || options.summaryOnly) {
        lines.push(...renderFindingSummary(result, options.summaryTop ?? DEFAULT_SUMMARY_TOP));
    }

    return lines;
}

function summaryCell(run: ToolRunResult, tool: string): string {
    switch (run.status) {
        case 'passed':
            return (run.baselinedFindings ?? 0) > 0 ? `${run.baselinedFindings} baselined` : 'passed';
        case 'failed':
            return `${(run.newFindings ?? run.findings) || 'some'} finding(s)`;
        case 'unmanaged':
            return 'skipped';
        case 'blocked':
            return 'blocked';
        case 'no-files':
            return tool === 'TypeScript' ? 'passed*' : 'no files';
        default:
            return 'tool error';
    }
}

function renderSummary(results: ExtensionCheckResult[]): string[] {
    if (results.length === 0) {
        return [];
    }

    const headers = [
        'extension',
        'ts',
        'specs',
        'eslint',
    ];
    // Extensions without specs (or with an unmanaged/blocked TS run) get a dash
    // rather than a misleading "passed" in the specs column.
    const specCell = (run: ToolRunResult): string =>
        [
            'no-files',
            'unmanaged',
            'blocked',
        ].includes(run.status)
            ? '—'
            : summaryCell(run, 'TypeScript');
    const rows = results.map((result) => {
        const skipped = result.skippedTargets ?? collectSkippedTargets(result.project);
        // Partial skips get an explicit suffix — a plain "passed"/"N finding(s)"
        // cell would read as full coverage.
        const withSkipNote = (tool: 'TypeScript' | 'ESLint', cell: string): string => {
            const count = skipped.filter((entry) => entry.tool === tool).length;

            return count > 0 && cell !== 'skipped' && cell !== 'blocked' ? `${cell} +${count} skipped` : cell;
        };

        return [
            result.project.name,
            withSkipNote('TypeScript', summaryCell(result.typescript, 'TypeScript')),
            specCell(result.typescriptSpecs),
            withSkipNote('ESLint', summaryCell(result.eslint, 'ESLint')),
        ];
    });
    const widths = headers.map((header, column) => Math.max(header.length, ...rows.map((row) => row[column].length)));
    const format = (cells: string[]): string =>
        `  ${cells.map((cell, column) => cell.padEnd(widths[column], ' ')).join('   ')}`;
    const separator = `  ${colors.dim('─'.repeat(widths.reduce((sum, width) => sum + width + 3, 0)))}`;
    const hasVacuousPass = results.some((result) => result.typescript.status === 'no-files');

    return [
        '',
        separator,
        colors.dim(format(headers)),
        ...rows.map((row) => format(row)),
        ...(hasVacuousPass ? [colors.dim('  * no TypeScript files — .js is not type-checked')] : []),
    ];
}

function hasFindings(result: ExtensionCheckResult): boolean {
    return [
        result.typescript.status,
        result.typescriptSpecs.status,
        result.eslint.status,
    ].some((status) => status === 'failed' || status === 'tooling-error');
}

interface CheckStats {
    withFindings: number;
    /** Extensions where both tools skipped — a whole extension left unchecked. */
    extensionsSkipped: number;
    /** Individual tool runs that did not happen (unmanaged or blocked), across all extensions. */
    toolsSkipped: number;
    baselined: number;
    /** Tool runs skipped on writable (non-vendor) extensions — the ones a CI gate must not read as green. */
    writableSkipped: number;
}

function countSkippedRuns(runs: ToolRunResult[]): number {
    return runs.filter((run) => run.status === 'unmanaged' || run.status === 'blocked').length;
}

function summarizeCheck(results: ExtensionCheckResult[]): CheckStats {
    return {
        withFindings: results.filter(hasFindings).length,
        extensionsSkipped: results.filter(
            (extension) => extension.typescript.status === 'unmanaged' && extension.eslint.status === 'unmanaged',
        ).length,
        toolsSkipped: results.reduce(
            (sum, extension) =>
                sum +
                countSkippedRuns([
                    extension.typescript,
                    extension.eslint,
                ]),
            0,
        ),
        baselined: results.reduce(
            (sum, extension) =>
                sum +
                (extension.typescript.baselinedFindings ?? 0) +
                (extension.typescriptSpecs.baselinedFindings ?? 0) +
                (extension.eslint.baselinedFindings ?? 0),
            0,
        ),
        writableSkipped: results
            .filter((extension) => !extension.project.vendor)
            .reduce(
                (sum, extension) =>
                    sum +
                    countSkippedRuns([
                        extension.typescript,
                        extension.typescriptSpecs,
                        extension.eslint,
                    ]),
                0,
            ),
    };
}

/**
 * Three verdict styles for the final line: green (complete success), yellow
 * (success with writable skips CI should not read as green), red (failure).
 */
function verdictStyle(succeeded: boolean, writableSkipped: number): { paint: (text: string) => string; glyph: string } {
    if (!succeeded) {
        return { paint: colors.red, glyph: '✖' };
    }

    if (writableSkipped > 0) {
        return { paint: colors.yellow, glyph: '⚠' };
    }

    return { paint: colors.green, glyph: '✔' };
}

export function renderCheckReport(result: CheckExtensionsResult, options: RenderOptions = {}): string {
    const lines = [colors.bold(`Administration extension check — ${result.results.length} extension(s)`)];

    // Cause before consequence: a fatal explains every blocked line below it.
    for (const diagnostic of result.fatalDiagnostics) {
        lines.push(colors.red(`\nError: ${diagnostic}`));
    }

    for (const extension of result.results) {
        lines.push(...renderExtension(extension, options));
    }

    for (const warning of result.warnings) {
        lines.push(colors.yellow(`\nWarning: ${warning}`));
    }

    if (result.baselineUpdates.length > 0) {
        lines.push('', colors.bold('  Baseline updated'));

        for (const update of result.baselineUpdates) {
            lines.push(colors.dim(`    ${update}`));
        }
    }

    lines.push(...renderSummary(result.results));

    const { withFindings, extensionsSkipped, toolsSkipped, baselined, writableSkipped } = summarizeCheck(result.results);

    if (writableSkipped > 0) {
        const noun = `${writableSkipped} tool run${writableSkipped === 1 ? '' : 's'} on writable extension(s)`;

        lines.push(
            colors.yellow(
                options.failOnSkipped
                    ? `\n⚠ ${noun} were skipped and NOT checked — failing because --fail-on-skipped is set.`
                    : `\n⚠ ${noun} were skipped and NOT checked. Pass --fail-on-skipped to fail CI on this.`,
            ),
        );
    }

    const { paint, glyph } = verdictStyle(result.exitCode === 0, writableSkipped);
    const toolsSkippedNote = toolsSkipped > 0 ? ` (${toolsSkipped} tool${toolsSkipped === 1 ? '' : 's'} skipped)` : '';
    const baselinedNote = baselined > 0 ? ` · ${baselined} baselined` : '';

    lines.push(
        '',
        paint(
            `${glyph} ${result.results.length} checked${toolsSkippedNote} · ${withFindings} with findings${baselinedNote} · ` +
                `${extensionsSkipped} extension${extensionsSkipped === 1 ? '' : 's'} skipped · exit ${result.exitCode}`,
        ),
    );

    // The natural "auto-fix what you can, baseline the rest" flow is two steps
    // (--fix and --update-baseline are mutually exclusive). After a --fix run
    // that still has findings, name the exact follow-up command.
    if (options.fix && withFindings > 0) {
        lines.push(
            '',
            colors.dim(
                '  --fix applied the auto-fixable findings (incl. Shopware sw-* → mt-* deprecation codemods). ' +
                    'Accept the findings that remain as a baseline:',
            ),
            colors.dim('    composer admin:check-extensions -- --update-baseline'),
        );
    }

    return lines.join('\n');
}
