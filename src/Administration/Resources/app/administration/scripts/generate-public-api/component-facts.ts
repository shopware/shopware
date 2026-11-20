/**
 * @sw-package framework
 *
 * What the break check compares about a component in one flag state. Each unit kind adds its part here, so a
 * rule reads the same facts whatever it is about.
 */
import { blockStructureFactsOf } from './block-facts';
import type { BlockStructureFacts } from './block-facts';
import type { FlagState } from './flag-states';
import type { ComponentUnit } from './model';

/** @private */
export type ComponentFacts = BlockStructureFacts;

/**
 * @private
 *
 * The facts of the component that exist in `state`.
 */
export function factsOf(unit: ComponentUnit, state: FlagState): ComponentFacts {
    return blockStructureFactsOf(unit, state);
}
