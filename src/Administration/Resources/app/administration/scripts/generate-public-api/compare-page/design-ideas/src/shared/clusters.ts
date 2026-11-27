/**
 * @sw-package framework
 *
 * Clusters the findings of a report by the component, service or store they are about. Inside a cluster, breaks
 * that share a rule and a fix become one line with their places, and additions and infos become one line of names.
 */
import { groupBy, membersOf, plural, unitOf } from './report';
import type { Finding, FindingGroup, Part, Unit } from './report';

/**
 * @private
 *
 * One or more findings that need the same fix: a break, or a change that a marker announced.
 */
export interface FixLine {
    type: 'fix';
    key: string;
    group: FindingGroup;
    rule: string;
    /** What the places have in common, for example `5 blocks › scope itemIndex`. */
    title: string;
    /** `name` tells a place from the others of the line. A line with one place needs none beside its title. */
    places: Array<{ finding: Finding; name: string | null }>;
}

/**
 * @private
 *
 * Findings that say the same about each element, so their names are enough.
 */
export interface NamesLine {
    type: 'names';
    key: string;
    group: FindingGroup;
    /** What happened to the elements, for example `deprecated`. Additions need none beside their badge. */
    label: string | null;
    size: number;
    /** The elements by their kind, for example `data` with `limit` and `page`. */
    kinds: Array<{ kind: string; findings: Finding[] }>;
}

/** @private */
export type ClusterLine = FixLine | NamesLine;

/** @private */
export interface Cluster {
    unit: Unit;
    /** The module folder of the sources, for example `sw-order`. */
    module: string;
    /** The folders of the sources below `src`, without the folder of the unit itself: `module`, `sw-order`, `component`. */
    folder: string[];
    breaks: number;
    /** The sizes of the groups, in the order of the lines, for example `6 breaks`. */
    counts: Array<{ group: FindingGroup; size: number; label: string }>;
    lines: ClusterLine[];
}

/** @private */
export interface ClusterSection {
    module: string;
    breaks: number;
    clusters: Cluster[];
}

const FIX_GROUPS: readonly FindingGroup[] = [
    'Break',
    'Allowed',
];

const NAME_GROUPS: readonly FindingGroup[] = [
    'Addition',
    'Info',
];

const GROUP_WORDS: Record<FindingGroup, [one: string, many: string]> = {
    Break: [
        'break',
        'breaks',
    ],
    Addition: [
        'addition',
        'additions',
    ],
    Allowed: [
        'allowed',
        'allowed',
    ],
    Info: [
        'info',
        'info',
    ],
    Private: [
        'private',
        'private',
    ],
};

const INFO_LABELS: Record<string, string> = {
    deprecation_added: 'deprecated',
};

/** `src/module/sw-order/...` is `sw-order`, `src/app/component/...` is `app/component`. */
function moduleOf(findings: readonly Finding[]): string {
    const at = findings.find((finding) => finding.at !== undefined)?.at;

    if (at === undefined) {
        return 'other';
    }

    const [, first, second] = at.split('/');

    return first === 'module' ? second : `${first}/${second}`;
}

function folderOf(findings: readonly Finding[], unit: Unit): string[] {
    const at = findings.find((finding) => finding.at !== undefined)?.at;

    if (at === undefined) {
        return [];
    }

    const folders = at.split('/').slice(1, -1);

    return folders.at(-1) === unit.name ? folders.slice(0, -1) : folders;
}

/** The fix without the names of the finding's own elements, so two places of one change compare equal. */
function fixKeyOf(finding: Finding): string {
    const own = [
        ...membersOf(finding).map(({ name }) => name),
        finding.possibleRename ?? '',
    ].filter((name) => name !== '');

    return own.reduce((fix, name) => fix.split(name).join('…'), `${finding.group}|${finding.rule}|${finding.fix ?? ''}`);
}

/**
 * @private
 *
 * The name of an element as the lines show it. A crossing is named `<element>@<block it opens in>><block it
 * closes in>`, which reads better in words.
 */
export function partName({ kind, name }: Part): string {
    const [
        ,
        element,
        from,
        to,
    ] = /^(.+)@(.+)>(.+)$/.exec(kind === 'crossing' ? name : '') ?? [];

    return element === undefined ? name : `${element} from ${from} to ${to}`;
}

function partText(part: Part): string {
    return part.kind === 'crossing' ? partName(part) : `${part.kind} ${partName(part)}`;
}

const MANY: Record<string, string> = {
    computed: 'computed properties',
    data: 'data properties',
    inject: 'injections',
};

function manyOf(kind: string): string {
    if (kind in MANY) {
        return MANY[kind];
    }

    if (kind.endsWith('y')) {
        return `${kind.slice(0, -1)}ies`;
    }

    return kind.endsWith('s') ? `${kind}es` : `${kind}s`;
}

/** Whether every place has the same part at `index`. */
function isShared(places: readonly Part[][], index: number): boolean {
    return places.every((parts) => parts[index]?.name === places[0][index]?.name);
}

/** The parts all places share stay, and a part that differs becomes its number: `5 blocks › scope itemIndex`. */
function titleOf(places: readonly Part[][], unit: Unit): string {
    if (places[0].length === 0) {
        return `the whole ${unit.kind}`;
    }

    return places[0]
        .map((part, index) =>
            isShared(places, index) ? partText(part) : plural(places.length, part.kind, manyOf(part.kind)),
        )
        .join(' › ');
}

/** The parts that tell this place from the others, for example `block sw_card_header`. */
function placeName(parts: readonly Part[], places: readonly Part[][]): string | null {
    const own = parts.filter((_part, index) => !isShared(places, index));

    return own.length === 0 ? null : own.map(partText).join(' › ');
}

function fixLines(findings: readonly Finding[], unit: Unit): FixLine[] {
    return [...groupBy(findings, fixKeyOf)]
        .map(([key, places]): FixLine => {
            const parts = places.map(membersOf);

            return {
                type: 'fix',
                key,
                group: places[0].group,
                rule: places[0].rule,
                title: titleOf(parts, unit),
                places: places.map((finding, index) => ({ finding, name: placeName(parts[index], parts) })),
            };
        })
        .sort((one, other) => other.places.length - one.places.length);
}

function byKind(findings: readonly Finding[]): NamesLine['kinds'] {
    return [...groupBy(findings, (finding) => membersOf(finding).at(-1)?.kind ?? '')].map(([kind, members]) => ({
        kind,
        findings: members,
    }));
}

function namesLines(findings: readonly Finding[], group: FindingGroup): NamesLine[] {
    if (group === 'Addition') {
        return [{ type: 'names', key: group, group, label: null, size: findings.length, kinds: byKind(findings) }];
    }

    return [...groupBy(findings, ({ rule }) => rule)].map(([rule, members]) => ({
        type: 'names',
        key: `${group}|${rule}`,
        group,
        label: INFO_LABELS[rule] ?? rule.split('_').join(' '),
        size: members.length,
        kinds: byKind(members),
    }));
}

function clusterOf(findings: readonly Finding[]): Cluster {
    const unit = unitOf(findings[0]);
    const byGroup = groupBy(findings, ({ group }) => group);
    const present = [
        ...FIX_GROUPS,
        ...NAME_GROUPS,
    ].filter((group) => byGroup.has(group));

    return {
        unit,
        module: moduleOf(findings),
        folder: folderOf(findings, unit),
        breaks: byGroup.get('Break')?.length ?? 0,
        counts: present.map((group) => ({
            group,
            size: byGroup.get(group)?.length ?? 0,
            label: plural(byGroup.get(group)?.length ?? 0, ...GROUP_WORDS[group]),
        })),
        lines: present.flatMap((group): ClusterLine[] => {
            const members = byGroup.get(group) ?? [];

            return FIX_GROUPS.includes(group) ? fixLines(members, unit) : namesLines(members, group);
        }),
    };
}

function mostBreaksFirst<Item extends { breaks: number }>(name: (item: Item) => string): (one: Item, other: Item) => number {
    return (one, other) => other.breaks - one.breaks || name(one).localeCompare(name(other));
}

/**
 * @private
 *
 * The clusters of `findings`, the ones with the most breaks first.
 */
export function clustersOf(findings: readonly Finding[]): Cluster[] {
    return [...groupBy(findings, (finding) => unitOf(finding).id).values()]
        .map(clusterOf)
        .sort(mostBreaksFirst(({ unit }) => unit.name));
}

/**
 * @private
 *
 * The clusters by the module folder of their sources, the modules with the most breaks first.
 */
export function sectionsOf(clusters: readonly Cluster[]): ClusterSection[] {
    const sections = new Map<string, Cluster[]>();

    clusters.forEach((cluster) => sections.set(cluster.module, [...(sections.get(cluster.module) ?? []), cluster]));

    return [...sections]
        .map(([module, members]) => ({
            module,
            breaks: members.reduce((sum, { breaks }) => sum + breaks, 0),
            clusters: members,
        }))
        .sort(mostBreaksFirst(({ module }) => module));
}
