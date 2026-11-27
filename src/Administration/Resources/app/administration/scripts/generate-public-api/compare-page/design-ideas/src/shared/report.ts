/**
 * @sw-package framework
 *
 * The findings the design ideas show, and the ways the directions slice them.
 */
import type { ExplainedFinding, FindingGroup } from '../../../../break-check/finding';
import type { CodeBlock } from '../../../finding-code';

/**
 * @private
 *
 * A finding with what the detail of a break shows: what the break check says about it beyond the summary, and
 * the code that `data/dump-findings.ts` cut out of the sources of both commits.
 */
export type Finding = ExplainedFinding & { code?: { changed?: CodeBlock; fix?: CodeBlock } };

/** @private */
export type { CodeBlock, FindingGroup };

/** @private */
export interface Report {
    base: string;
    head: string;
    findings: Finding[];
}

declare global {
    interface Window {
        REPORTS: Record<'small' | 'large', Report>;
    }
}

/** @private */
export const REPOSITORY = 'shopware/shopware';
const ADMINISTRATION = 'src/Administration/Resources/app/administration';

/** @private */
export const GROUP_ORDER: readonly FindingGroup[] = [
    'Break',
    'Addition',
    'Allowed',
    'Info',
    'Private',
];

/** @private */
export const BADGE_VARIANT: Record<FindingGroup, 'critical' | 'positive' | 'attention' | 'neutral' | 'info'> = {
    Break: 'critical',
    Addition: 'positive',
    Allowed: 'attention',
    Info: 'info',
    Private: 'neutral',
};

/** @private */
export interface Unit {
    /** `component:sw-card` */
    id: string;
    kind: string;
    name: string;
}

/** @private */
export interface Part {
    kind: string;
    name: string;
}

function partOf(segment: string): Part {
    const colon = segment.indexOf(':');

    return { kind: segment.slice(0, colon), name: segment.slice(colon + 1) };
}

/** @private */
export function unitOf(finding: Finding): Unit {
    const id = finding.element.split('/')[0];

    return { id, ...partOf(id) };
}

/**
 * @private
 *
 * The parts of the element below its unit, for example the block and the scope variable.
 */
export function membersOf(finding: Finding): Part[] {
    return finding.element.split('/').slice(1).map(partOf);
}

/** @private */
export function memberText(finding: Finding): string {
    const members = membersOf(finding);

    return members.length === 0
        ? `the whole ${unitOf(finding).kind}`
        : members.map(({ kind, name }) => `${kind} ${name}`).join(' › ');
}

/** @private */
export function fileOf(at: string): string {
    const file = at.slice(0, at.lastIndexOf(':'));

    return file.slice(file.lastIndexOf('/') + 1);
}

/** @private */
export function shortAt(at: string): string {
    return `${fileOf(at)}:${at.slice(at.lastIndexOf(':') + 1)}`;
}

/** @private */
export function sourceUrl(commit: string, at: string): string {
    const separator = at.lastIndexOf(':');

    return `https://github.com/${REPOSITORY}/blob/${commit}/${ADMINISTRATION}/${at.slice(0, separator)}#L${at.slice(separator + 1)}`;
}

/** @private */
export function pullRequestUrl(number: number): string {
    return `https://github.com/${REPOSITORY}/pull/${number}`;
}

/**
 * @private
 *
 * `null` when the finding holds with the major flags on and off, which is the common case.
 */
export function stateNote(finding: Finding): string | null {
    if (finding.states.length !== 1) {
        return null;
    }

    return finding.states[0] === 'major' ? 'only with the major flags on' : 'only with the major flags off';
}

/** @private */
export interface FixText {
    /** The change pattern of the documentation, if the fix names one. */
    pattern: string | null;
    /** The fix without the pattern, split into prose and markers to copy. */
    pieces: Array<{ text: string; code: boolean }>;
}

const MARKER = /\{#.*?#\}|\/\*\*.*?\*\/|@bc-change \w+(?: \w+:\S+)*/g;

/** @private */
export function fixTextOf(fix: string): FixText {
    const named = /^pattern "([^"]+)"\. /.exec(fix);
    const rest = named === null ? fix : fix.slice(named[0].length);
    const text = rest.charAt(0).toUpperCase() + rest.slice(1);
    const pieces: FixText['pieces'] = [];
    let index = 0;

    for (const match of text.matchAll(MARKER)) {
        const code = match[0].replace(/[.,]$/, '');

        pieces.push({ text: text.slice(index, match.index), code: false }, { text: code, code: true });
        index = match.index + code.length;
    }

    pieces.push({ text: text.slice(index), code: false });

    return { pattern: named?.[1] ?? null, pieces: pieces.filter((piece) => piece.text !== '') };
}

/** @private */
export function countBy<Key extends string>(findings: readonly Finding[], key: (finding: Finding) => Key): Map<Key, number> {
    const counts = new Map<Key, number>();

    findings.forEach((finding) => counts.set(key(finding), (counts.get(key(finding)) ?? 0) + 1));

    return counts;
}

/** @private */
export function groupBy<Key>(findings: readonly Finding[], key: (finding: Finding) => Key): Map<Key, Finding[]> {
    const groups = new Map<Key, Finding[]>();

    findings.forEach((finding) => {
        const members = groups.get(key(finding));

        if (members === undefined) {
            groups.set(key(finding), [finding]);
        } else {
            members.push(finding);
        }
    });

    return groups;
}

/** @private */
export function plural(count: number, word: string, many = `${word}s`): string {
    return `${count.toLocaleString('en')} ${count === 1 ? word : many}`;
}

/** @private */
export function matches(finding: Finding, query: string): boolean {
    const text = [
        finding.group,
        finding.rule,
        finding.element,
        finding.explanation,
        finding.fix ?? '',
        finding.at ?? '',
        ...(finding.alsoAffects ?? []),
    ]
        .join(' ')
        .toLowerCase();

    return query
        .toLowerCase()
        .split(/\s+/)
        .filter((word) => word !== '')
        .every((word) => text.includes(word));
}
