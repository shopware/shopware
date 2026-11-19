/**
 * @sw-package framework
 *
 * Prints the `.api.md` report of a component: an outline for reviewers that merges both flag states.
 */
import { byCodePoint } from './code-point-order';
import { flagStateMarker } from './flag-states';
import type { FlagContext } from './flag-states';
import type { ComponentUnit } from './model';
import { blockLabel, crossingSubject } from './block-structure';
import type { Crossing } from './block-structure';
import type { BlockContext, TemplateBlock } from './block-tree';
import { inAnyState } from './flag-states';
import type { FlagStates } from './flag-states';

/** @private */
export const GENERATE_COMMAND = 'composer admin:generate-public-api';

const FORMAT_DOCUMENT = 'technical-docs/11-code-quality/03-public-api-snapshot.md';

/** A line of the outline. Its markers follow the text in brackets, without the brackets here. */
interface Line {
    text: string;
    markers: string[];
}

interface Section {
    heading: string;
    lines: Line[];
}

function line(text: string, ...markers: (string | null)[]): Line {
    return { text, markers: markers.filter((marker) => marker !== null) };
}

/** A block under one parent as the report shows it. `markers` holds what follows its name, without its flag states. */
interface PrintedBlock {
    name: string;
    parent: string | null;
    markers: string[];
    states: FlagStates;
}

function contextMarkers({ afterIf, scope }: BlockContext): string[] {
    return [
        ...(afterIf === null ? [] : ['after v-if']),
        ...(scope.length === 0 ? [] : [`scope: ${scope.map(({ name }) => name).join(', ')}`]),
    ];
}

/**
 * The report shows that a block follows a `v-if` chain and which variables it can use, but not the conditions
 * of the chain and where a variable comes from. Entries that differ in those only are one line.
 */
function printedBlocks(blocks: TemplateBlock[]): PrintedBlock[] {
    const printed = new Map<string, PrintedBlock>();

    blocks.forEach(({ name, parent, origin, context, states }) => {
        const markers = [
            ...(origin === null ? [] : [origin]),
            ...contextMarkers(context),
        ];
        const key = [name, parent ?? '', ...markers].join('\n');
        const known = printed.get(key);

        printed.set(key, { name, parent, markers, states: known ? inAnyState(known.states, states) : states });
    });

    return [...printed.entries()].sort(([a], [b]) => byCodePoint(a, b)).map(([, block]) => block);
}

function blockTree(unit: ComponentUnit, flags: FlagContext): Line[] {
    const children = new Map<string | null, PrintedBlock[]>();

    printedBlocks(unit.blocks).forEach((block) =>
        children.set(block.parent, [...(children.get(block.parent) ?? []), block]),
    );

    const below = (parent: string | null, ancestors: readonly string[]): Line[] =>
        (children.get(parent) ?? []).flatMap(({ name, markers, states }) => [
            line(`${'  '.repeat(ancestors.length + 1)}${name}`, ...markers, flagStateMarker(states, flags)),
            // A block that sits inside itself would repeat forever.
            ...(ancestors.includes(name)
                ? []
                : below(name, [
                      ...ancestors,
                      name,
                  ])),
        ]);

    return below(null, []);
}

function crossingLines(crossings: Crossing[], flags: FlagContext): Line[] {
    return crossings
        .map((crossing) => {
            const subject = crossing.kind === 'chain' ? crossingSubject(crossing) : `<${crossingSubject(crossing)}>`;

            return line(
                `  ${subject}  ${blockLabel(crossing.opens)} -> ${blockLabel(crossing.closes)}`,
                flagStateMarker(crossing.states, flags),
            );
        })
        .sort((a, b) => byCodePoint(a.text, b.text));
}

function header(unit: ComponentUnit, flags: FlagContext): Line[] {
    return [
        line(`component ${unit.name}  ${unit.visibility}  ${unit.language}`, flagStateMarker(unit.states, flags)),
        ...(unit.extends ? [line(`  extends ${unit.extends}`)] : []),
        line(`  registered ${unit.registeredIn}`),
        line(`  source ${unit.source}`),
        ...(unit.sfc ? [line(`  sfc ${unit.sfc}`)] : []),
        ...(unit.template ? [line(`  template ${unit.template}`)] : []),
        ...(unit.visibility === 'private' ? [line(`  visibility-source ${unit.visibilitySource}`)] : []),
    ];
}

/**
 * Every line of a private component carries `[private]` as its last marker. The headings of the sections
 * belong to no element, so they carry none.
 */
function outline(unit: ComponentUnit, flags: FlagContext): string[] {
    const sections: Section[] = [
        { heading: 'blocks', lines: blockTree(unit, flags) },
        { heading: 'crossings', lines: crossingLines(unit.crossings, flags) },
    ];
    const unitMarkers = unit.visibility === 'private' ? ['private'] : [];
    const print = ({ text, markers }: Line): string => {
        const shown = [
            ...markers,
            ...unitMarkers,
        ];

        return shown.length > 0 ? `${text}  ${shown.map((marker) => `[${marker}]`).join(' ')}` : text;
    };

    return [
        ...header(unit, flags).map(print),
        ...sections
            .filter(({ lines }) => lines.length > 0)
            .flatMap(({ heading, lines }) => [
                '',
                heading,
                ...lines.map(print),
            ]),
    ];
}

/**
 * @private
 *
 * The report of a component.
 */
export function printReport(unit: ComponentUnit, flags: FlagContext): string {
    return [
        `# Component ${unit.name}`,
        '',
        `<!-- Generated by \`${GENERATE_COMMAND}\`. Do not edit. -->`,
        '',
        `API report of component \`${unit.name}\` in both flag states.`,
        `Line format and markers: \`${FORMAT_DOCUMENT}\`.`,
        '```text',
        ...outline(unit, flags),
        '```',
        '',
    ].join('\n');
}
