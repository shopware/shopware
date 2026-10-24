/**
 * @sw-package framework
 */

import type {
    BlockEntry,
    ComponentEntry,
    ComputedEntry,
    MethodEntry,
    NamedEntry,
    PropEntry,
    TypedEntry,
    Visibility,
} from '../snapshot/types';
import {
    addedChange,
    deprecationFindings,
    describeTextChange,
    isPublic,
    normalizeText,
    removedChange,
    resolveChange,
    textChanged,
    type ChangeCollector,
    type ChangeTarget,
    type Finding,
} from './changes';
import { compareMethods, renderMethod } from './signatures';
import type { ChangeKind } from './types';

interface MemberRules<T extends NamedEntry> {
    kind: ChangeKind;
    select: (component: ComponentEntry) => T[];
    render: (entry: T) => string;
    compare: (base: T, head: T) => Finding[];
    /** Overrides the plain 'added' severity of a public addition. */
    addition?: (entry: T) => Finding | undefined;
}

function typeFinding(base: string | null, head: string | null): Finding[] {
    if (!textChanged(base, head)) {
        return [];
    }

    return [
        {
            severity: 'potentiallyBreaking',
            change: 'changed',
            reason: describeTextChange('Type', base, head),
        },
    ];
}

function renderProp(prop: PropEntry): string {
    const parts = [normalizeText(prop.type) ?? 'any'];

    if (prop.required) {
        parts.push('required');
    }

    if (prop.default !== null) {
        parts.push(`default: ${normalizeText(prop.default)}`);
    }

    return parts.join(', ');
}

function compareProps(base: PropEntry, head: PropEntry): Finding[] {
    const findings = typeFinding(base.type, head.type);

    if (!base.required && head.required) {
        findings.push({ severity: 'breaking', change: 'changed', reason: 'Now required.' });
    } else if (base.required && !head.required) {
        findings.push({ severity: 'compatible', change: 'changed', reason: 'No longer required.' });
    }

    if (textChanged(base.default, head.default)) {
        findings.push({
            severity: 'potentiallyBreaking',
            change: 'changed',
            reason: describeTextChange('Default', base.default, head.default),
        });
    }

    return findings;
}

function compareComputed(base: ComputedEntry, head: ComputedEntry): Finding[] {
    const findings = typeFinding(base.type, head.type);

    if (base.writable && !head.writable) {
        findings.push({ severity: 'breaking', change: 'changed', reason: 'No longer writable.' });
    } else if (!base.writable && head.writable) {
        findings.push({ severity: 'compatible', change: 'changed', reason: 'Now writable.' });
    }

    return findings;
}

const renderName = (entry: NamedEntry): string => entry.name;
const nameOnly = (): Finding[] => [];

type MemberDiff = (pair: ComponentPair, collector: ChangeCollector) => void;

function memberDiff<T extends NamedEntry>(rules: MemberRules<T>): MemberDiff {
    return (pair, collector) => diffMembers(pair, rules, collector);
}

const MEMBER_DIFFS: MemberDiff[] = [
    memberDiff<PropEntry>({
        kind: 'prop',
        select: (component) => component.props,
        render: renderProp,
        compare: compareProps,
        addition: (prop) =>
            prop.required ? { severity: 'breaking', change: 'added', reason: 'New required prop.' } : undefined,
    }),
    memberDiff({ kind: 'emit', select: (component) => component.emits, render: renderName, compare: nameOnly }),
    memberDiff({ kind: 'slot', select: (component) => component.slots, render: renderName, compare: nameOnly }),
    memberDiff<TypedEntry>({
        kind: 'data',
        select: (component) => component.data,
        render: (entry) => normalizeText(entry.type) ?? '',
        compare: (base, head) => typeFinding(base.type, head.type),
    }),
    memberDiff<ComputedEntry>({
        kind: 'computed',
        select: (component) => component.computed,
        render: (entry) => `${normalizeText(entry.type)} (${entry.writable ? 'writable' : 'read-only'})`,
        compare: compareComputed,
    }),
    memberDiff<MethodEntry>({
        kind: 'method',
        select: (component) => component.methods,
        render: renderMethod,
        compare: compareMethods,
    }),
    memberDiff({ kind: 'inject', select: (component) => component.inject, render: renderName, compare: nameOnly }),
    memberDiff({ kind: 'mixin', select: (component) => component.mixins, render: renderName, compare: nameOnly }),
];

function effectiveVisibility(component: ComponentEntry, entry: NamedEntry): Visibility {
    return isPublic(component.visibility) ? entry.visibility : component.visibility;
}

function byName<T extends NamedEntry>(entries: T[]): Map<string, T> {
    const map = new Map<string, T>();

    entries.forEach((entry) => {
        if (!map.has(entry.name)) {
            map.set(entry.name, entry);
        }
    });

    return map;
}

interface ComponentPair {
    base: ComponentEntry;
    head: ComponentEntry;
}

function memberTarget<T extends NamedEntry>(
    pair: ComponentPair,
    kind: ChangeKind,
    member: string,
    base: T | undefined,
    head: T | undefined,
    render: (entry: T) => string,
): ChangeTarget {
    return {
        area: 'component',
        kind,
        owner: pair.head.name,
        member,
        before: base ? render(base) : null,
        after: head ? render(head) : null,
        location: head?.location ?? base?.location ?? null,
    };
}

function addedMember(pair: ComponentPair, target: ChangeTarget, head: NamedEntry, publicFinding?: Finding) {
    const visibility = isPublic(pair.base.visibility) ? head.visibility : effectiveVisibility(pair.head, head);

    return addedChange(target, visibility, publicFinding, pair.base.visibility);
}

function removedMember(pair: ComponentPair, target: ChangeTarget, base: NamedEntry) {
    const deprecation = pair.base.deprecation;

    return removedChange(
        target,
        effectiveVisibility(pair.base, base),
        base.deprecation,
        deprecation ? { label: `component \`${pair.base.name}\``, deprecation } : null,
    );
}

function diffMatched(
    pair: ComponentPair,
    target: ChangeTarget,
    base: NamedEntry,
    head: NamedEntry,
    findings: Finding[],
): ReturnType<typeof resolveChange> {
    return resolveChange(target, [...findings, ...deprecationFindings(base.deprecation, head.deprecation)], {
        base: effectiveVisibility(pair.base, base),
        head: effectiveVisibility(pair.head, head),
        reportTransition: isPublic(base.visibility) !== isPublic(head.visibility),
    });
}

function diffMembers<T extends NamedEntry>(pair: ComponentPair, rules: MemberRules<T>, collector: ChangeCollector): void {
    const baseMembers = byName(rules.select(pair.base));
    const headMembers = byName(rules.select(pair.head));

    baseMembers.forEach((base, name) => {
        const head = headMembers.get(name);
        const target = memberTarget(pair, rules.kind, name, base, head, rules.render);

        if (!head) {
            collector.add(removedMember(pair, target, base));
            return;
        }

        collector.add(diffMatched(pair, target, base, head, rules.compare(base, head)));
    });

    headMembers.forEach((head, name) => {
        if (baseMembers.has(name)) {
            return;
        }

        collector.add(
            addedMember(
                pair,
                memberTarget(pair, rules.kind, name, undefined, head, rules.render),
                head,
                rules.addition?.(head),
            ),
        );
    });
}

function countNames(blocks: BlockEntry[]): Map<string, number> {
    const counts = new Map<string, number>();
    blocks.forEach((block) => counts.set(block.name, (counts.get(block.name) ?? 0) + 1));

    return counts;
}

function parentChain(block: BlockEntry): string {
    const segments = block.key.replace(/~\d+$/, '').split('/').slice(0, -1);

    return segments.length > 0 ? segments.join('/') : '(top level)';
}

function blockMatchKeys(pair: ComponentPair): (block: BlockEntry) => string {
    const baseCounts = countNames(pair.base.blocks);
    const headCounts = countNames(pair.head.blocks);

    return (block) => (baseCounts.get(block.name) === 1 && headCounts.get(block.name) === 1 ? block.name : block.key);
}

function compareBlocks(base: BlockEntry, head: BlockEntry): Finding[] {
    const findings: Finding[] = [];

    if (base.parent !== head.parent) {
        findings.push({
            severity: 'potentiallyBreaking',
            change: 'moved',
            reason: `Moved from \`${base.parent ?? '(top level)'}\` to \`${head.parent ?? '(top level)'}\`.`,
            before: parentChain(base),
            after: parentChain(head),
        });
    }

    if (base.contentHash !== head.contentHash) {
        findings.push({ severity: 'compatible', change: 'changed', reason: 'Content changed.' });
    }

    return findings;
}

function diffBlocks(pair: ComponentPair, collector: ChangeCollector): void {
    const matchKey = blockMatchKeys(pair);
    const baseBlocks = new Map<string, BlockEntry>();
    const headBlocks = new Map<string, BlockEntry>();
    pair.base.blocks.forEach((block) => baseBlocks.set(matchKey(block), baseBlocks.get(matchKey(block)) ?? block));
    pair.head.blocks.forEach((block) => headBlocks.set(matchKey(block), headBlocks.get(matchKey(block)) ?? block));
    const render = (entry: BlockEntry): string => entry.content;

    baseBlocks.forEach((base, key) => {
        const head = headBlocks.get(key);
        const target = memberTarget(pair, 'block', key, base, head, render);

        if (!head) {
            collector.add(removedMember(pair, target, base));
            return;
        }

        collector.add(diffMatched(pair, target, base, head, compareBlocks(base, head)));
    });

    headBlocks.forEach((head, key) => {
        if (!baseBlocks.has(key)) {
            collector.add(addedMember(pair, memberTarget(pair, 'block', key, undefined, head, render), head));
        }
    });
}

function renderComponent(component: ComponentEntry): string {
    return `${component.registration}${component.extends !== null ? ` extends ${component.extends}` : ''}`;
}

function componentTarget(name: string, base: ComponentEntry | undefined, head: ComponentEntry | undefined): ChangeTarget {
    return {
        area: 'component',
        kind: 'component',
        owner: name,
        member: name,
        before: base ? renderComponent(base) : null,
        after: head ? renderComponent(head) : null,
        location: head?.location ?? base?.location ?? null,
    };
}

function diffComponent(pair: ComponentPair, collector: ChangeCollector): void {
    const findings: Finding[] = [];

    if (pair.base.registration !== pair.head.registration) {
        findings.push({
            severity: 'potentiallyBreaking',
            change: 'changed',
            reason: `Registration changed from ${pair.base.registration} to ${pair.head.registration}.`,
        });
    }

    if (pair.base.extends !== pair.head.extends) {
        findings.push({
            severity: 'potentiallyBreaking',
            change: 'changed',
            reason: `Parent component changed from \`${pair.base.extends ?? 'none'}\` to \`${pair.head.extends ?? 'none'}\`.`,
        });
    }

    findings.push(...deprecationFindings(pair.base.deprecation, pair.head.deprecation));
    collector.add(
        resolveChange(componentTarget(pair.head.name, pair.base, pair.head), findings, {
            base: pair.base.visibility,
            head: pair.head.visibility,
            reportTransition: true,
        }),
    );

    MEMBER_DIFFS.forEach((diff) => diff(pair, collector));
    diffBlocks(pair, collector);
}

export function diffComponents(base: ComponentEntry[], head: ComponentEntry[], collector: ChangeCollector): void {
    const baseComponents = byName(base);
    const headComponents = byName(head);

    baseComponents.forEach((baseComponent, name) => {
        const headComponent = headComponents.get(name);

        if (headComponent) {
            diffComponent({ base: baseComponent, head: headComponent }, collector);
        } else {
            collector.add(
                removedChange(
                    componentTarget(name, baseComponent, undefined),
                    baseComponent.visibility,
                    baseComponent.deprecation,
                ),
            );
        }
    });

    headComponents.forEach((headComponent, name) => {
        if (!baseComponents.has(name)) {
            collector.add(addedChange(componentTarget(name, undefined, headComponent), headComponent.visibility));
        }
    });
}
