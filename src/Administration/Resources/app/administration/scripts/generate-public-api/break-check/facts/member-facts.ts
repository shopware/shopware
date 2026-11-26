/**
 * @sw-package framework
 *
 * What the break check compares about the members of a component in one flag state.
 */
import type { FlagState } from '../../model/flag-states';
import { isKind, isTemplateMember, memberId, namePath } from '../../model/members';
import type { ComponentMembers, Member, MemberKind } from '../../model/members';
import type { ComponentUnit } from '../../model/model';
import type { SourceLine } from '../../model/source-line';

/**
 * @private
 *
 * The members of each kind by their name. A slot or a ref that the template has in several places has
 * one entry with all of them.
 */
export type MembersByKind = { [Kind in MemberKind]: ReadonlyMap<string, Member<Kind>[]> };

/**
 * @private
 *
 * `sections` are the lines of the options the source declares.
 */
export interface MemberFacts {
    members: MembersByKind;
    sections: ComponentMembers['sections'];
}

/**
 * @private
 *
 * The line a finding about a member that is gone points to: the option that held it, which has no line for a
 * member of the template.
 */
export function sectionLine(member: Member, { sections }: MemberFacts): SourceLine | undefined {
    return isTemplateMember(member) ? undefined : sections[member.kind];
}

/** The members of the kind by their name, each with all its places. */
function placesOf<Kind extends MemberKind>(members: Member[], kind: Kind): ReadonlyMap<string, Member<Kind>[]> {
    const places = new Map<string, Member<Kind>[]>();

    members.forEach((member) => {
        if (member.kind === kind) {
            places.set(member.name, [...(places.get(member.name) ?? []), member as Member<Kind>]);
        }
    });

    return places;
}

/**
 * @private
 *
 * The members of the component that exist in `state`.
 */
export function memberFactsOf(unit: ComponentUnit, state: FlagState): MemberFacts {
    const existing = unit.members.filter(({ states }) => states[state]);

    return {
        members: {
            mixin: placesOf(existing, 'mixin'),
            inject: placesOf(existing, 'inject'),
            provide: placesOf(existing, 'provide'),
            prop: placesOf(existing, 'prop'),
            emit: placesOf(existing, 'emit'),
            slot: placesOf(existing, 'slot'),
            data: placesOf(existing, 'data'),
            computed: placesOf(existing, 'computed'),
            method: placesOf(existing, 'method'),
            ref: placesOf(existing, 'ref'),
        },
        sections: unit.sections,
    };
}

/** A data key is overridden as a whole: a child that declares `pagination` drops every `pagination.*` it inherits. */
function overrideId(member: Member): string {
    return member.kind === 'data' ? memberId({ kind: 'data', name: namePath(member)[0] }) : memberId(member);
}

/** A method that only hands back `this.$super()` also returns a promise when the method it overrides does. */
function withParentResult(member: Member, fromParent: Member[]): Member {
    if (member.kind !== 'method' || !member.returnsSuper) {
        return member;
    }

    const overridden = fromParent.filter(isKind('method')).find(({ name }) => name === member.name);

    return overridden === undefined
        ? member
        : { ...member, returnsPromise: member.returnsPromise || overridden.returnsPromise };
}

function runtimeMembers(
    unit: ComponentUnit,
    components: ReadonlyMap<string, ComponentUnit>,
    seen: ReadonlySet<string>,
): Member[] {
    const parent = unit.extends === null || seen.has(unit.extends) ? undefined : components.get(unit.extends);

    if (parent === undefined) {
        return unit.members;
    }

    const fromParent = runtimeMembers(parent, components, new Set([...seen, parent.name])).filter(
        (member) => !isTemplateMember(member),
    );
    const own = unit.members.map((member) => withParentResult(member, fromParent));
    const overridden = new Set(own.map(overrideId));

    return [...own, ...fromParent.filter((member) => !overridden.has(overrideId(member)))];
}

/**
 * @private
 *
 * The component with the members it has at runtime. The options object of an extend child declares only what
 * the child overrides, the rest it inherits along its extend chain. Template members need no inheritance, they
 * are read from the merged template.
 */
export function withInheritedMembers(unit: ComponentUnit, components: ReadonlyMap<string, ComponentUnit>): ComponentUnit {
    return { ...unit, members: runtimeMembers(unit, components, new Set([unit.name])) };
}
