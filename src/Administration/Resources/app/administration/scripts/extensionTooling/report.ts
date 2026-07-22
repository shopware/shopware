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
import type { SetupExtensionToolingResult } from './setup';
import {
    SHIM_DIR_NAME,
    aggregateModeResolution,
    collectSkippedTargets,
    deriveExtensionState,
    projectHasBridge,
    projectHasOwnedConfig,
} from './shared';
import type { ExtensionToolingProject, ModeResolution, SkippedTarget } from './shared';

/**
 * Ownership class of a generated path, so dry-run and shim output can tell
 * apart the three lifecycles a developer must treat differently:
 * - `bridge`: git-ignored machine-specific files inside `.shopware-admin/` —
 *   never committed;
 * - `committable`: the plugin's own tsconfig/eslint/baseline — commit these;
 * - `host`: disposable root projections and IDE bootstraps the tool re-derives.
 */
type FileClass = 'bridge' | 'committable' | 'host';

function classifyFile(file: string): FileClass {
    if (file.includes(`/${SHIM_DIR_NAME}/`)) {
        return 'bridge';
    }

    const base = file.split('/').pop() ?? '';

    if (
        file.startsWith('custom/plugins/') &&
        (base === 'tsconfig.json' || base === 'eslint.config.mjs' || base === '.shopware-admin-baseline.json')
    ) {
        return 'committable';
    }

    return 'host';
}

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

export interface ToolGuidance {
    why: string;
    fix: string[];
}

const BRIDGE_TSCONFIG_LINE = '"extends": "./.shopware-admin/tsconfig.json"';
const BRIDGE_ESLINT_LINES = [
    "import shopware from './.shopware-admin/eslint.mjs';",
    'export default [ ...shopware /* , your rules */ ];',
];

function shimCommand(project: ExtensionToolingProject): string {
    return `composer admin:setup-extension-tooling -- --shim=${project.name}`;
}

/**
 * One `why:` and one `fix:` for a skipped tool — reason- and state-specific,
 * never suggesting a step the project's facts prove was already done.
 */
export function describeToolGuidance(
    project: ExtensionToolingProject,
    tool: 'TypeScript' | 'ESLint',
    resolution: ModeResolution,
): ToolGuidance | null {
    if (resolution.mode !== 'unmanaged') {
        return null;
    }

    const state = deriveExtensionState(project);

    if (state === 'vendor' || state === 'platform') {
        // Rendered as a per-extension note instead of per-tool fixes.
        return null;
    }

    const why = resolution.detail ?? 'the config does not compose the Shopware preset.';

    if (state === 'needs-bridge') {
        // The per-extension one-command block covers the fix; repeating it per
        // tool would print the same command twice.
        return { why, fix: [] };
    }

    if (resolution.reason === 'config-error') {
        return { why, fix: ['fix the config error, then re-run the check.'] };
    }

    if (tool === 'TypeScript') {
        switch (resolution.reason) {
            case 'files-override':
                return {
                    why,
                    fix: ['remove "files" from the plugin tsconfig — the bridge provides the type surface.'],
                };
            case 'not-extending':
                return { why, fix: [`add ${BRIDGE_TSCONFIG_LINE} to the plugin tsconfig.`] };
            default:
                return {
                    why,
                    fix: [`extend the bridge (${BRIDGE_TSCONFIG_LINE}) and remove own "files" / "types" overrides.`],
                };
        }
    }

    return {
        why,
        fix: [
            'compose the bridge in the config:',
            ...BRIDGE_ESLINT_LINES.map((line) => `    ${line}`),
        ],
    };
}

const MAX_SKIPPED_CONFIGS = 5;

/**
 * One `skipped:` block per distinct config so a partially covered multi-root
 * project names exactly the configs that keep targets out of the check — also
 * when the managed remainder failed, where the tool status alone says nothing
 * about the skipped targets.
 */
function renderSkippedTargetLines(
    project: ExtensionToolingProject,
    tool: 'TypeScript' | 'ESLint',
    entries: SkippedTarget[],
): string[] {
    const groups = new Map<string, SkippedTarget[]>();

    for (const entry of entries) {
        const group = groups.get(entry.configPath) ?? [];

        group.push(entry);
        groups.set(entry.configPath, group);
    }

    const sorted = [...groups.entries()].sort(([left], [right]) => left.localeCompare(right));
    const lines: string[] = [];

    for (const [
        configPath,
        group,
    ] of sorted.slice(0, MAX_SKIPPED_CONFIGS)) {
        const targetNote = group.length > 1 ? colors.dim(` (${group.length} targets)`) : '';

        lines.push(`      ${colors.yellow(`skipped: ${configPath}`)}${targetNote}`);

        const guidance = describeToolGuidance(project, tool, group[0].resolution);

        if (guidance) {
            lines.push(colors.dim(`        why: ${guidance.why}`));
            lines.push(...guidance.fix.map((line, index) => `        ${index === 0 ? 'fix: ' : '     '}${line}`));
        }
    }

    if (sorted.length > MAX_SKIPPED_CONFIGS) {
        lines.push(colors.dim(`      … and ${sorted.length - MAX_SKIPPED_CONFIGS} more skipped config(s)`));
    }

    return lines;
}

/**
 * The extension's single missing step for the setup report — empty when
 * nothing is missing.
 */
export function describeNextStep(project: ExtensionToolingProject): string[] {
    const state = deriveExtensionState(project);

    if (state === 'vendor') {
        return [
            'Read-only vendor extension — checked through host-owned configs; findings are',
            'non-fatal (pass --strict-vendor to fail on them).',
        ];
    }

    if (state === 'needs-bridge') {
        return [
            "It isn't checked with the Shopware preset yet. Bridge it with one command:",
            `    ${shimCommand(project)}`,
            'That generates a git-ignored .shopware-admin/ bridge plus small committed',
            'tsconfig/eslint that extend it (existing configs are never overwritten).',
        ];
    }

    if (state === 'bridge-unwired') {
        const lines = ['The .shopware-admin/ bridge exists — finish wiring it:'];

        for (const [
            tool,
            resolution,
        ] of [
            [
                'TypeScript',
                aggregateModeResolution(project, 'ts'),
            ],
            [
                'ESLint',
                aggregateModeResolution(project, 'eslint'),
            ],
        ] as Array<['TypeScript' | 'ESLint', ModeResolution]>) {
            const guidance = describeToolGuidance(project, tool, resolution);

            if (guidance) {
                lines.push(...guidance.fix.map((line) => `    ${line}`));
            }
        }

        return lines;
    }

    return [];
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

const DEFAULT_SUMMARY_TOP = 10;

/** The `count`-heaviest keys, ties broken alphabetically so the output is deterministic. */
function topBy<T>(items: T[], keyOf: (item: T) => string, top: number): Array<{ key: string; count: number }> {
    const counts = new Map<string, number>();

    for (const item of items) {
        const key = keyOf(item);

        counts.set(key, (counts.get(key) ?? 0) + 1);
    }

    return [...counts.entries()]
        .sort((left, right) => right[1] - left[1] || left[0].localeCompare(right[0]))
        .slice(0, top)
        .map(
            ([
                key,
                count,
            ]) => ({ key, count }),
        );
}

/** ` — N new · M baselined · K stale`, only for the parts that are non-zero. */
function baselineCountNote(run: ToolRunResult): string {
    const baselined = run.baselinedFindings ?? 0;
    const stale = run.staleBaseline ?? 0;

    if (baselined === 0 && stale === 0) {
        return '';
    }

    const parts = [
        `${run.newFindings ?? run.findings} new`,
        `${baselined} baselined`,
    ];

    if (stale > 0) {
        parts.push(`${stale} stale`);
    }

    return ` — ${parts.join(' · ')}`;
}

/**
 * Triage summary for one extension: each non-empty stream (runtime TypeScript,
 * spec TypeScript, ESLint errors, ESLint warnings) grouped by rule/code and by
 * file, top-N each. Turns an 11k-line native log into an actionable "what to fix
 * first" list without replacing the raw output (unless --summary-only).
 */
function renderFindingSummary(result: ExtensionCheckResult, top: number): string[] {
    const eslint = result.eslint.eslintFindings ?? [];
    const streams: Array<{
        label: string;
        findings: Array<{ file: string }>;
        kindOf: (finding: never) => string;
        kindLabel: string;
        run: ToolRunResult | null;
    }> = [
        {
            label: 'runtime TypeScript',
            findings: result.typescript.typeScriptFindings ?? [],
            kindOf: (finding: { code: string }) => finding.code,
            kindLabel: 'code',
            run: result.typescript,
        },
        {
            label: 'spec TypeScript',
            findings: result.typescriptSpecs.typeScriptFindings ?? [],
            kindOf: (finding: { code: string }) => finding.code,
            kindLabel: 'code',
            run: result.typescriptSpecs,
        },
        {
            label: 'ESLint errors',
            findings: eslint.filter((finding) => finding.severity === 'error'),
            kindOf: (finding: { rule: string }) => finding.rule || '(no rule)',
            kindLabel: 'rule',
            run: result.eslint,
        },
        {
            label: 'ESLint warnings',
            findings: eslint.filter((finding) => finding.severity === 'warning'),
            kindOf: (finding: { rule: string }) => finding.rule || '(no rule)',
            kindLabel: 'rule',
            run: null,
        },
    ];
    const active = streams.filter((stream) => stream.findings.length > 0);

    if (active.length === 0) {
        return [];
    }

    const lines = [`    ${colors.bold(`Summary — ${result.project.name}`)}`];

    for (const stream of active) {
        const note = stream.run ? baselineCountNote(stream.run) : '';

        lines.push(colors.dim(`      ${stream.label}: ${stream.findings.length} finding(s)${note}`));

        const format = (groups: Array<{ key: string; count: number }>): string =>
            groups.map((group) => `${group.key} ×${group.count}`).join(', ');

        lines.push(
            colors.dim(`        by ${stream.kindLabel}: ${format(topBy(stream.findings, stream.kindOf as never, top))}`),
        );
        lines.push(colors.dim(`        by file: ${format(topBy(stream.findings, (finding) => finding.file, top))}`));
    }

    return lines;
}

function renderExtension(result: ExtensionCheckResult, options: RenderOptions): string[] {
    const verbose = options.verbose === true;
    const location = result.project.vendor ? 'vendor' : result.project.basePath;
    const moduleCount = result.project.technicalNames.length;
    const moduleNote = moduleCount > 1 ? colors.dim(` (${moduleCount} modules)`) : '';
    const lines = [`\n  ${colors.bold(result.project.name)}${moduleNote}  ${colors.dim(location)}`];

    if (verbose) {
        for (const coverage of result.coverage) {
            lines.push(
                colors.dim(`    target ${coverage.target.technicalNames.join(', ')} · ${coverage.target.sourcePath}`),
                colors.dim(`      runtime: ${coverage.runtimeConfig}`),
                colors.dim(`      specs:   ${coverage.specConfig}`),
                colors.dim(`      eslint:  ${coverage.eslintConfig}`),
            );
        }
    }

    const tools: Array<{ label: string; tool: 'TypeScript' | 'ESLint'; run: ToolRunResult; resolution: ModeResolution }> = [
        { label: 'TypeScript', tool: 'TypeScript', run: result.typescript, resolution: result.tsResolution },
    ];

    // The spec program is a companion of the TypeScript run — only shown when it
    // actually ran (a plugin without specs, an unmanaged config, or a blocked
    // schema is already conveyed by the TypeScript line).
    if (
        [
            'passed',
            'failed',
            'tooling-error',
        ].includes(result.typescriptSpecs.status)
    ) {
        tools.push({
            label: 'TS (specs)',
            tool: 'TypeScript',
            run: result.typescriptSpecs,
            resolution: result.tsResolution,
        });
    }

    tools.push({ label: 'ESLint', tool: 'ESLint', run: result.eslint, resolution: result.eslintResolution });

    const state = deriveExtensionState(result.project);
    const skipped = result.skippedTargets ?? collectSkippedTargets(result.project);
    let anyUnmanaged = false;

    for (const { label, tool, run, resolution } of tools) {
        // The runtime TypeScript row already lists the skips the spec row shares.
        const toolSkips = label === 'TS (specs)' ? [] : skipped.filter((entry) => entry.tool === tool);

        lines.push(`    ${statusLine(label, run, resolution)}`);
        anyUnmanaged = anyUnmanaged || run.status === 'unmanaged' || toolSkips.length > 0;

        // A vacuous TypeScript pass is not a dead end: point at the one action
        // that turns it into real coverage instead of leaving green-with-asterisk.
        if (label === 'TypeScript' && run.status === 'no-files') {
            lines.push(
                colors.dim('      → rename a .js source to .ts (and add types) to enable type-checking, then re-run.'),
            );
        }

        // Rendered independently of the run status and of --summary-only: a
        // failed managed remainder must not swallow the skip remediation.
        lines.push(...renderSkippedTargetLines(result.project, tool, toolSkips));

        if (run.status === 'unmanaged') {
            if (verbose && resolution.probeOutput && resolution.probeOutput.trim() !== '') {
                lines.push(indent(resolution.probeOutput.trim(), '      '));
            }

            continue;
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
    }

    if (anyUnmanaged) {
        if (state === 'needs-bridge') {
            lines.push(...describeNextStep(result.project).map((step) => colors.dim(`      ${step}`)));
        } else if (state === 'vendor') {
            lines.push(...describeNextStep(result.project).map((step) => colors.dim(`      ${step}`)));
        } else if (state === 'platform') {
            lines.push(colors.dim('      platform bundle — its own config decides composition.'));
        }
    }

    if (options.showCommands === true) {
        for (const command of [
            result.commands.typescript,
            result.commands.typescriptSpecs,
            result.commands.eslint,
        ].flatMap((commands) => commands ?? [])) {
            if (command) {
                lines.push(colors.dim(`      $ ${command}`));
            }
        }
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

    const withFindings = result.results.filter(hasFindings).length;
    // Two different "skipped" notions, named apart: whole extensions where
    // both tools skipped vs. individual tool runs that did not happen.
    const extensionsSkipped = result.results.filter(
        (extension) => extension.typescript.status === 'unmanaged' && extension.eslint.status === 'unmanaged',
    ).length;
    const toolsSkipped = result.results.reduce(
        (sum, extension) =>
            sum +
            [
                extension.typescript.status,
                extension.eslint.status,
            ].filter((status) => status === 'unmanaged' || status === 'blocked').length,
        0,
    );
    const baselined = result.results.reduce(
        (sum, extension) =>
            sum +
            (extension.typescript.baselinedFindings ?? 0) +
            (extension.typescriptSpecs.baselinedFindings ?? 0) +
            (extension.eslint.baselinedFindings ?? 0),
        0,
    );
    // Tool runs skipped on writable (non-vendor) extensions: the ones a CI gate
    // must not read as green. Vendor skips are expected and covered separately.
    const writableSkipped = result.results
        .filter((extension) => !extension.project.vendor)
        .reduce(
            (sum, extension) =>
                sum +
                [
                    extension.typescript,
                    extension.typescriptSpecs,
                    extension.eslint,
                ].filter((run) => run.status === 'unmanaged' || run.status === 'blocked').length,
            0,
        );

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

    // Three summary colors: green (complete success), yellow (success with
    // writable skips), red (failure).
    const succeeded = result.exitCode === 0;
    const paint = succeeded ? (writableSkipped > 0 ? colors.yellow : colors.green) : colors.red;
    const glyph = succeeded ? (writableSkipped > 0 ? '⚠' : '✔') : '✖';
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

interface SetupRenderOptions {
    explain?: boolean;
    checkOnly?: boolean;
    shim?: string;
    /**
     * Shown on plain (flag-less) runs: composer swallows options placed before
     * "--", so this footer is both flag discovery and the safety net for a
     * swallowed flag that silently turned into a default run.
     */
    showFlagHint?: boolean;
}

/**
 * "3 generated, 1 updated" answers nothing — up to 8 changes are listed by
 * path so "what did this do to my repo" is answerable from the output; larger
 * batches keep the count and defer the list to --explain.
 */
function describeFileChanges(result: SetupExtensionToolingResult): string[] {
    const changes = [
        ...result.writes
            .filter((write) => write.state === 'created')
            .map((write) => ({ verb: 'generated', file: write.file })),
        ...result.writes
            .filter((write) => write.state === 'updated')
            .map((write) => ({ verb: 'updated', file: write.file })),
        ...result.staleFiles.map((file) => ({ verb: 'removed', file })),
    ];

    if (changes.length === 0) {
        return ['Configs up to date'];
    }

    if (changes.length > 8) {
        const created = result.writes.filter((write) => write.state === 'created').length;
        const updated = result.writes.filter((write) => write.state === 'updated').length;
        const removed = result.staleFiles.length;

        return [
            [
                `${created} generated`,
                `${updated} updated`,
                ...(removed > 0 ? [`${removed} removed`] : []),
            ].join(', ') + ' (list: --explain)',
        ];
    }

    return changes.map(({ verb, file }) => `${verb}: ${file}`);
}

export function renderSetupReport(result: SetupExtensionToolingResult, options: SetupRenderOptions = {}): string {
    const { projects } = result.manifest;
    const stateOf = new Map(
        projects.map((project) => [
            project.name,
            deriveExtensionState(project),
        ]),
    );
    const platform = projects.filter((project) => stateOf.get(project.name) === 'platform');
    const ownExtensions = projects.filter((project) => stateOf.get(project.name) !== 'platform');

    // The empty state must never read as a green "up to date": the most likely
    // cause is a stale var/plugins.json after installing or activating a plugin.
    if (ownExtensions.length === 0) {
        const lines = [
            colors.bold('Administration extension tooling — no extensions found'),
            '',
            '  No installed extension with Administration sources was discovered.',
            '  Discovery reads var/plugins.json. If you just installed or activated a',
            '  plugin, refresh it:',
            '',
            '      bin/console bundle:dump',
        ];

        if (platform.length > 0) {
            lines.push(
                '',
                colors.dim(
                    `  (Platform bundles like ${platform.map((project) => project.name).join(', ')} are always ` +
                        'covered and not listed here.)',
                ),
            );
        }

        for (const warning of result.warnings) {
            lines.push(colors.yellow(`  ⚠ ${warning}`));
        }

        return lines.join('\n');
    }

    const lines = [colors.bold(`Administration extension tooling — ${ownExtensions.length} extension(s)`)];

    if (options.shim) {
        const shim = options.shim;
        const justBridged = projects.filter(
            (project) => (project.name === shim || project.technicalNames.includes(shim)) && projectHasBridge(project),
        );

        for (const project of justBridged) {
            if (stateOf.get(project.name) === 'bridged') {
                lines.push(
                    '',
                    colors.green(`✔ Bridged ${project.name}. Its tsconfig / eslint.config.mjs now extend the generated`),
                    colors.green('  .shopware-admin/ bridge (git-ignored). Commit them, edit freely — keep the "extends".'),
                );
            } else {
                lines.push(
                    '',
                    colors.green(`✔ Bridge created for ${project.name} at .shopware-admin/ — one step left:`),
                    ...describeNextStep(project)
                        .slice(1)
                        .map((line) => `  ${line}`),
                );
            }
        }

        const created = result.writes.filter((write) => write.state === 'created');
        const bridgeCreated = created.filter((write) => classifyFile(write.file) === 'bridge').length;
        const committableCreated = created.filter((write) => classifyFile(write.file) === 'committable').length;

        if (bridgeCreated > 0 || committableCreated > 0) {
            lines.push(
                colors.dim(
                    `  ${bridgeCreated} git-ignored bridge file(s) in .shopware-admin/ (never commit) · ` +
                        `${committableCreated} committable plugin config(s) (commit these)`,
                ),
            );
        }
    }

    const ready = projects.filter((project) => stateOf.get(project.name) === 'ready');
    const bridged = projects.filter((project) => stateOf.get(project.name) === 'bridged');
    const unwired = projects.filter((project) => stateOf.get(project.name) === 'bridge-unwired');
    const needsBridge = projects.filter((project) => {
        const state = stateOf.get(project.name);

        if (state === 'needs-bridge') {
            return true;
        }

        return state === 'vendor' && !projectHasBridge(project) && projectHasOwnedConfig(project);
    });
    const unverifiedBridged = bridged.some((project) =>
        project.targets.some(
            (target) =>
                (target.tsconfig !== null && !target.ts.verified) ||
                (target.eslintConfig !== null && !target.eslint.verified),
        ),
    );

    lines.push('');

    if (ready.length > 0) {
        lines.push(`  ${colors.green('✔ ready')}    ${ready.map((project) => project.name).join(', ')}`);
    }

    if (bridged.length > 0) {
        lines.push(
            `  ${colors.cyan('● bridged')}  ${bridged.map((project) => project.name).join(', ')}  ` +
                colors.dim(
                    `(own configs compose the Shopware preset${
                        unverifiedBridged ? ' — unverified, run composer admin:check-extensions' : ''
                    })`,
                ),
        );
    }

    if (unwired.length > 0) {
        lines.push(
            `  ${colors.yellow('⚠ bridge unwired')}  ${unwired.map((project) => project.name).join(', ')}  ` +
                colors.dim("(bridge exists — own config doesn't compose it yet)"),
        );
    }

    if (needsBridge.length > 0) {
        lines.push(
            `  ${colors.yellow('● needs bridge')}   ${needsBridge.map((project) => project.name).join(', ')}  ` +
                colors.dim('(ships own config — not composed yet; bridge it to check with the preset)'),
        );
    }

    if (platform.length > 0) {
        lines.push(
            colors.dim(
                `  platform   ${platform.map((project) => project.name).join(', ')}  ` +
                    '(always checked with core tooling)',
            ),
        );
    }

    lines.push('', ...describeFileChanges(result).map((line) => `  ${colors.dim(line)}`));

    if (result.manifest.rootConfigs.tsconfig === 'conflict') {
        lines.push(colors.yellow('  ⚠ root tsconfig.json is user-owned — run with --explain to integrate'));
    }

    if (result.manifest.rootConfigs.eslintConfig === 'conflict') {
        lines.push(colors.yellow('  ⚠ root eslint.config.mjs is user-owned — run with --explain to integrate'));
    }

    for (const warning of result.warnings) {
        lines.push(colors.yellow(`  ⚠ ${warning}`));
    }

    if (options.checkOnly && result.changed) {
        lines.push('', colors.red('  Setup is stale — re-run `composer admin:setup-extension-tooling`:'));

        const classNote: Record<FileClass, string> = {
            bridge: colors.dim(' [git-ignored bridge]'),
            committable: colors.cyan(' [commit this]'),
            host: '',
        };
        const pending = result.writes.filter((entry) => entry.state === 'created' || entry.state === 'updated');

        for (const write of pending) {
            lines.push(
                colors.dim(`    would ${write.state === 'created' ? 'create' : 'update'}: ${write.file}`) +
                    classNote[classifyFile(write.file)],
            );
        }

        for (const staleFile of result.staleFiles) {
            lines.push(colors.dim(`    would delete: ${staleFile}`) + classNote[classifyFile(staleFile)]);
        }

        const bridgeCount = pending.filter((write) => classifyFile(write.file) === 'bridge').length;
        const committableCount = pending.filter((write) => classifyFile(write.file) === 'committable').length;

        if (bridgeCount > 0 || committableCount > 0) {
            lines.push(
                colors.dim(
                    `    (${bridgeCount} git-ignored bridge file(s), ${committableCount} committable plugin file(s), ` +
                        `${pending.length - bridgeCount - committableCount} host projection(s))`,
                ),
            );
        }
    }

    const needsInclusion = projects.filter((project) =>
        [
            'needs-bridge',
            'bridge-unwired',
        ].includes(stateOf.get(project.name) as string),
    );

    if (needsInclusion.length > 0) {
        lines.push('', colors.bold('  Next steps'));

        for (const project of needsInclusion.slice(0, 5)) {
            lines.push(`  ${colors.bold(project.name)}`, ...describeNextStep(project).map((step) => `    ${step}`));
        }

        if (needsInclusion.length > 5) {
            lines.push(colors.dim(`  … and ${needsInclusion.length - 5} more — run with --explain`));
        }
    }

    if (!options.explain) {
        lines.push('', colors.dim('  IDE setup: run with --explain for VS Code / Zed / PhpStorm config'));
    }

    if (options.showFlagHint) {
        lines.push(
            colors.dim(
                '  Options need "--": composer admin:setup-extension-tooling -- --check | --explain | --shim=<name> | --help',
            ),
        );
    }

    if (options.explain) {
        lines.push(
            '',
            colors.bold('  Details'),
            `    Administration root: ${result.manifest.adminRoot}`,
            `    Discovered from: ${result.discoverySource.path}${
                result.discoverySource.updatedAt ? ` (updated ${result.discoverySource.updatedAt})` : ''
            }`,
            `    Entity schema: ${
                result.manifest.entitySchemaAvailable
                    ? 'available'
                    : 'stub (run composer admin:generate-entity-schema-types)'
            }`,
        );

        // Setup never runs live probes (the check does), so a verified verdict
        // here was carried in from the probe cache and an unverified one is fresh
        // static analysis — surface which, so a reader knows how much to trust it.
        const verdictSource = (resolution: ModeResolution, hasOwnConfig: boolean): string =>
            !hasOwnConfig ? '' : resolution.verified ? ' (cached-live)' : ' (static)';

        for (const project of projects) {
            const moduleCount = project.technicalNames.length;
            const ts = aggregateModeResolution(project, 'ts');
            const eslint = aggregateModeResolution(project, 'eslint');

            lines.push(
                `    - ${project.name} · ${moduleCount === 1 ? '1 module' : `${moduleCount} modules`} · ` +
                    `ts:${ts.mode} · eslint:${eslint.mode}`,
            );

            for (const target of project.targets) {
                lines.push(
                    `        ${target.technicalNames.join(', ')} · ${target.sourcePath}`,
                    `          runtime: ${target.ts.mode}${verdictSource(target.ts, target.tsconfig !== null)} → ${target.checkTsconfig}`,
                    `          specs:   ${target.specTsconfig}`,
                    `          eslint:  ${target.eslint.mode}${verdictSource(target.eslint, target.eslintConfig !== null)} → ${target.eslintConfig ?? 'generated root config'}`,
                );
            }
        }

        lines.push(
            '',
            '    Own path aliases: declare them in tsconfig.aliases.json next to the plugin config,',
            '    e.g. { "MyPlugin/*": ["src/*"] } — the generated .shopware-admin/ bridge merges them',
            '    with the preset paths (tsconfig "paths" cannot be extended additively).',
            '',
            '    Managed files:',
            ...result.writes.map((write) => `      ${write.state}: ${write.file}`),
            ...result.staleFiles.map((file) => `      removed: ${file}`),
        );

        for (const instruction of result.instructions) {
            lines.push('', ...instruction.split('\n').map((line) => `    ${line}`));
        }
    }

    return lines.join('\n');
}
