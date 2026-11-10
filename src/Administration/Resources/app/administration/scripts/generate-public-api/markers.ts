/**
 * @sw-package framework
 *
 * Grammar of the markers that announce a change to the public API of the Administration:
 * `@deprecated tag:vX.Y.Z - <text>` for an element that is removed or replaced, and
 * `@bc-change <Kind> version:vX.Y.Z [<key>:<value> ...]` for a planned change to an element that stays.
 *
 * Only the format is checked here. Whether a marker sits on an element its kind fits, and whether it
 * describes a real change, needs the snapshot and is not part of this file.
 */

/**
 * @private
 *
 * The kinds of `@bc-change` with the keys each one requires. The names follow the PHP attributes in
 * `src/Core/Framework/Deprecation/BCChange/` where the change exists in both worlds.
 */
export const BC_CHANGE_KINDS = {
    ParameterRemoval: ['parameter'],
    NewRequiredParameter: ['parameter'],
    ParameterDefaultValueChange: [
        'parameter',
        'default',
    ],
    ReturnTypeNarrowing: ['type'],
    ReturnTypeWidening: ['type'],
    BecomesPrivate: [],
    BlockMoved: ['parent'],
    ScopeVariableRemoval: ['variable'],
    PropBecomesRequired: [],
    EmitPayloadChange: [],
    RouteParentChange: ['parent'],
    RegistryGroupChange: ['group'],
} as const satisfies Record<string, readonly string[]>;

/** @private */
export type BcChangeKind = keyof typeof BC_CHANGE_KINDS;

/** @private */
export type Marker =
    | { tag: 'deprecated'; version: string }
    | { tag: 'bc-change'; kind: BcChangeKind; version: string; parameters: Record<string, string> };

/**
 * @private
 *
 * A marker or a near miss of one. `offset` and `length` locate its line in the text that was parsed,
 * starting at the `@`.
 */
export type MarkerMatch = { offset: number; length: number } & ({ marker: Marker } | { errors: string[] });

// The name has to end at a space, so `@change="onChange"` in commented-out markup is not a tag.
const TAG_LINE = /^[ \t*]*@([\w-]+)(?=[ \t]|$)[ \t]*(.*?)[ \t]*$/gm;
const TEMPLATE_COMMENT = /\{#-?([\s\S]*?)-?#\}|<!--([\s\S]*?)-->/g;
const VERSION = /^v\d+\.\d+\.\d+$/;
const KEY_VALUE = /^([A-Za-z]+):(.+)$/;
const MAX_TYPO_DISTANCE = 2;

function editDistance(a: string, b: string): number {
    let previous = Array.from({ length: b.length + 1 }, (_, index) => index);

    for (let i = 1; i <= a.length; i += 1) {
        const current = [i];

        for (let j = 1; j <= b.length; j += 1) {
            const substitution = previous[j - 1] + (a[i - 1] === b[j - 1] ? 0 : 1);
            current.push(Math.min(previous[j] + 1, current[j - 1] + 1, substitution));
        }

        previous = current;
    }

    return previous[b.length];
}

function normalize(name: string): string {
    return name.toLowerCase().replace(/[-_]/g, '');
}

function closest(name: string, candidates: readonly string[]): { name: string; distance: number } {
    return candidates
        .map((candidate) => ({ name: candidate, distance: editDistance(normalize(name), normalize(candidate)) }))
        .reduce((best, candidate) => (candidate.distance < best.distance ? candidate : best));
}

function versionErrors(version: string): string[] {
    return VERSION.test(version) ? [] : [`"${version}" is not a version of the form vX.Y.Z, for example v6.8.0.`];
}

function parseDeprecated(rest: string): Marker | string[] {
    const [first = ''] = rest.split(/\s+/);

    if (!first.startsWith('tag:')) {
        return ['@deprecated needs "tag:vX.Y.Z" directly after the tag name.'];
    }

    const version = first.slice('tag:'.length);
    const errors = versionErrors(version);

    return errors.length > 0 ? errors : { tag: 'deprecated', version };
}

function parameterErrors(kind: BcChangeKind, parameters: Record<string, string>): string[] {
    const required: readonly string[] = BC_CHANGE_KINDS[kind];
    const takes = required.length > 0 ? `it takes: ${required.join(', ')}` : 'it takes no keys';

    return [
        ...required.filter((key) => !Object.hasOwn(parameters, key)).map((key) => `${kind} needs "${key}:<value>".`),
        ...Object.keys(parameters)
            .filter((key) => !required.includes(key))
            .map((key) => `${kind} has no key "${key}", ${takes}.`),
    ];
}

function isBcChangeKind(kind: string): kind is BcChangeKind {
    return Object.hasOwn(BC_CHANGE_KINDS, kind);
}

function readKeyValues(words: string[]): { entries: Record<string, string>; errors: string[] } {
    const entries: Record<string, string> = {};
    const errors: string[] = [];

    words.forEach((word) => {
        const [
            ,
            key,
            value,
        ] = KEY_VALUE.exec(word) ?? [];

        if (key === undefined) {
            errors.push(`"${word}" is not of the form key:value. Free text starts on the line after the marker.`);
        } else if (Object.hasOwn(entries, key)) {
            errors.push(`"${key}" is given twice.`);
        } else {
            entries[key] = value;
        }
    });

    return { entries, errors };
}

function parseBcChange(rest: string): Marker | string[] {
    const [
        kind,
        ...words
    ] = rest.split(/\s+/).filter(Boolean);
    const kindNames = Object.keys(BC_CHANGE_KINDS);

    if (kind === undefined) {
        return [`@bc-change needs a kind, one of: ${kindNames.join(', ')}.`];
    }

    const { entries, errors } = readKeyValues(words);
    const { version, ...parameters } = entries;
    errors.push(...(version === undefined ? ['@bc-change needs "version:vX.Y.Z".'] : versionErrors(version)));

    if (!isBcChangeKind(kind)) {
        return [
            `"${kind}" is not a @bc-change kind, the closest one is "${closest(kind, kindNames).name}".`,
            ...errors,
        ];
    }

    errors.push(...parameterErrors(kind, parameters));

    return errors.length > 0 ? errors : { tag: 'bc-change', kind, version, parameters };
}

const TAG_PARSERS: Record<Marker['tag'], (rest: string) => Marker | string[]> = {
    deprecated: parseDeprecated,
    'bc-change': parseBcChange,
};

function parseTagLine(name: string, rest: string): Marker | string[] | null {
    if (Object.hasOwn(TAG_PARSERS, name)) {
        return TAG_PARSERS[name as Marker['tag']](rest);
    }

    const tag = closest(name, Object.keys(TAG_PARSERS));

    return tag.distance <= MAX_TYPO_DISTANCE ? [`"@${name}" is not a tag, the closest one is "@${tag.name}".`] : null;
}

/**
 * @private
 *
 * Reads the markers out of the content of one comment, without its delimiters. A tag only counts at
 * the start of a line, so a mention inside a sentence is not a marker. A line that looks like a marker
 * is returned as valid or with its errors, never dropped.
 */
export function parseMarkers(comment: string): MarkerMatch[] {
    return [...comment.matchAll(TAG_LINE)].flatMap((match) => {
        const [
            line,
            name,
            rest,
        ] = match;
        const result = parseTagLine(name, rest);

        if (result === null) {
            return [];
        }

        const start = line.indexOf('@');
        const position = { offset: match.index + start, length: line.trimEnd().length - start };

        return [Array.isArray(result) ? { ...position, errors: result } : { ...position, marker: result }];
    });
}

/**
 * @private
 *
 * Reads the markers out of every Twig comment (`{# ... #}`) and HTML comment of a template.
 */
export function parseTemplateMarkers(template: string): MarkerMatch[] {
    return [...template.matchAll(TEMPLATE_COMMENT)].flatMap((match) => {
        const content = match[1] ?? match[2];
        const contentStart = match.index + match[0].indexOf(content);

        return parseMarkers(content).map((marker) => ({ ...marker, offset: marker.offset + contentStart }));
    });
}
