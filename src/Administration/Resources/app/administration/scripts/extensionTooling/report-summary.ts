/**
 * @sw-package framework
 *
 * The check report's two data-reduction blocks: the per-config skipped-target
 * remediation (capped in concise mode, complete under --verbose) and the
 * opt-in triage summary that groups an extension's findings by rule/code and
 * by file. Both turn long native tool output into an actionable shortlist.
 */

import colors from 'picocolors';
import type { ExtensionCheckResult, ToolRunResult } from './check';
import { describeToolGuidance } from './report-guidance';
import type { ExtensionToolingProject, SkippedTarget } from './shared';

const MAX_SKIPPED_CONFIGS = 5;

/**
 * One `skipped:` block per distinct config so a partially covered multi-root
 * project names exactly the configs that keep targets out of the check — also
 * when the managed remainder failed, where the tool status alone says nothing
 * about the skipped targets. Concise output caps the list; --verbose renders
 * every config so no skipped target's remediation is hidden.
 */
export function renderSkippedTargetLines(
    project: ExtensionToolingProject,
    tool: 'TypeScript' | 'ESLint',
    entries: SkippedTarget[],
    verbose: boolean,
): string[] {
    const groups = new Map<string, SkippedTarget[]>();

    for (const entry of entries) {
        const group = groups.get(entry.configPath) ?? [];

        group.push(entry);
        groups.set(entry.configPath, group);
    }

    const sorted = [...groups.entries()].sort(([left], [right]) => left.localeCompare(right));
    const visible = verbose ? sorted : sorted.slice(0, MAX_SKIPPED_CONFIGS);
    const lines: string[] = [];

    for (const [
        configPath,
        group,
    ] of visible) {
        const targetNote = group.length > 1 ? colors.dim(` (${group.length} targets)`) : '';

        lines.push(`      ${colors.yellow(`skipped: ${configPath}`)}${targetNote}`);

        const guidance = describeToolGuidance(project, tool, group[0].resolution);

        if (guidance) {
            lines.push(colors.dim(`        why: ${guidance.why}`));
            lines.push(...guidance.fix.map((line, index) => `        ${index === 0 ? 'fix: ' : '     '}${line}`));
        }
    }

    if (sorted.length > visible.length) {
        lines.push(
            colors.dim(
                `      … and ${sorted.length - visible.length} more skipped config(s) — run with --verbose to list them`,
            ),
        );
    }

    return lines;
}

export const DEFAULT_SUMMARY_TOP = 10;

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
export function renderFindingSummary(result: ExtensionCheckResult, top: number): string[] {
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
