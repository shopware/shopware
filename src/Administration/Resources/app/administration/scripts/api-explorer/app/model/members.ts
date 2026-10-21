/**
 * @sw-package framework
 */

import type { ApiChange, ChangeKind } from '../../diff/types';
import type {
    ComponentEntry,
    ComputedEntry,
    MethodEntry,
    NamedEntry,
    ParameterEntry,
    PropEntry,
    TypedEntry,
} from '../../snapshot/types';

export type MemberKind = Exclude<ChangeKind, 'component' | 'block' | 'objectMember'>;

export interface MemberSectionDefinition {
    kind: MemberKind;
    title: string;
    select: (component: ComponentEntry) => NamedEntry[];
    render: (entry: NamedEntry) => string;
}

export function renderParameters(parameters: ParameterEntry[]): string {
    return parameters
        .map(
            (parameter) =>
                `${parameter.rest ? '...' : ''}${parameter.name}${parameter.optional ? '?' : ''}: ${parameter.type}`,
        )
        .join(', ');
}

function renderProp(entry: PropEntry): string {
    return [
        entry.type ?? 'any',
        entry.required ? 'required' : null,
        entry.default !== null ? `default: ${entry.default}` : null,
    ]
        .filter((part) => part !== null)
        .join(', ');
}

function renderMethod(entry: MethodEntry): string {
    return `${entry.async ? 'async ' : ''}(${renderParameters(entry.parameters)}) => ${entry.returnType}`;
}

function renderComputed(entry: ComputedEntry): string {
    return `${entry.type}${entry.writable ? ' (writable)' : ''}`;
}

const renderName = (entry: NamedEntry): string => entry.name;

export const MEMBER_SECTIONS: MemberSectionDefinition[] = [
    { kind: 'prop', title: 'Props', select: (c) => c.props, render: (e) => renderProp(e as PropEntry) },
    { kind: 'emit', title: 'Emits', select: (c) => c.emits, render: renderName },
    { kind: 'slot', title: 'Slots', select: (c) => c.slots, render: renderName },
    { kind: 'data', title: 'Data', select: (c) => c.data, render: (e) => (e as TypedEntry).type },
    { kind: 'computed', title: 'Computed', select: (c) => c.computed, render: (e) => renderComputed(e as ComputedEntry) },
    { kind: 'method', title: 'Methods', select: (c) => c.methods, render: (e) => renderMethod(e as MethodEntry) },
    { kind: 'inject', title: 'Inject', select: (c) => c.inject, render: renderName },
    { kind: 'mixin', title: 'Mixins', select: (c) => c.mixins, render: renderName },
];

export interface MemberRow {
    name: string;
    base: NamedEntry | null;
    head: NamedEntry | null;
    before: string | null;
    after: string | null;
    change: ApiChange | null;
}

/** Head order, then members that only exist in the base; changes that match no entry still get a row. */
export function memberRows(
    section: MemberSectionDefinition,
    base: ComponentEntry | null,
    head: ComponentEntry | null,
    changes: ApiChange[],
): MemberRow[] {
    const baseEntries = new Map(
        (base ? section.select(base) : []).map((entry) => [
            entry.name,
            entry,
        ]),
    );
    const headEntries = new Map(
        (head ? section.select(head) : []).map((entry) => [
            entry.name,
            entry,
        ]),
    );
    const changeByName = new Map(
        changes
            .filter((change) => change.kind === section.kind)
            .map((change) => [
                change.member,
                change,
            ]),
    );
    const names = new Set([
        ...headEntries.keys(),
        ...baseEntries.keys(),
        ...changeByName.keys(),
    ]);

    return [...names].map((name) => {
        const baseEntry = baseEntries.get(name) ?? null;
        const headEntry = headEntries.get(name) ?? null;
        const change = changeByName.get(name) ?? null;

        return {
            name,
            base: baseEntry,
            head: headEntry,
            before: change ? change.before : baseEntry && section.render(baseEntry),
            after: change ? change.after : headEntry && section.render(headEntry),
            change,
        };
    });
}
