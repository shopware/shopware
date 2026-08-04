/**
 * @sw-package framework
 *
 * The check report's per-config skipped-target remediation (capped in concise
 * mode, complete under --verbose). Turns long native tool output into an
 * actionable shortlist of the configs that keep targets out of the check.
 */

import colors from 'picocolors';
import { describeToolGuidance } from './report-guidance';
import type { SkippedTarget } from './check-types';
import type { ExtensionToolingProject } from './shared';

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

        const guidance = describeToolGuidance(project, tool, {
            path: group[0].configPath,
            composes: false,
            detail: group[0].detail,
        });

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
