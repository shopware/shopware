/**
 * @sw-package framework
 *
 * The members of a component besides its blocks: what the options object declares and what the template offers
 * to `this.$refs` and to slots. Every kind is a list of named members, so the printers and the
 * rules of the break check treat them alike and only differ in the details of a kind.
 */
import type { Marker } from '../../../eslint-rules/deprecation-rules/utils/api-markers';
import type { FlagStates } from './flag-states';
import type { SourceLine } from './source-line';

/**
 * @private
 *
 * A parameter of a method or of an emit validator. `type` is the annotation as the source writes it, `null`
 * without one. A destructured parameter is named `arg<position>`, and `pattern` holds its source text, which
 * identifies it at every position. It is `null` for a named parameter.
 */
export interface Parameter {
    name: string;
    pattern: string | null;
    type: string | null;
    default: DefaultValue;
    optional: boolean;
    rest: boolean;
}

/**
 * @private
 *
 * How the break check names a parameter: by its name, a destructured one by its pattern, which stays the same
 * at every position. The pattern is written without spaces, as a marker writes it: `parameter:{id,name}`.
 */
export function parameterId({ name, pattern }: Parameter): string {
    return pattern?.replace(/\s+/g, '') ?? name;
}

/**
 * @private
 *
 * The default of a prop or a parameter: a value as the source writes it, or a factory, whose body is not
 * compared.
 */
export type DefaultValue = { kind: 'none' } | { kind: 'factory' } | { kind: 'value'; text: string };

/**
 * What a kind of the options object knows besides the name. A type of `null` is untyped, which is what a JS
 * member is.
 *
 * - `inject.from`: the key the value is injected by.
 * - `prop.constructors`: the constructors Vue checks a value against, `prop.annotation` the type of a `PropType`
 *   on them. `propType` is what the prop declares of the two.
 * - `emit.payload`: the parameters of the validator, `null` for the array form, which declares none.
 * - `data.nested`: the value is an object literal, whose keys are members of their own named `parent.key`.
 * - `method.parameters`: `null` when the method is no function in the source, for example an imported one.
 * - `method.returnsPromise`: the method is `async`, declares a `Promise` return type, or every `return` hands
 *   back a promise it visibly builds, such as `.then()` or `Promise.all()`.
 * - `method.returnsSuper`: every `return` hands back `this.$super()` of the same method, so the method returns
 *   whatever the method it overrides returns.
 */
interface OptionDetails {
    mixin: object;
    inject: { from: string };
    provide: object;
    prop: { constructors: string | null; annotation: string | null; required: boolean; default: DefaultValue };
    emit: { payload: Parameter[] | null };
    data: { nested: boolean };
    computed: { type: string | null };
    method: { parameters: Parameter[] | null; returns: string | null; returnsPromise: boolean; returnsSuper: boolean };
}

/**
 * @private
 *
 * Where the template holds a member: the block around it, `null` outside of every block.
 */
export interface TemplatePlace {
    block: string | null;
}

/**
 * What a kind of the template knows besides the name and its place. `ref.list` says that the ref sits inside a
 * `v-for`, so Vue fills it with an array.
 */
interface TemplateDetails {
    slot: TemplatePlace & { scope: string[] };
    ref: TemplatePlace & { target: string; list: boolean };
}

type MemberDetails = OptionDetails & TemplateDetails;

/** @private */
export type OptionKind = keyof OptionDetails;

/** @private */
export type TemplateKind = keyof TemplateDetails;

/** @private */
export type MemberKind = OptionKind | TemplateKind;

/**
 * @private
 *
 * The kinds in the order of their sections in a report, which stand before or after the blocks. `fromTemplate`
 * kinds are read from the template a component renders, the others from its options object. `label` is how a
 * finding names a member of the kind.
 */
export const MEMBER_KINDS: {
    [Kind in MemberKind]: {
        heading: string;
        label: string;
        placement: 'beforeBlocks' | 'afterBlocks';
        fromTemplate: Kind extends TemplateKind ? true : false;
    };
} = {
    mixin: { heading: 'mixins', label: 'mixin', placement: 'beforeBlocks', fromTemplate: false },
    inject: { heading: 'inject', label: 'injection', placement: 'beforeBlocks', fromTemplate: false },
    provide: { heading: 'provide', label: 'provided value', placement: 'beforeBlocks', fromTemplate: false },
    prop: { heading: 'props', label: 'prop', placement: 'beforeBlocks', fromTemplate: false },
    emit: { heading: 'emits', label: 'event', placement: 'beforeBlocks', fromTemplate: false },
    slot: { heading: 'slots', label: 'slot', placement: 'beforeBlocks', fromTemplate: true },
    data: { heading: 'data', label: 'data property', placement: 'beforeBlocks', fromTemplate: false },
    computed: { heading: 'computed', label: 'computed property', placement: 'beforeBlocks', fromTemplate: false },
    method: { heading: 'methods', label: 'method', placement: 'beforeBlocks', fromTemplate: false },
    ref: { heading: 'refs', label: 'template ref', placement: 'afterBlocks', fromTemplate: true },
};

/** @private */
export const KINDS = Object.keys(MEMBER_KINDS) as MemberKind[];

interface MemberBase {
    name: string;
    at: SourceLine;
    /** The `@deprecated` and `@bc-change` markers in the JSDoc of the member. */
    markers: Marker[];
    /** The JSDoc of the member says `@private`. */
    isPrivate: boolean;
    states: FlagStates;
}

/**
 * @private
 *
 * One member of a component. `Member<'prop'>` is a prop with its details.
 */
export type Member<Kind extends MemberKind = MemberKind> = {
    [K in Kind]: MemberBase & { kind: K } & MemberDetails[K];
}[Kind];

/**
 * @private
 *
 * The keys from the top of `data()` down to the member: `pagination.limit` gives `['pagination', 'limit']`. The
 * name of every other member is a path of one key, also when it holds a dot, as an inject key may.
 */
export function namePath({ kind, name }: Pick<Member, 'kind' | 'name'>): string[] {
    return kind === 'data' ? name.split('.') : [name];
}

/**
 * @private
 *
 * The type a prop declares: its `PropType`, or the constructors without one.
 */
export function propType({ annotation, constructors }: Pick<Member<'prop'>, 'annotation' | 'constructors'>): string | null {
    return annotation ?? constructors;
}

/**
 * @private
 *
 * Narrows a member to its kind. The predicate is spelled with `Extract`, which is `Member<Kind>` for every kind.
 */
export function isKind<Kind extends MemberKind>(kind: Kind): (member: Member) => member is Extract<Member, { kind: Kind }> {
    return (member): member is Extract<Member, { kind: Kind }> => member.kind === kind;
}

/** @private */
export function isTemplateMember(member: Member): member is Member<TemplateKind> {
    return MEMBER_KINDS[member.kind].fromTemplate;
}

/**
 * @private
 *
 * The part of an element id that names the member below its component, `method:onSave`.
 */
export function memberId({ kind, name }: Pick<Member, 'kind' | 'name'>): string {
    return `${kind}:${name}`;
}

/**
 * @private
 *
 * The members of a component with what the generator could not read. A note is a line of the `notes` section
 * of the report, so a member the snapshot misses is visible there. `sections` holds the line of each option
 * the source declares, where a finding about a removed member points to.
 */
export interface ComponentMembers {
    members: Member[];
    notes: string[];
    sections: Partial<Record<OptionKind, SourceLine>>;
}
