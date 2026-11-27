/**
 * @sw-package framework
 *
 * What the break check compares about a component in one flag state. Each unit kind adds its part here, so a
 * rule reads the same facts whatever it is about.
 */
import { perState } from '../../model/flag-states';
import type { FlagState } from '../../model/flag-states';
import type { ComponentUnit } from '../../model/model';
import { blockStructureFactsOf } from './block-facts';
import type { BlockStructureFacts } from './block-facts';
import { memberFactsOf } from './member-facts';
import type { MemberFacts } from './member-facts';

/** @private */
export type ComponentFacts = BlockStructureFacts & MemberFacts;

/** The facts of the component that exist in `state`. */
function factsOf(unit: ComponentUnit, state: FlagState): ComponentFacts {
    return { ...blockStructureFactsOf(unit, state), ...memberFactsOf(unit, state) };
}

/**
 * @private
 *
 * The facts of one component in each flag state, at the base and at the head commit.
 */
export interface ChangedFacts {
    baseFacts: Record<FlagState, ComponentFacts>;
    headFacts: Record<FlagState, ComponentFacts>;
}

/** @private */
export function changedFactsOf(base: ComponentUnit, head: ComponentUnit): ChangedFacts {
    return {
        baseFacts: perState((state) => factsOf(base, state)),
        headFacts: perState((state) => factsOf(head, state)),
    };
}
