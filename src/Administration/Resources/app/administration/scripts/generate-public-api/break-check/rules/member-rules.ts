/**
 * @sw-package framework
 *
 * The rules of the break check for the members of a component. A member that is gone or new is one rule per
 * kind, built from the table of kinds. The rules below them are about what only one kind has, such as the
 * parameters of a method. Each rule says in its texts what a plugin notices of the change.
 */
import type { BcChangeKind, Marker } from '../../../../eslint-rules/deprecation-rules/utils/api-markers';
import type { FlagContext } from '../../model/flag-states';
import { isTemplateMember, KINDS, MEMBER_KINDS, memberId, parameterId } from '../../model/members';
import type { Member, MemberKind, Parameter } from '../../model/members';
import type { SourceLine } from '../../model/source-line';
import { coveringDeprecation } from '../facts/block-facts';
import type { BlockFacts } from '../facts/block-facts';
import type { ComponentFacts } from '../facts/component-facts';
import { sectionLine } from '../facts/member-facts';
import { isMajorOnly } from '../finding';
import { rule } from './rule';
import type { FindingTexts, Rule } from './rule';

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

/** The markers in the comment above the member, in all its places. */
function ownMarkers({ kind, name }: Pick<Member, 'kind' | 'name'>, facts: ComponentFacts): Marker[] {
    return facts.members[kind].get(name)?.flatMap(({ markers }) => markers) ?? [];
}

function serialized(marker: Marker): string {
    return JSON.stringify(marker);
}

/**
 * A slot or a ref carries no comment of its own. It goes away with a deprecated block around it, when it does so
 * in every place.
 */
function enclosingDeprecations({ kind, name }: Pick<Member, 'kind' | 'name'>, facts: ComponentFacts): Marker[] {
    return coveringDeprecation(
        (facts.members[kind].get(name) ?? [])
            .filter(isTemplateMember)
            .map(({ block }) => (block === null ? [] : (facts.blocks.get(block)?.deprecations ?? []))),
    );
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

/** What a finding about a removed member says, besides what a plugin notices of it, and what announces it. */
interface RemovalPolicy {
    markers: (member: Pick<Member, 'kind' | 'name'>, facts: ComponentFacts) => Marker[];
    /** How the finding says that the member is gone. */
    gone: (kind: MemberKind, name: string) => string;
    fix: (removed: { kind: MemberKind; name: string; rename: string | undefined }, flags: FlagContext) => string;
    /** What the author of a new member of the kind can do about it being public. */
    additionFix?: string;
}

/** A member of the options object can stay beside its replacement, with a marker that announces its removal. */
const DECLARED: RemovalPolicy = {
    markers: ownMarkers,
    gone: (kind) => `The ${label(kind)} is gone`,
    fix: ({ kind, name, rename }, flags) =>
        rename === undefined
            ? `pattern "Removal". Keep the ${label(kind)} and mark it ${deprecation(flags, 'Will be removed.')}.`
            : `pattern "Rename". Keep ${name} and mark it ${deprecation(flags, `Use {@link ${rename}} instead.`)}.`,
    additionFix: 'mark it @private if it is not meant for plugins.',
};

/** The line of a finding about a member of the template is that of a block, so the finding names the member. */
const RENDERED: RemovalPolicy = {
    markers: enclosingDeprecations,
    gone: (kind, name) => `The ${label(kind)} ${name} is gone`,
    fix: ({ kind }, flags) =>
        `keep the ${label(kind)}, or remove it with the block around it once that block is marked {# @deprecated tag:${flags.majorVersion} - Will be removed. #}.`,
};

/**
 * How the break check treats a member that comes or goes, per kind. `effect` is what a plugin notices when the
 * member is gone. A new ref is no API a plugin can be told to rely on, so it is no addition.
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
};

/** Everything of a member besides its name and where it stands. */
function shapeOf(member: Member): string {
    return JSON.stringify({ ...member, name: null, at: null, markers: null, states: null });
}

/** A new member of the same kind that looks the same is probably the removed one under a new name. */
function possibleRename(removed: Member, base: ComponentFacts, head: ComponentFacts): string | undefined {
    const candidates = onlyIn(head, base, removed.kind).filter((added) => shapeOf(added) === shapeOf(removed));

    return candidates.length === 1 ? candidates[0].name : undefined;
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
        group: 'Break',
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
            markers: (change, facts) => removal.markers(change, facts),
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
        markers: (change, facts) => ownMarkers(change, facts),
        matches: (_change, marker) => isBcChange(marker, 'PropBecomesRequired'),
    },
});

function declaredTypes<Kind extends MemberKind>(members: Kept<Kind>[], typeOf: (member: Member<Kind>) => string | null) {
    return members
        .map((member) => ({ ...keptElement(member), from: typeOf(member.was), to: typeOf(member.now) }))
        .filter(({ from, to }) => from !== to);
}

type TypedKind = 'prop' | 'computed' | 'method';

/** The kinds that declare a type, with where each one holds it. A method declares what it returns. */
const DECLARED_TYPE: { [Kind in TypedKind]: (member: Member<Kind>) => string | null } = {
    prop: ({ type }) => type,
    computed: ({ type }) => type,
    method: ({ returns }) => returns,
};

const TYPED_KINDS = Object.keys(DECLARED_TYPE) as TypedKind[];

function typeChanges<Kind extends TypedKind>(base: ComponentFacts, head: ComponentFacts, kind: Kind) {
    return declaredTypes(inBoth(base, head, kind), DECLARED_TYPE[kind] as (member: Member<Kind>) => string | null);
}

/** A type that both commits declare. A member that gets or loses its type is `type_widened`. */
function isRetyped({ from, to }: { from: string | null; to: string | null }): boolean {
    return from !== null && to !== null;
}

/** How a marker writes a type: a value of a marker ends at a space. */
function markerType(type: string | null): string {
    return (type ?? '').replace(/\s+/g, '');
}

/*
 * Whether another type still fits what plugins pass or read needs the type checker. The rules compare the text
 * of the declaration, so every other text is a break until a reviewer has checked it.
 */
const typeChanged = rule({
    id: 'type_changed',
    group: 'Break',
    changes: (base, head) => typeChanges(base, head, 'prop').filter(isRetyped),
    texts: ({ kind, name, from, to }) => ({
        explanation: `The declared type of the ${label(kind)} changed from ${from} to ${to}, so values that plugin templates pass as ${name} may no longer fit.`,
        fix: `pattern "Rename". Keep ${name} with the type ${from} and add a ${label(kind)} with the new type under a new name.`,
    }),
});

const RETURN_TYPE_MARKERS: readonly BcChangeKind[] = ['ReturnTypeNarrowing', 'ReturnTypeWidening'];

function returnTypeChanged(kind: 'computed' | 'method'): Rule {
    return rule({
        id: 'return_type_changed',
        group: 'Break',
        changes: (base, head) => typeChanges(base, head, kind).filter(isRetyped),
        texts: ({ name, from, to }, { flags }) => ({
            explanation: `The declared return type of the ${label(kind)} changed from ${from} to ${to}, so plugin code that uses the value of ${name} may no longer fit.`,
            fix: `keep the return type, or mark the ${label(kind)} @bc-change ReturnTypeNarrowing or ReturnTypeWidening version:${flags.majorVersion} type:${markerType(to)}.`,
        }),
        announcement: {
            rule: 'announced_change',
            subject: `The ${label(kind)}`,
            markers: (change, facts) => ownMarkers(change, facts),
            matches: ({ to }, marker) =>
                marker.tag === 'bc-change' &&
                RETURN_TYPE_MARKERS.includes(marker.kind) &&
                marker.parameters.type === markerType(to),
        },
    });
}

const typeWidened = rule({
    id: 'type_widened',
    group: 'Info',
    changes: (base, head) =>
        TYPED_KINDS.flatMap((kind) => typeChanges(base, head, kind)).filter((change) => !isRetyped(change)),
    texts: ({ kind, from, to }) => ({
        explanation:
            from === null
                ? `The ${label(kind)} now declares the type ${to}. It was untyped before.`
                : `The ${label(kind)} no longer declares a type. It was ${from} before.`,
    }),
});

/** A parameter in both commits: under the same name, or under another name at the same position. */
interface Placed {
    was: Parameter;
    now: Parameter;
}

/**
 * How the parameters of a method changed. They are matched by `parameterId`, because a plugin passes its
 * values by position: a parameter that holds another position at the head receives what the plugin meant for
 * another one.
 *
 * - `removed`: gone, with the kept parameters behind it that move up because of it.
 * - `appended`: new behind every parameter both commits have.
 * - `inserted`: new in front of a parameter both commits have, which then receives the value meant for it.
 * - `renamed`: at the same position under another name, so calls still pass the same value.
 * - `reordered`: the kept parameters stand in another order.
 */
interface ParameterChanges {
    method: Kept<'method'>;
    removed: { parameter: Parameter; moved: string[] }[];
    appended: Parameter[];
    inserted: { parameter: Parameter; before: string }[];
    renamed: Placed[];
    placed: Placed[];
    reordered: boolean;
    before: Parameter[];
    after: Parameter[];
}

function names(parameters: Parameter[]): string[] {
    return parameters.map(parameterId);
}

function compareParameters(method: Kept<'method'>, before: Parameter[], after: Parameter[]): ParameterChanges {
    const find = (parameters: Parameter[], parameter: Parameter) =>
        parameters.find((candidate) => parameterId(candidate) === parameterId(parameter));
    const kept = before.flatMap((was) => {
        const now = find(after, was);

        return now === undefined ? [] : [{ was, now }];
    });
    const renamed = before.flatMap((was, position): Placed[] => {
        const now = after.at(position);
        const isRename = now !== undefined && !find(after, was) && !find(before, now);

        return isRename ? [{ was, now }] : [];
    });
    const placed = [...kept, ...renamed];
    const isPlaced = (parameter: Parameter) => placed.some(({ now }) => now === parameter);
    const added = after.filter((parameter) => !find(before, parameter) && !isPlaced(parameter));
    const followerOf = (parameter: Parameter) => after.slice(after.indexOf(parameter) + 1).find(isPlaced);
    const keptOrder = (parameters: Parameter[]) =>
        names(parameters.filter((parameter) => find(before, parameter) && find(after, parameter)));

    return {
        method,
        removed: before
            .filter((was) => !find(after, was) && !renamed.some((rename) => rename.was === was))
            .map((parameter) => ({
                parameter,
                moved: kept
                    .filter(
                        ({ was, now }) =>
                            before.indexOf(was) > before.indexOf(parameter) && after.indexOf(now) < before.indexOf(was),
                    )
                    .map(({ now }) => parameterId(now)),
            })),
        appended: added.filter((parameter) => followerOf(parameter) === undefined),
        inserted: added.flatMap((parameter) => {
            const follower = followerOf(parameter);

            return follower === undefined ? [] : [{ parameter, before: parameterId(follower) }];
        }),
        renamed,
        placed,
        reordered: keptOrder(before).join() !== keptOrder(after).join(),
        before,
        after,
    };
}

/** The methods that both commits declare with parameters the source names. */
function parameterChanges(base: ComponentFacts, head: ComponentFacts): ParameterChanges[] {
    return inBoth(base, head, 'method').flatMap((method) =>
        method.was.parameters === null || method.now.parameters === null
            ? []
            : [compareParameters(method, method.was.parameters, method.now.parameters)],
    );
}

function isRequired({ optional, rest }: Parameter): boolean {
    return !optional && !rest;
}

function parameterElement(method: Kept<'method'>, parameter: Parameter) {
    const id = parameterId(parameter);

    return { ...keptElement(method, `/param:${id}`), parameter: id };
}

/** A change to how a method is called or what it returns, which every call notices. */
type SignatureChange =
    | { change: 'promise'; nowReturnsPromise: boolean }
    | { change: 'order'; before: string; after: string }
    | { change: 'inserted'; parameter: string; before: string };

function signatureTexts(change: SignatureChange, name: string): FindingTexts {
    switch (change.change) {
        case 'promise':
            return change.nowReturnsPromise
                ? {
                      explanation: `The method now returns a Promise, so plugin overrides that use the return value of $super('${name}') get a Promise instead.`,
                      fix: 'keep the method sync and add an async method under a new name.',
                  }
                : {
                      explanation: `The method no longer returns a Promise, so plugin overrides that chain a .then() on $super('${name}') fail.`,
                      fix: 'keep returning a Promise and add a sync method under a new name.',
                  };
        case 'order':
            return {
                explanation: `The parameters of the method changed their order from (${change.before}) to (${change.after}), so plugin calls and $super('${name}', ...) calls pass their values in the wrong place.`,
                fix: 'keep the order of the parameters and add a method under a new name.',
            };
        case 'inserted':
            return {
                explanation: `The new parameter ${change.parameter} stands in front of ${change.before}, so plugin calls and $super('${name}', ...) calls pass the value meant for ${change.before} as ${change.parameter}.`,
                fix: `add ${change.parameter} behind the existing parameters.`,
            };
        default:
            return change satisfies never;
    }
}

function withSignature<Element>(element: Element, signature: SignatureChange) {
    return { ...element, signature };
}

const methodSignatureChanged = rule({
    id: 'method_signature_changed',
    group: 'Break',
    changes: (base, head) => [
        ...inBoth(base, head, 'method')
            // Two declared return types are compared by `return_type_changed`, which a marker can announce.
            .filter(
                ({ was, now }) =>
                    (was.returns === null || now.returns === null) && was.returnsPromise !== now.returnsPromise,
            )
            .map((method) =>
                withSignature(keptElement(method), { change: 'promise', nowReturnsPromise: method.now.returnsPromise }),
            ),
        ...parameterChanges(base, head).flatMap(({ method, reordered, before, after, inserted }) => [
            ...(reordered
                ? [
                      withSignature(keptElement(method), {
                          change: 'order',
                          before: names(before).join(', '),
                          after: names(after).join(', '),
                      }),
                  ]
                : []),
            ...inserted.map(({ parameter, before: follower }) =>
                withSignature(parameterElement(method, parameter), {
                    change: 'inserted',
                    parameter: parameterId(parameter),
                    before: follower,
                }),
            ),
        ]),
    ],
    texts: ({ name, signature }) => signatureTexts(signature, name),
});

const paramMissing = rule({
    id: 'param_missing',
    group: 'Break',
    changes: (base, head) =>
        parameterChanges(base, head).flatMap(({ method, removed }) =>
            removed.map(({ parameter, moved }) => ({ ...parameterElement(method, parameter), moved })),
        ),
    texts: ({ name, parameter, moved }, { flags }) => ({
        explanation:
            moved.length === 0
                ? `The parameter ${parameter} is gone, so plugin overrides of ${name} that read it get undefined.`
                : `The parameter ${parameter} is gone and ${moved.join(', ')} moved up, so plugin calls and $super('${name}', ...) calls pass their values in the wrong place.`,
        fix: `pattern "Parameter removal". Keep ${parameter}, mark the method @bc-change ParameterRemoval version:${flags.majorVersion} parameter:${parameter}, and remove it in the major.`,
    }),
    announcement: {
        rule: 'announced_change',
        subject: 'The method',
        markers: (change, facts) => ownMarkers(change, facts),
        matches: ({ parameter }, marker) => isBcChange(marker, 'ParameterRemoval', parameter),
    },
});

const paramNowRequired = rule({
    id: 'param_now_required',
    group: 'Break',
    changes: (base, head) =>
        parameterChanges(base, head).flatMap(({ method, appended, placed }) => [
            ...appended.filter(isRequired).map((added) => ({ ...parameterElement(method, added), isNew: true })),
            ...placed
                .filter(({ was, now }) => !isRequired(was) && isRequired(now))
                .map(({ now }) => ({ ...parameterElement(method, now), isNew: false })),
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
        markers: (change, facts) => ownMarkers(change, facts),
        matches: ({ parameter }, marker) => isBcChange(marker, 'NewRequiredParameter', parameter),
    },
});

const paramAdded = rule({
    id: 'param_added',
    group: 'Addition',
    changes: (base, head) =>
        parameterChanges(base, head).flatMap(({ method, appended }) =>
            appended.filter((added) => !isRequired(added)).map((added) => parameterElement(method, added)),
        ),
    texts: ({ parameter }) => ({
        explanation: `A new optional parameter ${parameter}. Plugin overrides that call $super with explicit arguments do not pass it on.`,
    }),
});

const methodParamRenamed = rule({
    id: 'method_param_renamed',
    group: 'Info',
    changes: (base, head) =>
        parameterChanges(base, head).flatMap(({ method, renamed }) =>
            renamed.map(({ was, now }) => ({ ...parameterElement(method, now), was: parameterId(was) })),
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
        const known = new Set(ownMarkers(member.was, base).map(serialized));

        return ownMarkers(member.now, head)
            .filter((marker) => marker.tag === tag && !known.has(serialized(marker)))
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
    returnTypeChanged('computed'),
    returnTypeChanged('method'),
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
