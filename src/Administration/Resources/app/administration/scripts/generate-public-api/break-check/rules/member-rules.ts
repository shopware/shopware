/**
 * @sw-package framework
 *
 * The rules of the break check for the members of a component. A member that is gone or new is one rule per
 * kind, built from the table of kinds. The rules below them are about what only one kind has, such as the
 * parameters of a method. Each rule says in its texts what a plugin notices of the change.
 */
import type { Marker } from '../../../../eslint-rules/deprecation-rules/utils/api-markers';
import type { FlagContext } from '../../model/flag-states';
import { isTemplateMember, KINDS, MEMBER_KINDS, memberId } from '../../model/members';
import type { Member, MemberKind, Parameter } from '../../model/members';
import type { SourceLine } from '../../model/source-line';
import type { BlockFacts } from '../facts/block-facts';
import type { ComponentFacts } from '../facts/component-facts';
import { sectionLine } from '../facts/member-facts';
import { isMajorOnly } from '../finding';
import type { FindingGroup } from '../finding';
import { rule } from './rule';
import type { Rule } from './rule';

/** The first place of every member of the kind. What a rule compares of all places, it reads from the facts. */
function membersOf<Kind extends MemberKind>(facts: ComponentFacts, kind: Kind): Member<Kind>[] {
    return [...facts.members[kind].values()].map(([member]) => member);
}

/** A member that both commits have. */
interface Kept<Kind extends MemberKind = MemberKind> {
    was: Member<Kind>;
    now: Member<Kind>;
}

function inBoth<Kind extends MemberKind>(base: ComponentFacts, head: ComponentFacts, kind: Kind): Kept<Kind>[] {
    return membersOf(base, kind).flatMap((was) => {
        const [now] = head.members[kind].get(was.name) ?? [];

        return now === undefined ? [] : [{ was, now }];
    });
}

function onlyIn<Kind extends MemberKind>(facts: ComponentFacts, other: ComponentFacts, kind: Kind): Member<Kind>[] {
    return membersOf(facts, kind).filter(({ name }) => !other.members[kind].has(name));
}

/** The member as the element of a change, or something `below` it such as `/param:item`. */
function memberElement({ kind, name, at, isPrivate }: Member, below = '') {
    return { element: `${memberId({ kind, name })}${below}`, at, isPrivate, kind, name };
}

/**
 * The element of a change to a member that both commits have. Its line is the one at the head. Whether it is
 * private is decided at the base: a member that becomes `@private` with the change was public API until then.
 */
function keptElement({ was, now }: Kept, below = '') {
    return { ...memberElement(now, below), isPrivate: was.isPrivate };
}

/** The markers in the comment above the member of a change, which can announce it. */
function memberMarkers({ kind, name }: Pick<Member, 'kind' | 'name'>, facts: ComponentFacts): Marker[] {
    return facts.members[kind].get(name)?.flatMap(({ markers }) => markers) ?? [];
}

function isBcChange(marker: Marker, kind: string, parameter?: string): boolean {
    return (
        marker.tag === 'bc-change' &&
        marker.kind === kind &&
        (parameter === undefined || marker.parameters.parameter === parameter)
    );
}

function label(kind: MemberKind): string {
    return MEMBER_KINDS[kind].label;
}

function deprecation(flags: FlagContext, text: string): string {
    return `/** @deprecated tag:${flags.majorVersion} - ${text} */`;
}

/** What a finding about a removed member says, besides what a plugin notices of it. */
interface RemovalPolicy {
    group: FindingGroup;
    /** How the finding says that the member is gone. */
    gone: (kind: MemberKind, name: string) => string;
    fix: (removed: { kind: MemberKind; name: string; rename: string | undefined }, flags: FlagContext) => string;
    /** A new member of the same kind can be the removed one under a new name. */
    suggestsRename: boolean;
    /** What the author of a new member of the kind can do about it being public. */
    additionFix?: string;
}

/** A member of the options object can stay beside its replacement, with a marker that announces its removal. */
const DECLARED: RemovalPolicy = {
    group: 'Break',
    gone: (kind) => `The ${label(kind)} is gone`,
    fix: ({ kind, name, rename }, flags) =>
        rename === undefined
            ? `pattern "Removal". Keep the ${label(kind)} and mark it ${deprecation(flags, 'Will be removed.')}.`
            : `pattern "Rename". Keep ${name} and mark it ${deprecation(flags, `Use {@link ${rename}} instead.`)}.`,
    suggestsRename: true,
    additionFix: 'mark it @private if it is not meant for plugins.',
};

/** A member of the template carries no marker, and the line of its finding is that of a block, so it is named. */
const RENDERED: RemovalPolicy = {
    group: 'Break',
    gone: (kind, name) => `The ${label(kind)} ${name} is gone`,
    fix: ({ kind }) => `keep the ${label(kind)}.`,
    suggestsRename: true,
};

/** CSS selectors fail silently and markup changes often, so a class is a hint. All classes look the same. */
const STYLE_HOOK: RemovalPolicy = {
    group: 'Info',
    gone: (kind, name) => `The ${label(kind)} ${name} is no longer in the template`,
    fix: () => 'mention it in the upgrade notes if plugins are likely to style it.',
    suggestsRename: false,
};

/**
 * How the break check treats a member that comes or goes, per kind. `effect` is what a plugin notices when the
 * member is gone. A new ref or class is no API a plugin can be told to rely on, so it is no addition.
 */
const COMING_AND_GOING: Record<
    MemberKind,
    { effect: (name: string) => string; removal: RemovalPolicy; isAddition: boolean }
> = {
    mixin: {
        effect: (name) => `plugin overrides that use a member of ${name} through this fail`,
        removal: DECLARED,
        isAddition: true,
    },
    inject: {
        effect: (name) => `plugin overrides that read this.${name} get undefined`,
        removal: DECLARED,
        isAddition: true,
    },
    provide: {
        effect: (name) => `plugin components that inject ${name} get undefined`,
        removal: DECLARED,
        isAddition: true,
    },
    prop: {
        effect: (name) =>
            `a value that plugin templates pass as ${name} falls through as an HTML attribute, and plugin overrides that read this.${name} get undefined`,
        removal: DECLARED,
        isAddition: true,
    },
    emit: {
        effect: (name) => `listeners that plugin templates add with @${name} are no longer called by the component`,
        removal: DECLARED,
        isAddition: true,
    },
    slot: {
        effect: (name) => `content that plugin templates pass to the slot ${name} is no longer rendered`,
        removal: RENDERED,
        isAddition: true,
    },
    data: {
        effect: (name) => `plugin overrides that read or set this.${name} work on a value the component ignores`,
        removal: DECLARED,
        isAddition: true,
    },
    computed: {
        effect: (name) => `plugin overrides that call $super('${name}') throw at runtime`,
        removal: DECLARED,
        isAddition: true,
    },
    method: {
        effect: (name) => `plugin overrides that call $super('${name}') throw at runtime`,
        removal: DECLARED,
        isAddition: true,
    },
    ref: {
        effect: (name) => `plugin overrides that read this.$refs.${name} get undefined`,
        removal: RENDERED,
        isAddition: false,
    },
    class: {
        effect: () => 'plugin styles and selectors that use it stop matching',
        removal: STYLE_HOOK,
        isAddition: false,
    },
};

/** Everything of a member besides its name and where it stands. */
function shapeOf(member: Member): string {
    return JSON.stringify({ ...member, name: null, at: null, markers: null, states: null });
}

/** A new member of the same kind that looks the same is probably the removed one under a new name. */
function possibleRename(removed: Member, base: ComponentFacts, head: ComponentFacts): string | undefined {
    const candidates = onlyIn(head, base, removed.kind).filter((added) => shapeOf(added) === shapeOf(removed));

    return COMING_AND_GOING[removed.kind].removal.suggestsRename && candidates.length === 1 ? candidates[0].name : undefined;
}

/** The block, or the closest block around it at the base, that the head still has. */
function remainingBlock(block: string | null, base: ComponentFacts, head: ComponentFacts): BlockFacts | undefined {
    if (block === null) {
        return undefined;
    }

    const [parent = null] = base.blocks.get(block)?.parents ?? [];

    return head.blocks.get(block) ?? remainingBlock(parent, base, head);
}

/**
 * The line a finding about a removed member points to. The member has no line at the head, so it is the option
 * that held it, or for a member of the template the block around it.
 */
function lineOfRemoved(removed: Member, base: ComponentFacts, head: ComponentFacts): SourceLine | undefined {
    return isTemplateMember(removed) ? remainingBlock(removed.block, base, head)?.at : sectionLine(removed, head);
}

function memberMissing(kind: MemberKind): Rule {
    const { effect, removal } = COMING_AND_GOING[kind];

    return rule({
        id: `${kind}_missing`,
        group: removal.group,
        changes: (base, head) =>
            onlyIn(base, head, kind).map((removed) => ({
                ...memberElement(removed),
                at: lineOfRemoved(removed, base, head),
                rename: possibleRename(removed, base, head),
            })),
        texts: ({ name, rename }, { flags, states }) => ({
            explanation: `${removal.gone(kind, name)}${isMajorOnly(states) ? ' in the major state' : ''}, so ${effect(name)}.`,
            fix: removal.fix({ kind, name, rename }, flags),
            ...(rename === undefined ? {} : { possibleRename: rename }),
        }),
        announcement: {
            rule: 'deprecated_removal',
            subject: `The ${label(kind)}`,
            // A lambda, so the type of the change is inferred from `changes` alone.
            markers: (change, facts) => memberMarkers(change, facts),
            matches: (_change, marker) => marker.tag === 'deprecated',
        },
    });
}

function memberAdded(kind: MemberKind): Rule {
    const { additionFix } = COMING_AND_GOING[kind].removal;

    return rule({
        id: `${kind}_added`,
        group: 'Addition',
        changes: (base, head) => onlyIn(head, base, kind).map((added) => memberElement(added)),
        texts: () => ({
            explanation: `A new public ${label(kind)} that plugins can rely on from now on.`,
            ...(additionFix === undefined ? {} : { fix: additionFix }),
        }),
    });
}

const propNowRequired = rule({
    id: 'prop_now_required',
    group: 'Break',
    changes: (base, head) =>
        inBoth(base, head, 'prop')
            .filter(({ was, now }) => !was.required && now.required)
            .map((prop) => keptElement(prop)),
    texts: ({ name }, { component, flags }) => ({
        explanation: `The prop is now required, so plugin templates that render ${component} without ${name} fail prop validation.`,
        fix: `pattern "Prop becomes required". Keep ${name} optional, call triggerDeprecationOrThrow when it is missing, and mark it @bc-change PropBecomesRequired version:${flags.majorVersion}.`,
    }),
    announcement: {
        rule: 'announced_change',
        subject: 'The prop',
        markers: (change, facts) => memberMarkers(change, facts),
        matches: (_change, marker) => isBcChange(marker, 'PropBecomesRequired'),
    },
});

function declaredTypes<Kind extends MemberKind>(members: Kept<Kind>[], typeOf: (member: Member<Kind>) => string | null) {
    return members
        .map((member) => ({ ...keptElement(member), from: typeOf(member.was), to: typeOf(member.now) }))
        .filter(({ from, to }) => from !== to);
}

/** The kinds that declare a type, each with where it stands. A method declares what it returns. */
function typeChanges(base: ComponentFacts, head: ComponentFacts) {
    return [
        ...declaredTypes(inBoth(base, head, 'prop'), ({ type }) => type),
        ...declaredTypes(inBoth(base, head, 'computed'), ({ type }) => type),
        ...declaredTypes(inBoth(base, head, 'method'), ({ returns }) => returns),
    ];
}

/*
 * Whether another type still fits what plugins pass or read needs the type checker. The rule compares the text
 * of the declaration, so it only points the reviewer to the change.
 */
const typeChanged = rule({
    id: 'type_changed',
    group: 'Info',
    changes: (base, head) => typeChanges(base, head).filter(({ from }) => from !== null),
    texts: ({ kind, from, to }) => ({
        explanation: `The declared type of the ${label(kind)} changed from ${from} to ${to ?? 'untyped'}.`,
        fix: 'check that the values plugins pass or read still fit the new type.',
    }),
});

const typeWidened = rule({
    id: 'type_widened',
    group: 'Info',
    changes: (base, head) => typeChanges(base, head).filter(({ from }) => from === null),
    texts: ({ kind, to }) => ({ explanation: `The ${label(kind)} now declares the type ${to}. It was untyped before.` }),
});

/** A method that both commits declare with parameters the source names. */
interface MethodChange {
    method: Kept<'method'>;
    before: Parameter[];
    after: Parameter[];
}

function methodChanges(base: ComponentFacts, head: ComponentFacts): MethodChange[] {
    return inBoth(base, head, 'method').flatMap((method) =>
        method.was.parameters === null || method.now.parameters === null
            ? []
            : [{ method, before: method.was.parameters, after: method.now.parameters }],
    );
}

function names(parameters: Parameter[]): string[] {
    return parameters.map(({ name }) => name);
}

/** The same parameters in another order. Then no position is compared with what stood there before. */
function isReordered({ before, after }: MethodChange): boolean {
    const sameNames = before.length === after.length && names(before).every((name) => names(after).includes(name));

    return sameNames && names(before).some((name, position) => name !== after[position].name);
}

/** The positions both commits have, unless the parameters changed their order. */
function keptPositions(change: MethodChange): { was: Parameter; now: Parameter }[] {
    return isReordered(change)
        ? []
        : change.before.slice(0, change.after.length).map((was, position) => ({ was, now: change.after[position] }));
}

function isRequired({ optional, rest }: Parameter): boolean {
    return !optional && !rest;
}

function parameterElement(method: Kept<'method'>, parameter: Parameter) {
    return { ...keptElement(method, `/param:${parameter.name}`), parameter: parameter.name };
}

const methodSignatureChanged = rule({
    id: 'method_signature_changed',
    group: 'Break',
    changes: (base, head) => [
        ...inBoth(base, head, 'method')
            .filter(({ was, now }) => was.isAsync !== now.isAsync)
            .map((method) => ({ ...keptElement(method), becameAsync: method.now.isAsync, order: undefined })),
        ...methodChanges(base, head)
            .filter(isReordered)
            .map(({ method, before, after }) => ({
                ...keptElement(method),
                becameAsync: undefined,
                order: { before: names(before).join(', '), after: names(after).join(', ') },
            })),
    ],
    texts: ({ name, becameAsync, order }) => {
        if (order !== undefined) {
            return {
                explanation: `The parameters of the method changed their order from (${order.before}) to (${order.after}), so plugin calls and $super('${name}', ...) calls pass their values in the wrong place.`,
                fix: 'keep the order of the parameters and add a method under a new name.',
            };
        }

        return becameAsync
            ? {
                  explanation: `The method switched from sync to async, so plugin overrides that use the return value of $super('${name}') get a Promise instead.`,
                  fix: 'keep the method sync and add an async method under a new name.',
              }
            : {
                  explanation: `The method switched from async to sync, so plugin overrides that chain a .then() on $super('${name}') fail.`,
                  fix: 'keep the method async and add a sync method under a new name.',
              };
    },
});

const paramMissing = rule({
    id: 'param_missing',
    group: 'Break',
    changes: (base, head) =>
        methodChanges(base, head).flatMap(({ method, before, after }) =>
            before.slice(after.length).map((removed) => parameterElement(method, removed)),
        ),
    texts: ({ name, parameter }, { flags }) => ({
        explanation: `The parameter ${parameter} is gone, so plugin overrides of ${name} that read it get undefined.`,
        fix: `pattern "Parameter removal". Keep ${parameter}, mark the method @bc-change ParameterRemoval version:${flags.majorVersion} parameter:${parameter}, and remove it in the major.`,
    }),
    announcement: {
        rule: 'announced_change',
        subject: 'The method',
        markers: (change, facts) => memberMarkers(change, facts),
        matches: ({ parameter }, marker) => isBcChange(marker, 'ParameterRemoval', parameter),
    },
});

const paramNowRequired = rule({
    id: 'param_now_required',
    group: 'Break',
    changes: (base, head) =>
        methodChanges(base, head).flatMap((change) => [
            ...change.after
                .slice(change.before.length)
                .filter(isRequired)
                .map((added) => ({ ...parameterElement(change.method, added), isNew: true })),
            ...keptPositions(change)
                .filter(({ was, now }) => !isRequired(was) && isRequired(now))
                .map(({ now }) => ({ ...parameterElement(change.method, now), isNew: false })),
        ]),
    texts: ({ name, parameter, isNew }, { flags }) => ({
        explanation: isNew
            ? `The method has a new required parameter ${parameter}, so plugin calls and $super('${name}', ...) calls that pass the old arguments leave it undefined.`
            : `The parameter ${parameter} lost its default, so plugin calls and $super('${name}', ...) calls that do not pass it leave it undefined.`,
        fix: `pattern "New required parameter". Keep ${parameter} optional, call triggerDeprecationOrThrow when it is missing, and mark the method @bc-change NewRequiredParameter version:${flags.majorVersion} parameter:${parameter}.`,
    }),
    announcement: {
        rule: 'announced_change',
        subject: 'The method',
        markers: (change, facts) => memberMarkers(change, facts),
        matches: ({ parameter }, marker) => isBcChange(marker, 'NewRequiredParameter', parameter),
    },
});

const paramAdded = rule({
    id: 'param_added',
    group: 'Addition',
    changes: (base, head) =>
        methodChanges(base, head).flatMap(({ method, before, after }) =>
            after
                .slice(before.length)
                .filter((added) => !isRequired(added))
                .map((added) => parameterElement(method, added)),
        ),
    texts: ({ parameter }) => ({
        explanation: `A new optional parameter ${parameter}. Plugin overrides that call $super with explicit arguments do not pass it on.`,
    }),
});

const methodParamRenamed = rule({
    id: 'method_param_renamed',
    group: 'Info',
    changes: (base, head) =>
        methodChanges(base, head).flatMap((change) =>
            keptPositions(change)
                .filter(({ was, now }) => was.name !== now.name)
                .map(({ was, now }) => ({ ...parameterElement(change.method, now), was: was.name })),
        ),
    texts: ({ parameter, was }) => ({
        explanation: `The parameter ${was} is now called ${parameter}. It kept its position, so calls pass the same value.`,
    }),
});

/** What a ref points to in all its places: a tag, and `[]` behind it for a ref inside a `v-for`. */
function refTargets(facts: ComponentFacts, name: string): string {
    const targets = (facts.members.ref.get(name) ?? []).map(({ target, list }) => `${target}${list ? '[]' : ''}`);

    return [...new Set(targets)].sort().join(' or ');
}

const refTargetChanged = rule({
    id: 'ref_target_changed',
    group: 'Break',
    changes: (base, head) =>
        inBoth(base, head, 'ref')
            .map((ref) => ({
                ...keptElement(ref),
                from: refTargets(base, ref.was.name),
                to: refTargets(head, ref.now.name),
            }))
            .filter(({ from, to }) => from !== to),
    texts: ({ name, from, to }) => ({
        explanation: `The template ref ${name} now points to ${to} instead of ${from}, so plugin code that calls methods on it fails.`,
        fix: `pattern "Rename". Keep the ref ${name} on ${from} and give the ref on ${to} another name.`,
    }),
});

function markerText(marker: Marker): string {
    return marker.tag === 'deprecated'
        ? `@deprecated tag:${marker.version}`
        : `@bc-change ${marker.kind} version:${marker.version}`;
}

/** `v6.8.0` gives `UPGRADE-6.8.md`. */
function upgradeFile({ version }: Marker): string {
    return `UPGRADE-${version.slice(1).split('.').slice(0, 2).join('.')}.md`;
}

/** A marker that names a parameter is about that parameter, which is an element of its own. */
function parameterOf(marker: Marker): string {
    const parameter = marker.tag === 'bc-change' ? marker.parameters.parameter : undefined;

    return parameter === undefined ? '' : `/param:${parameter}`;
}

function newMarkers(base: ComponentFacts, head: ComponentFacts, tag: Marker['tag']) {
    return KINDS.flatMap((kind): Kept[] => inBoth(base, head, kind)).flatMap((member) => {
        const known = new Set(memberMarkers(member.was, base).map((marker) => JSON.stringify(marker)));

        return memberMarkers(member.now, head)
            .filter((marker) => marker.tag === tag && !known.has(JSON.stringify(marker)))
            .map((marker) => ({ member, marker }));
    });
}

function markerAdded(id: string, tag: Marker['tag'], announces: string): Rule {
    return rule({
        id,
        group: 'Info',
        changes: (base, head) =>
            newMarkers(base, head, tag).map(({ member, marker }) => ({
                ...keptElement(member, parameterOf(marker)),
                marker: markerText(marker),
                version: marker.version,
                upgradeFile: upgradeFile(marker),
            })),
        texts: ({ kind, marker, version, upgradeFile: file }) => ({
            explanation: `The ${label(kind)} now carries ${marker}, so ${announces} in ${version} is announced.`,
            fix: `add an upgrade note for it to ${file}.`,
        }),
    });
}

/** @private */
export const MEMBER_RULES: readonly Rule[] = [
    ...KINDS.map(memberMissing),
    ...KINDS.filter((kind) => COMING_AND_GOING[kind].isAddition).map(memberAdded),
    propNowRequired,
    typeChanged,
    typeWidened,
    methodSignatureChanged,
    paramMissing,
    paramNowRequired,
    paramAdded,
    methodParamRenamed,
    refTargetChanged,
    markerAdded('deprecation_added', 'deprecated', 'its removal'),
    markerAdded('bc_change_added', 'bc-change', 'this change'),
];
