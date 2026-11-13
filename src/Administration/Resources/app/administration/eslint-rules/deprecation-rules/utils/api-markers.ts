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
// It may also end at a stray ":" or ",", which is reported instead of letting `@deprecated:` pass as a plain comment.
const TAG_LINE = /^[ \t*]*@(?<name>[\w-]+)(?:(?<stray>[:,])|(?=[ \t]|$))[ \t]*(?<rest>.*?)[ \t]*$/gm;
const DEPRECATED_PARTS = /^(?<first>\S*)[ \t]*(?<text>.*)$/;
const DEPRECATED_TEXT = /^-[ \t]+\S/;
const TEMPLATE_COMMENT = /\{#-?(?<twig>[\s\S]*?)-?#\}|<!--(?<html>[\s\S]*?)-->/dg;
const VERSION = /^v\d+\.\d+\.\d+$/;
const KEY_VALUE = /^(?<key>[A-Za-z]+):(?<value>.+)$/;
// One edit catches `@deprecate` and `@bc-chnage`, two would already report `@change` as a typo of `@bc-change`.
const MAX_TYPO_DISTANCE = 1;

// Optimal string alignment distance, so swapping two neighbouring letters counts as one edit.
function editDistance(a: string, b: string): number {
    const distances = Array.from({ length: a.length + 1 }, (_, i) =>
        Array.from({ length: b.length + 1 }, (__, j) => (i === 0 ? j : i)),
    );

    for (let i = 1; i <= a.length; i += 1) {
        for (let j = 1; j <= b.length; j += 1) {
            const substitution = distances[i - 1][j - 1] + Number(a[i - 1] !== b[j - 1]);
            const swap =
                i > 1 && j > 1 && a[i - 1] === b[j - 2] && a[i - 2] === b[j - 1] ? distances[i - 2][j - 2] + 1 : Infinity;

            distances[i][j] = Math.min(distances[i - 1][j] + 1, distances[i][j - 1] + 1, substitution, swap);
        }
    }

    return distances[a.length][b.length];
}

function normalize(name: string): string {
    return name.toLowerCase().replace(/[-_]/g, '');
}

function nearMiss(name: string, candidates: readonly string[]): string | undefined {
    return candidates.find((candidate) => editDistance(normalize(name), normalize(candidate)) <= MAX_TYPO_DISTANCE);
}

function versionErrors(version: string): string[] {
    return VERSION.test(version) ? [] : [`"${version}" is not a version of the form vX.Y.Z, for example v6.8.0.`];
}

function textErrors(text: string): string[] {
    return DEPRECATED_TEXT.test(text)
        ? []
        : ['@deprecated needs "- <text>" after the version, for example "- Use load instead".'];
}

function parseDeprecated(rest: string): Marker | string[] {
    const { first = '', text = '' } = DEPRECATED_PARTS.exec(rest)?.groups ?? {};

    if (!first.startsWith('tag:')) {
        return ['@deprecated needs "tag:vX.Y.Z" directly after the tag name.'];
    }

    const version = first.slice('tag:'.length);
    const errors = [
        ...versionErrors(version),
        ...textErrors(text),
    ];

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
        const { key, value } = KEY_VALUE.exec(word)?.groups ?? {};

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

function kindError(kind: string | undefined): string {
    const kindNames = Object.keys(BC_CHANGE_KINDS);

    if (kind === undefined) {
        return `@bc-change needs a kind, one of: ${kindNames.join(', ')}.`;
    }

    const suggestion = nearMiss(kind, kindNames);
    const hint = suggestion ? `did you mean "${suggestion}"?` : `use one of: ${kindNames.join(', ')}.`;

    return `"${kind}" is not a @bc-change kind, ${hint}`;
}

function parseBcChange(rest: string): Marker | string[] {
    const words = rest.split(/\s+/).filter(Boolean);
    // A first word of the form key:value means the kind was left out, so it is read as a key.
    const kind = words.length > 0 && !KEY_VALUE.test(words[0]) ? words[0] : undefined;
    const {
        entries: { version, ...parameters },
        errors,
    } = readKeyValues(kind === undefined ? words : words.slice(1));
    errors.push(...(version === undefined ? ['@bc-change needs "version:vX.Y.Z".'] : versionErrors(version)));

    if (kind === undefined || !isBcChangeKind(kind)) {
        return [
            kindError(kind),
            ...errors,
        ];
    }

    errors.push(...parameterErrors(kind, parameters));

    return errors.length > 0 || version === undefined ? errors : { tag: 'bc-change', kind, version, parameters };
}

const TAG_PARSERS: Record<Marker['tag'], (rest: string) => Marker | string[]> = {
    deprecated: parseDeprecated,
    'bc-change': parseBcChange,
};

function isMarkerTag(name: string): name is Marker['tag'] {
    return Object.hasOwn(TAG_PARSERS, name);
}

function parseMarkerTag(name: Marker['tag'], rest: string, stray: string | undefined): Marker | string[] {
    const result = TAG_PARSERS[name](rest);

    if (stray === undefined) {
        return result;
    }

    return [
        `"@${name}" must not be followed by "${stray}".`,
        ...(Array.isArray(result) ? result : []),
    ];
}

function parseTagLine(name: string, rest: string, stray: string | undefined): Marker | string[] | null {
    if (isMarkerTag(name)) {
        return parseMarkerTag(name, rest, stray);
    }

    const suggestion = nearMiss(name, Object.keys(TAG_PARSERS));

    return suggestion ? [`"@${name}" is not a tag, did you mean "@${suggestion}"?`] : null;
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
        const { name = '', rest = '', stray } = match.groups ?? {};
        const result = parseTagLine(name, rest, stray);

        if (result === null) {
            return [];
        }

        const line = match[0];
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
        const group = match.groups?.twig === undefined ? 'html' : 'twig';
        const content = match.groups?.[group] ?? '';
        const contentStart = match.indices?.groups?.[group]?.[0] ?? match.index;

        return parseMarkers(content).map((marker) => ({ ...marker, offset: marker.offset + contentStart }));
    });
}
