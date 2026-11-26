/**
 * @sw-package framework
 *
 * Prints the findings of the break check for a GitHub Actions run: one annotation per break, and the summary of
 * the job.
 */
import { breaksOf } from './finding';
import type { Finding } from './finding';
import { findingDetails, printFindings } from './print-findings';

/**
 * @private
 *
 * `error` when breaks fail the check, `warning` when the check only reports them.
 */
export type AnnotationLevel = 'error' | 'warning';

/** https://docs.github.com/en/actions/reference/workflows-and-actions/workflow-commands */
function escapeData(value: string): string {
    return value.replaceAll('%', '%25').replaceAll('\r', '%0D').replaceAll('\n', '%0A');
}

function escapeProperty(value: string): string {
    return escapeData(value).replaceAll(':', '%3A').replaceAll(',', '%2C');
}

/** `at` is `<file>:<line>`, with the file relative to the Administration folder. */
function locationOf(at: string | undefined, administration: string): string[] {
    const separator = at?.lastIndexOf(':') ?? -1;

    if (at === undefined || separator < 0) {
        return [];
    }

    return [
        `file=${escapeProperty(`${administration}/${at.slice(0, separator)}`)}`,
        `line=${at.slice(separator + 1)}`,
    ];
}

function annotationOf(finding: Finding, level: AnnotationLevel, administration: string): string {
    const properties = [
        ...locationOf(finding.at, administration),
        `title=${escapeProperty(`Public API: ${finding.rule}`)}`,
    ];
    const message = [
        ...findingDetails(finding),
        `${finding.element}  ${finding.states.join(', ')}`,
    ];

    return `::${level} ${properties.join(',')}::${escapeData(message.join('\n'))}`;
}

/**
 * @private
 *
 * One workflow command per break. GitHub shows it on the line of the head commit that the finding names, and
 * without a line on the page of the run. `administration` is the path of the Administration folder in the
 * repository, because GitHub expects paths from the root of the repository.
 */
export function printAnnotations(findings: Finding[], level: AnnotationLevel, administration: string): string {
    return breaksOf(findings)
        .map((finding) => `${annotationOf(finding, level, administration)}\n`)
        .join('');
}

/**
 * @private
 *
 * The summary of the job in Markdown: the summary of the break check as text, and a note when breaks do not
 * fail the check.
 */
export function printJobSummary(findings: Finding[], level: AnnotationLevel): string {
    return [
        '## Administration public API',
        '',
        ...(level === 'warning' ? ['Report only: unannounced breaks do not fail this check yet.', ''] : []),
        '```text',
        printFindings(findings).trimEnd(),
        '```',
        '',
    ].join('\n');
}
