/**
 * @sw-package framework
 *
 * The findings of the break check and the summary it prints for them.
 * The summary format is described in `technical-docs/11-code-quality/03-public-api-snapshot.md`.
 */

// In this order the summary prints the groups.
const FINDING_GROUPS = [
    'Break',
    'Addition',
    'Info',
] as const;

/** @private */
export type FindingGroup = (typeof FINDING_GROUPS)[number];

/** @private */
export type FlagState = 'current' | 'major';

/**
 * @private
 *
 * One change the break check reports. `element` is the element id, for example
 * `component:sw-card/block:sw_card_header`.
 */
export interface Finding {
    group: FindingGroup;
    rule: string;
    element: string;
    states: FlagState[];
    explanation: string;
    fix?: string;
    possibleRename?: string;
    at?: string;
}

const ELEMENT_KIND = /^[^/]+\/([a-z-]+):/;

/**
 * @private
 *
 * The kind of the element below its owner, `block` for `component:sw-card/block:sw_card_header/scope:item`.
 */
export function elementKind(finding: Finding): string | undefined {
    return ELEMENT_KIND.exec(finding.element)?.[1];
}

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
export function formatFindings(findings: Finding[]): string {
    const sections = [[verdict(findings)], ...FINDING_GROUPS.map((group) => formatGroup(group, findings))];

    return `${sections
        .filter((lines) => lines.length > 0)
        .map((lines) => lines.join('\n'))
        .join('\n\n')}\n`;
}
