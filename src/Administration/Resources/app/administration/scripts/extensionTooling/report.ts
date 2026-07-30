/**
 * @sw-package framework
 *
 * The report and the exit-code contract. The most-read output of the whole
 * system is an error message, so both are specified here rather than improvised
 * at the call site.
 */

import type { CheckReport, Finding, RootReport, ToolRun } from './shared';

export const EXIT_OK = 0;
export const EXIT_FINDINGS = 1;
export const EXIT_USAGE = 2;
export const EXIT_TOOL_ERROR = 3;

const TOOL_LABEL: Record<ToolRun['tool'], string> = {
    types: 'types',
    lint: 'lint',
};

const TOOL_ACTION: Record<ToolRun['tool'], string> = {
    types: 'type-checked',
    lint: 'linted',
};

function countBySeverity(findings: Finding[], severity: Finding['severity']): number {
    return findings.filter((finding) => finding.severity === severity).length;
}

/**
 * A run that checked nothing is a tool error, never a clean result: with an
 * explicit `--config` ESLint resolves its base path from the process cwd, so the
 * failure mode of getting that wrong is a green run over zero files.
 */
export function zeroFileErrors(report: CheckReport): string[] {
    const errors: string[] = [];

    for (const rootReport of report.roots) {
        for (const run of rootReport.runs) {
            if (run.filesChecked === 0 && run.errors.length === 0) {
                errors.push(
                    `Checked 0 files for ${rootReport.root.bundleName} (${TOOL_LABEL[run.tool]}) — this is a tool error, not a clean result.`,
                );
            }
        }
    }

    return errors;
}

export function exitCodeFor(report: CheckReport): number {
    if (report.errors.length > 0) {
        return EXIT_TOOL_ERROR;
    }

    if (report.roots.length === 0) {
        return EXIT_TOOL_ERROR;
    }

    if (zeroFileErrors(report).length > 0) {
        return EXIT_TOOL_ERROR;
    }

    if (report.roots.some((rootReport) => rootReport.runs.some((run) => run.errors.length > 0))) {
        return EXIT_TOOL_ERROR;
    }

    const hasErrors = report.roots.some((rootReport) =>
        rootReport.runs.some((run) => countBySeverity(run.findings, 'error') > 0),
    );

    return hasErrors ? EXIT_FINDINGS : EXIT_OK;
}

function renderFinding(finding: Finding): string {
    const location = finding.file === null ? '<program>' : `${finding.file}:${finding.line ?? 0}:${finding.column ?? 0}`;
    const rule = finding.rule === null ? '' : `  ${finding.rule}`;

    return `    ${location}  ${finding.severity}${rule}  ${finding.message}`;
}

function renderRun(run: ToolRun): string[] {
    const errorCount = countBySeverity(run.findings, 'error');
    const warningCount = countBySeverity(run.findings, 'warning');
    const lines = [
        `  ${TOOL_LABEL[run.tool]}: ${run.filesChecked} files ${TOOL_ACTION[run.tool]}, ${errorCount} errors, ${warningCount} warnings`,
    ];

    if (run.externalFindings > 0) {
        lines.push(
            `    (${run.externalFindings} diagnostics in Administration sources outside this extension, not listed — ` +
                'the host program is part of every extension program)',
        );
    }

    for (const error of run.errors) {
        lines.push(`    tool error: ${error}`);
    }

    for (const finding of run.findings) {
        lines.push(renderFinding(finding));
    }

    return lines;
}

function renderRoot(rootReport: RootReport, projectRelativeSource: string): string[] {
    return [
        `${rootReport.root.bundleName} (${rootReport.root.extensionName})`,
        `  ${projectRelativeSource}`,
        ...rootReport.runs.flatMap((run) => renderRun(run)),
    ];
}

export interface RenderOptions {
    /** Source paths relative to the project root, keyed by slug. */
    sourcePaths: Record<string, string>;
    notices?: string[];
}

export function renderReport(report: CheckReport, options: RenderOptions): string {
    const lines: string[] = [
        'Administration extension check',
        '',
    ];

    for (const notice of options.notices ?? []) {
        lines.push(notice);
    }

    if ((options.notices ?? []).length > 0) {
        lines.push('');
    }

    for (const error of report.errors) {
        lines.push(`tool error: ${error}`);
    }

    if (report.errors.length > 0) {
        lines.push('');
    }

    if (report.roots.length === 0 && report.errors.length === 0) {
        lines.push('No Administration extension sources were checked.');
        lines.push('Install an extension, or pass --include-platform to check the platform bundles.');
        lines.push('');
    }

    for (const rootReport of report.roots) {
        lines.push(...renderRoot(rootReport, options.sourcePaths[rootReport.root.slug] ?? rootReport.root.sourcePath));
        lines.push('');
    }

    for (const error of zeroFileErrors(report)) {
        lines.push(`tool error: ${error}`);
    }

    const allFindings = report.roots.flatMap((rootReport) => rootReport.runs.flatMap((run) => run.findings));
    const filesChecked = report.roots.reduce(
        (total, rootReport) => total + rootReport.runs.reduce((sum, run) => Math.max(sum, run.filesChecked), 0),
        0,
    );

    lines.push(
        `Summary: ${report.roots.length} source roots, ${filesChecked} files checked, ` +
            `${countBySeverity(allFindings, 'error')} errors, ${countBySeverity(allFindings, 'warning')} warnings`,
    );

    return `${lines.join('\n')}\n`;
}
