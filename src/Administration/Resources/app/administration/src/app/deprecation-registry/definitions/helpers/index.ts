/**
 * @sw-package framework
 *
 * Builders for deprecation registry definitions: one per migration category, and one per usage step.
 * Component usage steps carry their own ESLint check and fix, which `no-deprecated-component-usage` runs.
 */

export type * from './types';
export { FIX_LEVELS } from './types';
export * from './migrations';
export * from './steps/simple-steps';
export { onlyAfterConversion } from './steps/only-after-conversion';
export { renameProp, invertBooleanProp, booleanPropToValue } from './steps/prop-renames';
export { removeProp, replaceProp } from './steps/prop-removals';
export { routerLinkClickHandler, aiBadgeTitleSlot } from './steps/prop-replacements';
export { mapPropValue } from './steps/map-prop-value';
export { mapOptionsPropKeys } from './steps/map-options-prop-keys';
export { missingProp } from './steps/missing-prop';
export { removeSlot, slotToProp, flagSlotForManualMove } from './steps/slots';
export { slotToItemsProp } from './steps/slot-to-items-prop';
export { renameEvent, removeEvent } from './steps/events';
export { renameVModelArgument } from './steps/rename-v-model-argument';
