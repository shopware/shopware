/**
 * @sw-package framework
 *
 * Prints the `.api.md` report of a component: an outline for reviewers that merges both flag states.
 */
import { blockLabel, crossingSubject } from '../model/blocks';
import type { BlockContext, Crossing, TemplateBlock } from '../model/blocks';
import { mergeStates } from '../model/flag-states';
import type { FlagContext, FlagStates } from '../model/flag-states';
import type { ComponentUnit } from '../model/model';
import { kindsPlaced, memberSections } from './print-members';
import { flagStateMarker, line, markerLabels, notesSection, printOutline, printReportFile } from './report-outline';
import type { Line } from './report-outline';

/** A block under one parent as the report shows it. `markers` holds what follows its name, without its flag states. */
interface PrintedBlock {
    name: string;
    parent: string | null;
    markers: string[];
    /** The `@deprecated` and `@bc-change` markers of the block, which follow its flag states. */
    announced: string[];
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
    return mergeStates(
        blocks.map(({ name, parent, origin, context, states, markers }) => ({
            name,
            parent,
            markers: [
                ...(origin === null ? [] : [origin]),
                ...contextMarkers(context),
            ],
            announced: markerLabels(markers),
            states,
        })),
        ({ name, parent, markers, announced }) =>
            [
                name,
                parent ?? '',
                ...markers,
                ...announced,
            ].join('\n'),
    );
}

function blockTree(unit: ComponentUnit, flags: FlagContext): Line[] {
    const children = new Map<string | null, PrintedBlock[]>();

    printedBlocks(unit.blocks).forEach((block) =>
        children.set(block.parent, [...(children.get(block.parent) ?? []), block]),
    );

    const below = (parent: string | null, ancestors: readonly string[]): Line[] =>
        (children.get(parent) ?? []).flatMap(({ name, markers, announced, states }) => [
            line(`${'  '.repeat(ancestors.length + 1)}${name}`, ...markers, flagStateMarker(states, flags), ...announced),
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
    return crossings.map((crossing) => {
        const subject = crossing.kind === 'chain' ? crossingSubject(crossing) : `<${crossingSubject(crossing)}>`;

        return line(
            `  ${subject}  ${blockLabel(crossing.opens)} -> ${blockLabel(crossing.closes)}`,
            flagStateMarker(crossing.states, flags),
        );
    });
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
 * @private
 *
 * The report of a component.
 */
export function printReport(unit: ComponentUnit, flags: FlagContext): string {
    const sections = [
        ...memberSections(unit.members, kindsPlaced('beforeBlocks'), flags),
        { heading: 'blocks', lines: blockTree(unit, flags) },
        { heading: 'crossings', lines: crossingLines(unit.crossings, flags) },
        ...memberSections(unit.members, kindsPlaced('afterBlocks'), flags),
        notesSection(unit.notes),
    ];

    return printReportFile(`Component ${unit.name}`, `component \`${unit.name}\``, [
        printOutline(header(unit, flags), sections, unit.visibility === 'private'),
    ]);
}
