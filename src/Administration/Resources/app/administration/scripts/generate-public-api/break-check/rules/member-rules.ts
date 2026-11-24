/**
 * @sw-package framework
 *
 * The rules of the break check for the members of a unit. A member that is gone or new is one rule per kind,
 * built from the table of kinds. The rules below them are about what only one kind has, such as the parameters
 * of a method. Each rule says in its texts what a plugin notices of the change, in the words of `plugin-use.ts`
 * where a component and a service differ.
 */
import type { BcChangeKind, Marker } from '../../../../eslint-rules/deprecation-rules/utils/api-markers';
import type { FlagContext } from '../../model/flag-states';
import {
    CALLABLE_KINDS,
    isTemplateMember,
    KINDS,
    MEMBER_KINDS,
    memberId,
    namePath,
    parameterId,
    propType,
} from '../../model/members';
import type { CallableKind, DefaultValue, Member, MemberKind, Parameter } from '../../model/members';
import type { SourceLine } from '../../model/source-line';
import { coveringDeprecation } from '../facts/block-facts';
import type { BlockFacts } from '../facts/block-facts';
import type { UnitFacts } from '../facts/unit-facts';
import { sectionLine } from '../facts/member-facts';
import type { PluginUse } from '../plugin-use';
import { rule, worded } from './rule';
import {
    ADDITION_FIX,
    additionText,
    announcesBecomingPrivate,
    becamePrivateTexts,
    deprecation,
    inMajorState,
    isBcChange,
    removalFix,
} from './texts';
import type { FindingTexts, Rule, RuleDefinition, TextContext, Worded } from './rule';

/** The first place of every member of the kind. What a rule compares of all places, it reads from the facts. */
function membersOf<Kind extends MemberKind>(facts: UnitFacts, kind: Kind): Member<Kind>[] {
    return [...facts.members[kind].values()].map(([member]) => member);
}

/** A member that both commits have. */
interface Kept<Kind extends MemberKind = MemberKind> {
    was: Member<Kind>;
    now: Member<Kind>;
}

function inBoth<Kind extends MemberKind>(base: UnitFacts, head: UnitFacts, kind: Kind): Kept<Kind>[] {
    return membersOf(base, kind).flatMap((was) => {
        const [now] = head.members[kind].get(was.name) ?? [];

        return now === undefined ? [] : [{ was, now }];
    });
}

/** The object a nested data key stands in, `null` for every other member. */
function parentName(member: Pick<Member, 'kind' | 'name'>): string | null {
    const path = namePath(member);

    return path.length > 1 ? path.slice(0, -1).join('.') : null;
}

/** A nested data key goes with its object, when the change is about that object too. */
function withoutNestedKeys<Item>(changed: Item[], memberOf: (item: Item) => { kind: MemberKind; name: string }): Item[] {
    const changedNames = new Set(changed.map((item) => memberOf(item).name));

    return changed.filter((item) => {
        const parent = parentName(memberOf(item));

        return parent === null || !changedNames.has(parent);
    });
}

function onlyIn<Kind extends MemberKind>(facts: UnitFacts, other: UnitFacts, kind: Kind): Member<Kind>[] {
    return withoutNestedKeys(
        membersOf(facts, kind).filter(({ name }) => !other.members[kind].has(name)),
        ({ name }) => ({ kind, name }),
    );
}

/** The member as the element of a change, or something `below` it such as `/param:item`. */
function memberElement<Kind extends MemberKind>(member: Member<Kind>, below = '') {
    const { name, at, isPrivate } = member;
    const kind: Kind = member.kind;

    return { element: `${memberId({ kind, name })}${below}`, at, isPrivate, kind, name };
}

/**
 * The element of a change to a member that both commits have. Its line is the one at the head. Whether it is
 * private is decided at the base: a member that becomes `@private` with the change was public API until then.
 */
function keptElement<Kind extends MemberKind>({ was, now }: Kept<Kind>, below = '') {
    return { ...memberElement(now, below), isPrivate: was.isPrivate };
}

/** The markers in the comment above the member, in all its places. */
function ownMarkers({ kind, name }: Pick<Member, 'kind' | 'name'>, facts: UnitFacts): Marker[] {
    return facts.members[kind].get(name)?.flatMap(({ markers }) => markers) ?? [];
}

/** The markers that announce a change to a member: its own and, for a nested data key, those of its objects. */
function announcingMarkers(member: Pick<Member, 'kind' | 'name'>, facts: UnitFacts): Marker[] {
    return namePath(member).flatMap((_key, index, path) =>
        ownMarkers({ kind: member.kind, name: path.slice(0, index + 1).join('.') }, facts),
    );
}

function serialized(marker: Marker): string {
    return JSON.stringify(marker);
}

/**
 * A slot or a ref carries no comment of its own. It goes away with a deprecated block around it, when it does so
 * in every place.
 */
function enclosingDeprecations({ kind, name }: Pick<Member, 'kind' | 'name'>, facts: UnitFacts): Marker[] {
    return coveringDeprecation(
        (facts.members[kind].get(name) ?? [])
            .filter(isTemplateMember)
            .map(({ block }) => (block === null ? [] : (facts.blocks.get(block)?.deprecations ?? []))),
    );
}

function label(kind: MemberKind): string {
    return MEMBER_KINDS[kind].label;
}

/** What a finding about a removed member says, besides what a plugin notices of it, and what announces it. */
interface RemovalPolicy {
    markers: (member: Pick<Member, 'kind' | 'name'>, facts: UnitFacts) => Marker[];
    /** How the finding says that the member is gone. */
    gone: (kind: MemberKind, name: string) => string;
    fix: (removed: { kind: MemberKind; name: string; rename: string | undefined }, flags: FlagContext) => string;
    /** What the author of a new member of the kind can do about it being public. */
    additionFix?: string;
}

/** A member of the options object can stay beside its replacement, with a marker that announces its removal. */
const IN_SCRIPT: RemovalPolicy = {
    markers: announcingMarkers,
    gone: (kind) => `The ${label(kind)} is gone`,
    fix: ({ kind, name, rename }, flags) =>
        rename === undefined
            ? removalFix(label(kind), flags)
            : `pattern "Rename". Keep ${name} and mark it ${deprecation(flags, `Use {@link ${rename}} instead.`)}.`,
    additionFix: ADDITION_FIX,
};

/** The line of a finding about a member of the template is that of a block, so the finding names the member. */
const IN_TEMPLATE: RemovalPolicy = {
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
    { effect: (name: string, use: PluginUse) => string; removal: RemovalPolicy; isAddition: boolean }
> = {
    mixin: {
        effect: (name) => `plugin overrides that use a member of ${name} through this fail`,
        removal: IN_SCRIPT,
        isAddition: true,
    },
    inject: {
        effect: (name) => `plugin overrides that read this.${name} get undefined`,
        removal: IN_SCRIPT,
        isAddition: true,
    },
    provide: {
        effect: (name) => `plugin components that inject ${name} get undefined`,
        removal: IN_SCRIPT,
        isAddition: true,
    },
    prop: {
        effect: (name) =>
            `a value that plugin templates pass as ${name} falls through as an HTML attribute, and plugin overrides that read this.${name} get undefined`,
        removal: IN_SCRIPT,
        isAddition: true,
    },
    emit: {
        effect: (name) => `listeners that plugin templates add with @${name} are no longer called by the component`,
        removal: IN_SCRIPT,
        isAddition: true,
    },
    slot: {
        effect: (name) => `content that plugin templates pass to the slot ${name} is no longer rendered`,
        removal: IN_TEMPLATE,
        isAddition: true,
    },
    data: {
        effect: (name) => `plugin overrides that read or set this.${name} work on a value the component ignores`,
        removal: IN_SCRIPT,
        isAddition: true,
    },
    computed: {
        effect: (name) => `plugin overrides that call $super('${name}') throw at runtime`,
        removal: IN_SCRIPT,
        isAddition: true,
    },
    state: {
        effect: (name, use) => use.withoutProperty(name),
        removal: IN_SCRIPT,
        isAddition: true,
    },
    getter: {
        effect: (name, use) => use.withoutProperty(name),
        removal: IN_SCRIPT,
        isAddition: true,
    },
    property: {
        effect: (name, use) => use.withoutProperty(name),
        removal: IN_SCRIPT,
        isAddition: true,
    },
    method: {
        effect: (name, use) => use.withoutMethod(name),
        removal: IN_SCRIPT,
        isAddition: true,
    },
    action: {
        effect: (name, use) => use.withoutMethod(name),
        removal: IN_SCRIPT,
        isAddition: true,
    },
    ref: {
        effect: (name) => `plugin overrides that read this.$refs.${name} get undefined`,
        removal: IN_TEMPLATE,
        isAddition: false,
    },
};

/** Everything of a member besides its name and where it stands. */
function shapeOf(member: Member): string {
    return JSON.stringify({ ...member, name: null, at: null, markers: null, states: null });
}

/** A new member of the same kind that looks the same is probably the removed one under a new name. */
function possibleRename(removed: Member, base: UnitFacts, head: UnitFacts): string | undefined {
    const candidates = onlyIn(head, base, removed.kind).filter((added) => shapeOf(added) === shapeOf(removed));

    return candidates.length === 1 ? candidates[0].name : undefined;
}

/** The block, or the closest block around it at the base, that the head still has. */
function remainingBlock(block: string | null, base: UnitFacts, head: UnitFacts): BlockFacts | undefined {
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
function lineOfRemoved(removed: Member, base: UnitFacts, head: UnitFacts): SourceLine | undefined {
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
        texts: ({ name, rename }, { flags, states, use }) => ({
            explanation: `${removal.gone(kind, name)}${inMajorState(states)}, so ${effect(name, use)}.`,
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
            explanation: additionText(label(kind)),
            ...(additionFix === undefined ? {} : { fix: additionFix }),
        }),
    });
}

function memberBecamePrivate(kind: MemberKind): Rule {
    return rule({
        id: 'visibility_became_private',
        group: 'Break',
        changes: (base, head) =>
            withoutNestedKeys(
                inBoth(base, head, kind).filter(({ was, now }) => !was.isPrivate && now.isPrivate),
                ({ now }) => now,
            ).map((member) => keptElement(member)),
        texts: ({ name }, { flags }) => becamePrivateTexts(label(kind), name, flags),
        announcement: {
            rule: 'announced_change',
            subject: `The ${label(kind)}`,
            markers: (change, facts) => announcingMarkers(change, facts),
            matches: (_change, marker) => announcesBecomingPrivate(marker),
        },
    });
}

const propNowRequired = rule({
    id: 'prop_now_required',
    group: 'Break',
    changes: (base, head) =>
        inBoth(base, head, 'prop')
            .filter(({ was, now }) => !was.required && now.required)
            .map((prop) => keptElement(prop)),
    texts: ({ name }, { unit: component, flags }) => ({
        explanation: `The prop is now required, so plugin templates that render ${component} without ${name} fail prop validation.`,
        fix: `pattern "Prop becomes required". Keep ${name} optional, call triggerDeprecationOrThrow when it is missing, and mark it @bc-change PropBecomesRequired version:${flags.majorVersion}.`,
    }),
    announcement: {
        rule: 'announced_change',
        subject: 'The prop',
        markers: (change, facts) => announcingMarkers(change, facts),
        matches: (_change, marker) => isBcChange(marker, 'PropBecomesRequired'),
    },
});

/** A prop that gets a `PropType` on the constructors it had. Vue checks a value against them as before. */
function isAnnotated({ was, now }: Kept): boolean {
    return (
        was.kind === 'prop' &&
        now.kind === 'prop' &&
        was.constructors === now.constructors &&
        was.annotation === null &&
        now.annotation !== null
    );
}

/** How the declared type of a member changed. Only `retyped` can mean that a plugin gets another kind of value. */
type Transition = 'retyped' | 'gained' | 'lost' | 'annotated';

function transitionOf(member: Kept, from: string | null, to: string | null): Transition {
    if (isAnnotated(member)) {
        return 'annotated';
    }

    if (from === null) {
        return 'gained';
    }

    return to === null ? 'lost' : 'retyped';
}

/** A default that callers no longer fall back to: gone, or another value. */
interface DefaultChange {
    from: string | null;
    to: string | null;
}

function valueOf(value: DefaultValue): string | null {
    return value.kind === 'value' ? value.text : null;
}

/** A factory has no value to compare, so only its removal counts. */
function defaultChange(was: DefaultValue, now: DefaultValue): DefaultChange | null {
    const removed = was.kind !== 'none' && now.kind === 'none';
    const changed = was.kind === 'value' && now.kind === 'value' && was.text !== now.text;

    return removed || changed ? { from: valueOf(was), to: valueOf(now) } : null;
}

/** The default as a marker names it: without spaces, and `undefined` for a default that is gone. */
function markerDefault(value: string | null): string {
    return markerType(value ?? 'undefined');
}

function markerKeysText(keys: Record<string, string>): string {
    return Object.entries(keys)
        .map(([key, value]) => `${key}:${value}`)
        .join(' ');
}

/**
 * The rule for a changed default. `subject` names what holds the default, `callers` who falls back to it, and
 * `marker` the `@bc-change` on the member that announces the new default.
 */
function defaultChanged<Change extends DefaultChange & Pick<Member, 'kind' | 'name'>>({
    id,
    changes,
    subject,
    callers,
    marker,
}: {
    id: string;
    changes: RuleDefinition<Change>['changes'];
    subject: (change: Change) => string;
    callers: (change: Change, context: TextContext) => string;
    marker: { kind: BcChangeKind; holder: Worded<Change>; keys: (change: Change) => Record<string, string> };
}): Rule {
    const markerKeys = (change: Change) => ({ ...marker.keys(change), default: markerDefault(change.to) });

    return rule({
        id,
        group: 'Break',
        changes,
        texts: (change, context) => ({
            explanation:
                change.to === null
                    ? `The ${subject(change)} no longer has a default, so ${callers(change, context)} leave it undefined.`
                    : `The default of the ${subject(change)} changed from ${change.from} to ${change.to}, so ${callers(change, context)} get another value.`,
            fix: `keep the default, or mark the ${worded(marker.holder, change)} @bc-change ${marker.kind} version:${context.flags.majorVersion} ${markerKeysText(markerKeys(change))}.`,
        }),
        announcement: {
            rule: 'announced_change',
            subject: (change) => `The ${worded(marker.holder, change)}`,
            markers: (change, facts) => announcingMarkers(change, facts),
            matches: (change, found) => isBcChange(found, marker.kind, markerKeys(change)),
        },
    });
}

/** A prop that became required needs no default, which `prop_now_required` reports. */
const propDefaultChanged = defaultChanged({
    id: 'prop_default_changed',
    changes: (base, head) =>
        inBoth(base, head, 'prop').flatMap((prop) => {
            const change = prop.now.required ? null : defaultChange(prop.was.default, prop.now.default);

            return change === null ? [] : [{ ...keptElement(prop), ...change }];
        }),
    subject: () => 'prop',
    callers: ({ name }, { unit: component }) => `plugin templates that render ${component} without ${name}`,
    marker: { kind: 'PropDefaultValueChange', holder: 'prop', keys: () => ({}) },
});

function declaredTypes<Kind extends MemberKind>(members: Kept<Kind>[], typeOf: (member: Member<Kind>) => string | null) {
    return members.flatMap((member) => {
        const from = typeOf(member.was);
        const to = typeOf(member.now);

        return from === to ? [] : [{ ...keptElement(member), from, to, transition: transitionOf(member, from, to) }];
    });
}

/** The kinds that declare a type: those whose details hold a `type` or a `returns`, and a prop, which holds its type in two places. */
type TypedKind =
    | {
          [Kind in MemberKind]: Member<Kind> extends { type: string | null } | { returns: string | null } ? Kind : never;
      }[MemberKind]
    | 'prop';

/** What a plugin holds of a member whose type is no return type: the value it reads or sets. */
function heldValue(name: string): string {
    return `plugin code that reads or sets ${name} may no longer fit`;
}

/**
 * Where each typed kind holds its type, and what another type means: `returned` for a kind whose type is what
 * it returns, which `return_type_changed` reports, and otherwise who holds a value of the old type, for
 * `type_changed`. A kind that is missing here does not compile.
 */
const TYPED: {
    [Kind in TypedKind]: {
        typeOf: (member: Member<Kind>) => string | null;
        retyped: 'returned' | ((name: string) => string);
    };
} = {
    prop: { typeOf: propType, retyped: (name) => `values that plugin templates pass as ${name} may no longer fit` },
    computed: { typeOf: ({ type }) => type, retyped: 'returned' },
    state: { typeOf: ({ type }) => type, retyped: heldValue },
    getter: { typeOf: ({ type }) => type, retyped: 'returned' },
    property: { typeOf: ({ type }) => type, retyped: heldValue },
    method: { typeOf: ({ returns }) => returns, retyped: 'returned' },
    action: { typeOf: ({ returns }) => returns, retyped: 'returned' },
};

const TYPED_KINDS = Object.keys(TYPED) as TypedKind[];

function typeChanges<Kind extends TypedKind>(base: UnitFacts, head: UnitFacts, kind: Kind) {
    return declaredTypes(inBoth(base, head, kind), TYPED[kind].typeOf as (member: Member<Kind>) => string | null);
}

function isRetyped({ transition }: { transition: Transition }): boolean {
    return transition === 'retyped';
}

/** How a marker writes a type: a value of a marker ends at a space. */
function markerType(type: string | null): string {
    return (type ?? '').replace(/\s+/g, '');
}

/*
 * Whether another type still fits what plugins pass or read needs the type checker. The rules compare the text
 * of the declaration, so every other text is a break until a reviewer has checked it.
 */
function typeChanged(kind: TypedKind, holder: (name: string) => string): Rule {
    return rule({
        id: 'type_changed',
        group: 'Break',
        changes: (base, head) => typeChanges(base, head, kind).filter(isRetyped),
        texts: ({ name, from, to }) => ({
            explanation: `The declared type of the ${label(kind)} changed from ${from} to ${to}, so ${holder(name)}.`,
            fix: `pattern "Rename". Keep ${name} with the type ${from} and add a ${label(kind)} with the new type under a new name.`,
        }),
    });
}

const RETURN_TYPE_MARKERS: readonly BcChangeKind[] = ['ReturnTypeNarrowing', 'ReturnTypeWidening'];

function returnTypeChanged(kind: TypedKind): Rule {
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
            markers: (change, facts) => announcingMarkers(change, facts),
            matches: ({ to }, marker) =>
                RETURN_TYPE_MARKERS.some((markerKind) => isBcChange(marker, markerKind, { type: markerType(to) })),
        },
    });
}

function retyped(kind: TypedKind): Rule {
    const holder = TYPED[kind].retyped;

    return holder === 'returned' ? returnTypeChanged(kind) : typeChanged(kind, holder);
}

const WIDENED_TEXT: Record<
    Exclude<Transition, 'retyped'>,
    (subject: string, from: string | null, to: string | null) => string
> = {
    annotated: (subject, from, to) =>
        `The ${subject} now declares the type ${to}. Vue still checks a value against ${from}, as before.`,
    gained: (subject, _from, to) => `The ${subject} now declares the type ${to}. It was untyped before.`,
    lost: (subject, from) => `The ${subject} no longer declares a type. It was ${from} before.`,
};

const typeWidened = rule({
    id: 'type_widened',
    group: 'Info',
    changes: (base, head) =>
        TYPED_KINDS.flatMap((kind) => typeChanges(base, head, kind)).flatMap((change) =>
            change.transition === 'retyped' ? [] : [{ ...change, transition: change.transition }],
        ),
    texts: ({ kind, from, to, transition }) => ({ explanation: WIDENED_TEXT[transition](label(kind), from, to) }),
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
    method: Kept<CallableKind>;
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

function compareParameters(method: Kept<CallableKind>, before: Parameter[], after: Parameter[]): ParameterChanges {
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

/** The methods and actions that both commits declare. */
function callablesInBoth(base: UnitFacts, head: UnitFacts): Kept<CallableKind>[] {
    return CALLABLE_KINDS.flatMap((kind): Kept<CallableKind>[] => inBoth(base, head, kind));
}

/** Those of them with parameters the source names. */
function parameterChanges(base: UnitFacts, head: UnitFacts): ParameterChanges[] {
    return callablesInBoth(base, head).flatMap((method) =>
        method.was.parameters === null || method.now.parameters === null
            ? []
            : [compareParameters(method, method.was.parameters, method.now.parameters)],
    );
}

function isRequired({ optional, rest }: Parameter): boolean {
    return !optional && !rest;
}

function parameterElement(method: Kept<CallableKind>, parameter: Parameter) {
    const id = parameterId(parameter);

    return { ...keptElement(method, `/param:${id}`), parameter: id };
}

/** A change to how a method is called or what it returns, which every call notices. */
type SignatureChange =
    | { change: 'promise'; nowReturnsPromise: boolean }
    | { change: 'order'; before: string; after: string }
    | { change: 'inserted'; parameter: string; before: string };

function signatureTexts(change: SignatureChange, kind: CallableKind, name: string, use: PluginUse): FindingTexts {
    const callable = label(kind);

    switch (change.change) {
        case 'promise':
            return change.nowReturnsPromise
                ? {
                      explanation: `The ${callable} now returns a Promise, so ${use.gettingPromise(name)}.`,
                      fix: `keep the ${callable} sync and add an async ${callable} under a new name.`,
                  }
                : {
                      explanation: `The ${callable} no longer returns a Promise, so ${use.losingPromise(name)}.`,
                      fix: `keep returning a Promise and add a sync ${callable} under a new name.`,
                  };
        case 'order':
            return {
                explanation: `The parameters of the ${callable} changed their order from (${change.before}) to (${change.after}), so ${use.calls(name)} pass their values in the wrong place.`,
                fix: `keep the order of the parameters and add a ${callable} under a new name.`,
            };
        case 'inserted':
            return {
                explanation: `The new parameter ${change.parameter} stands in front of ${change.before}, so ${use.calls(name)} pass the value meant for ${change.before} as ${change.parameter}.`,
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
        ...callablesInBoth(base, head).flatMap((method) => {
            const { was, now } = method;
            // Two declared return types are compared by `return_type_changed`, which a marker can announce.
            const isDeclaredTwice = was.returns !== null && now.returns !== null;

            return isDeclaredTwice ||
                was.returnsPromise === null ||
                now.returnsPromise === null ||
                was.returnsPromise === now.returnsPromise
                ? []
                : [withSignature(keptElement(method), { change: 'promise', nowReturnsPromise: now.returnsPromise })];
        }),
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
    texts: ({ kind, name, signature }, { use }) => signatureTexts(signature, kind, name, use),
});

const paramMissing = rule({
    id: 'param_missing',
    group: 'Break',
    changes: (base, head) =>
        parameterChanges(base, head).flatMap(({ method, removed }) =>
            removed.map(({ parameter, moved }) => ({ ...parameterElement(method, parameter), moved })),
        ),
    texts: ({ kind, name, parameter, moved }, { flags, use }) => ({
        explanation:
            moved.length === 0
                ? `The parameter ${parameter} is gone, so ${use.withoutParameter(name)}.`
                : `The parameter ${parameter} is gone and ${moved.join(', ')} moved up, so ${use.calls(name)} pass their values in the wrong place.`,
        fix: `pattern "Parameter removal". Keep ${parameter}, mark the ${label(kind)} @bc-change ParameterRemoval version:${flags.majorVersion} parameter:${parameter}, and remove it in the major.`,
    }),
    announcement: {
        rule: 'announced_change',
        subject: ({ kind }) => `The ${label(kind)}`,
        markers: (change, facts) => announcingMarkers(change, facts),
        matches: ({ parameter }, marker) => isBcChange(marker, 'ParameterRemoval', { parameter }),
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
    texts: ({ kind, name, parameter, isNew }, { flags, use }) => ({
        explanation: isNew
            ? `The ${label(kind)} has a new required parameter ${parameter}, so ${use.calls(name)} that pass the old arguments leave it undefined.`
            : `The parameter ${parameter} lost its default, so ${use.calls(name)} that do not pass it leave it undefined.`,
        fix: `pattern "New required parameter". Keep ${parameter} optional, call triggerDeprecationOrThrow when it is missing, and mark the ${label(kind)} @bc-change NewRequiredParameter version:${flags.majorVersion} parameter:${parameter}.`,
    }),
    announcement: {
        rule: 'announced_change',
        subject: ({ kind }) => `The ${label(kind)}`,
        markers: (change, facts) => announcingMarkers(change, facts),
        matches: ({ parameter }, marker) => isBcChange(marker, 'NewRequiredParameter', { parameter }),
    },
});

/** A parameter that became required needs no default, which `param_now_required` reports. */
const paramDefaultChanged = defaultChanged({
    id: 'param_default_changed',
    changes: (base, head) =>
        parameterChanges(base, head).flatMap(({ method, placed }) =>
            placed.flatMap(({ was, now }) => {
                const change = isRequired(now) ? null : defaultChange(was.default, now.default);

                return change === null ? [] : [{ ...parameterElement(method, now), ...change }];
            }),
        ),
    subject: ({ parameter }) => `parameter ${parameter}`,
    callers: ({ name }, { use }) => `${use.calls(name)} that do not pass it`,
    marker: {
        kind: 'ParameterDefaultValueChange',
        holder: ({ kind }) => label(kind),
        keys: ({ parameter }) => ({ parameter }),
    },
});

const paramAdded = rule({
    id: 'param_added',
    group: 'Addition',
    changes: (base, head) =>
        parameterChanges(base, head).flatMap(({ method, appended }) =>
            appended.filter((added) => !isRequired(added)).map((added) => parameterElement(method, added)),
        ),
    texts: ({ parameter }, { use }) => ({
        explanation: [`A new optional parameter ${parameter}.`, use.newOptionalParameter].filter(Boolean).join(' '),
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
function refTargets(facts: UnitFacts, name: string): string {
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

function newMarkers(base: UnitFacts, head: UnitFacts, tag: Marker['tag']) {
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
    // A member of the template carries no comment of its own, so it cannot become @private.
    ...KINDS.filter((kind) => !MEMBER_KINDS[kind].fromTemplate).map(memberBecamePrivate),
    propNowRequired,
    propDefaultChanged,
    ...TYPED_KINDS.map(retyped),
    typeWidened,
    methodSignatureChanged,
    paramMissing,
    paramNowRequired,
    paramDefaultChanged,
    paramAdded,
    methodParamRenamed,
    refTargetChanged,
    markerAdded('deprecation_added', 'deprecated', 'its removal'),
    markerAdded('bc_change_added', 'bc-change', 'this change'),
];
