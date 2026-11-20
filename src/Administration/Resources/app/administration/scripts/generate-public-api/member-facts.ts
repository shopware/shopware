/**
 * @sw-package framework
 *
 * What the break check compares about the members of a component in one flag state.
 */
import type { SourceLine } from './block-tree';
import { isTemplateMember } from './component-members';
import type { ComponentMembers, Member, MemberKind } from './component-members';
import type { FlagState } from './flag-states';
import type { ComponentUnit } from './model';

/**
 * @private
 *
 * The members of each kind by their name. A slot, a ref or a class that the template has in several places has
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
            class: placesOf(existing, 'class'),
        },
        sections: unit.sections,
    };
}
