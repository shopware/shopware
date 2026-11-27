/**
 * @sw-package framework
 *
 * What the break check compares about a unit in one flag state. Each part of a unit adds its facts here, so a
 * rule reads the same facts whatever it is about. A unit without a template, such as a service, has no blocks.
 */
import { perState } from '../../model/flag-states';
import type { FlagState } from '../../model/flag-states';
import type { UnitMembers } from '../../model/members';
import type { ComponentUnit } from '../../model/model';
import { blockStructureFactsOf } from './block-facts';
import type { BlockStructureFacts } from './block-facts';
import { memberFactsOf } from './member-facts';
import type { MemberFacts } from './member-facts';

/** @private */
export type UnitFacts = BlockStructureFacts & MemberFacts;

/**
 * @private
 *
 * The parts of a unit that facts are read from.
 */
export type FactSource = UnitMembers & Partial<Pick<ComponentUnit, 'blocks' | 'crossings'>>;

/**
 * @private
 *
 * The facts of the unit in each flag state.
 */
export function factsOf({ blocks = [], crossings = [], ...members }: FactSource): Record<FlagState, UnitFacts> {
    return perState((state) => ({
        ...blockStructureFactsOf({ blocks, crossings }, state),
        ...memberFactsOf(members, state),
    }));
}

/**
 * @private
 *
 * The facts of one unit in each flag state, at the base and at the head commit.
 */
export interface ChangedFacts {
    baseFacts: Record<FlagState, UnitFacts>;
    headFacts: Record<FlagState, UnitFacts>;
}
