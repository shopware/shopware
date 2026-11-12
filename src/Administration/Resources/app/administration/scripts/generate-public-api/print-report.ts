/**
 * @sw-package framework
 *
 * Prints the `.api.md` report of a component: an outline for reviewers that merges both flag states.
 */
import { byCodePoint } from './code-point-order';
import { flagStateMarker } from './flag-states';
import type { FlagContext } from './flag-states';
import { existingElements } from './model';
import type { ComponentUnit } from './model';
import { FORMAT_DOCUMENT, GENERATED_BY, sourceFiles } from './print-shared';
import type { TemplateBlock } from './template-blocks';

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

function blockTree(unit: ComponentUnit, flags: FlagContext): Line[] {
    const children = new Map<string | null, TemplateBlock[]>();

    existingElements(unit, unit.blocks).forEach((block) => {
        children.set(block.parent, [
            ...(children.get(block.parent) ?? []),
            block,
        ]);
    });

    const below = (parent: string | null, ancestors: readonly string[]): Line[] =>
        (children.get(parent) ?? [])
            .sort((a, b) => byCodePoint(a.name, b.name))
            .flatMap(({ name, states }) => [
                line(`${'  '.repeat(ancestors.length + 1)}${name}`, flagStateMarker(states, flags)),
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

function header(unit: ComponentUnit): Line[] {
    return [
        line(`component ${unit.name}  ${unit.visibility}  ${unit.language}`),
        ...(unit.extends ? [line(`  extends ${unit.extends}`)] : []),
        line(`  registered ${unit.registeredIn}`),
        ...sourceFiles(unit).map(([label, file]) => line(`  ${label.toLowerCase()} ${file}`)),
        ...(unit.visibility === 'private' ? [line(`  visibility-source ${unit.visibilitySource}`)] : []),
    ];
}

/**
 * Every line of a private component carries `[private]` as its last marker. The headings of the sections
 * belong to no element, so they carry none.
 */
function outline(unit: ComponentUnit, flags: FlagContext): string[] {
    const sections: Section[] = [{ heading: 'blocks', lines: blockTree(unit, flags) }];
    const unitMarkers = unit.visibility === 'private' ? ['private'] : [];
    const print = ({ text, markers }: Line): string => {
        const shown = [
            ...markers,
            ...unitMarkers,
        ];

        return shown.length > 0 ? `${text}  ${shown.map((marker) => `[${marker}]`).join(' ')}` : text;
    };

    return [
        ...header(unit).map(print),
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
 * The report of a component. `hasMajorCopy` tells whether a `<name>.major.d.ts` exists beside it.
 */
export function printReport(unit: ComponentUnit, flags: FlagContext, hasMajorCopy: boolean): string {
    const contract = hasMajorCopy
        ? `\`${unit.name}.d.ts\` (current) and \`${unit.name}.major.d.ts\` (major)`
        : `\`${unit.name}.d.ts\` (both flag states)`;

    return [
        `# Component ${unit.name}`,
        '',
        `<!-- ${GENERATED_BY} -->`,
        '',
        `API report of component \`${unit.name}\` in both flag states.`,
        `Contract: ${contract}.`,
        `Line format and markers: \`${FORMAT_DOCUMENT}\`.`,
        '```text',
        ...outline(unit, flags),
        '```',
        '',
    ].join('\n');
}
