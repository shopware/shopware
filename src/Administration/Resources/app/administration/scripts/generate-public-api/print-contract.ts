/**
 * @sw-package framework
 *
 * Prints the `.d.ts` contract of a component, one file per flag state that differs.
 */
import { byCodePoint } from './code-point-order';
import type { FlagContext, FlagStates } from './flag-states';
import { existingElements } from './model';
import type { ComponentUnit } from './model';
import { GENERATED_BY, sourceFiles } from './print-shared';

type FlagState = keyof FlagStates;

/**
 * @private
 *
 * The contract files of a component by path below `public-api/`.
 */
export interface Contracts {
    files: Record<string, string>;
    hasMajorCopy: boolean;
}

const IDENTIFIER = /^[A-Za-z_$][\w$]*$/;

function literal(value: string | null): string {
    return value === null ? 'null' : `'${value}'`;
}

function key(name: string): string {
    return IDENTIFIER.test(name) ? name : literal(name);
}

function blockLines(unit: ComponentUnit, state: FlagState): string[] {
    const parents = new Map<string, (string | null)[]>();

    existingElements(unit, unit.blocks)
        .filter(({ states }) => states[state])
        .forEach(({ name, parent }) => {
            parents.set(name, [
                ...(parents.get(name) ?? []),
                parent,
            ]);
        });

    return [...parents]
        .sort(([a], [b]) => byCodePoint(a, b))
        .map(([name, parentsOfBlock]) => `${key(name)}: Block<${parentsOfBlock.map(literal).join(' | ')}>;`);
}

function member(name: string, lines: string[]): string[] {
    if (lines.length === 0) {
        return [`${name}: {};`];
    }

    return [
        `${name}: {`,
        ...lines.map((line) => `    ${line}`),
        '};',
    ];
}

function entry(unit: ComponentUnit, state: FlagState): string[] {
    return [
        ...(unit.visibility === 'private' ? ['/** @private */'] : []),
        `${literal(unit.name)}: {`,
        ...[
            ...member('$meta', [
                `kind: ${literal(unit.kind)};`,
                `lang: ${literal(unit.language)};`,
                `visibility: ${literal(unit.visibility)};`,
                `visibilitySource: ${literal(unit.visibilitySource)};`,
                `extends: ${literal(unit.extends)};`,
            ]),
            ...member('blocks', blockLines(unit, state)),
        ].map((line) => `    ${line}`),
        '};',
    ];
}

function body(unit: ComponentUnit, state: FlagState): string {
    return [
        'declare namespace AdminApi {',
        '    interface Components {',
        ...entry(unit, state).map((line) => `        ${line}`),
        '    }',
        '}',
        '',
    ].join('\n');
}

function flagStateLine(name: string, state: FlagState, hasMajorCopy: boolean, { majorFlag }: FlagContext): string {
    if (state === 'major') {
        return `// Flag state: major (${majorFlag} and every flag with \`major: ${majorFlag}\` on).`;
    }

    const current = `// Flag state: current (${majorFlag} off).`;

    return hasMajorCopy ? current : `${current} The major state is identical, so there is no \`${name}.major.d.ts\`.`;
}

function header(unit: ComponentUnit, state: FlagState, hasMajorCopy: boolean, flags: FlagContext): string {
    return [
        `// ${GENERATED_BY}`,
        flagStateLine(unit.name, state, hasMajorCopy, flags),
        ...sourceFiles(unit).map(([label, file]) => `// ${label}: ${file}`),
        '',
        '',
    ].join('\n');
}

/**
 * @private
 *
 * Prints `<name>.d.ts` for the current state, and `<name>.major.d.ts` only when the major state differs.
 */
export function printContracts(unit: ComponentUnit, flags: FlagContext): Contracts {
    const current = body(unit, 'current');
    const major = body(unit, 'major');
    const hasMajorCopy = current !== major;

    return {
        hasMajorCopy,
        files: {
            [`${unit.file}.d.ts`]: header(unit, 'current', hasMajorCopy, flags) + current,
            ...(hasMajorCopy && { [`${unit.file}.major.d.ts`]: header(unit, 'major', true, flags) + major }),
        },
    };
}
