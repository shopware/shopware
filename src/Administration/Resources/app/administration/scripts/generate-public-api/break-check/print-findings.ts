/**
 * @sw-package framework
 *
 * Prints the summary of the break check: the verdict, then the findings by group.
 * The summary format is described in `technical-docs/11-code-quality/03-public-api-snapshot.md`.
 */
import { FINDING_GROUPS } from './finding';
import type { Finding, FindingGroup } from './finding';

function plural(count: number, word: string): string {
    return `${count} ${word}${count === 1 ? '' : 's'}`;
}

function verdict(findings: Finding[]): string {
    const breaks = findings.filter(({ group }) => group === 'Break').length;

    return breaks > 0
        ? `Public API check failed: ${plural(breaks, 'unannounced break')}.`
        : 'Public API check passed: no breaks.';
}

function formatFinding(finding: Finding): string[] {
    const details = [
        finding.explanation,
        finding.fix === undefined ? undefined : `Fix: ${finding.fix}`,
        finding.possibleRename === undefined ? undefined : `Possible rename: ${finding.possibleRename}.`,
        finding.alsoAffects === undefined ? undefined : `Also affects: ${finding.alsoAffects.join(', ')}.`,
        finding.at === undefined ? undefined : `At: ${finding.at}`,
    ].filter((detail) => detail !== undefined);

    return [
        `  ${finding.rule}  ${finding.element}  ${finding.states.join(', ')}`,
        ...details.map((detail) => `    ${detail}`),
    ];
}

function formatGroup(group: FindingGroup, findings: Finding[]): string[] {
    const members = findings.filter((finding) => finding.group === group);

    return members.length === 0 ? [] : [`${group} (${members.length})`, ...members.flatMap(formatFinding)];
}

/**
 * @private
 *
 * The summary of the break check: the verdict, then the findings by group. Empty groups are left out.
 */
export function printFindings(findings: Finding[]): string {
    const sections = [[verdict(findings)], ...FINDING_GROUPS.map((group) => formatGroup(group, findings))];

    return `${sections
        .filter((lines) => lines.length > 0)
        .map((lines) => lines.join('\n'))
        .join('\n\n')}\n`;
}
