/**
 * @sw-package framework
 *
 * Builders for deprecation registry definitions: one per migration category, and one per usage step.
 * Component usage steps carry their own ESLint check and fix, which `no-deprecated-component-usage` runs.
 */

export type * from './types';
export * from './migrations';
export * from './steps/simple-steps';
export { renameProp, invertBooleanProp, booleanPropToPropValue } from './steps/prop-renames';
export { removeProp, replaceProp } from './steps/prop-removals';
export { routerLinkClickHandler, aiBadgeTitleSlot } from './steps/prop-replacements';
export { replacePropValue } from './steps/replace-prop-value';
export { renameArrayPropKeys } from './steps/rename-array-prop-keys';
export { addPropWhenMissing } from './steps/add-prop-when-missing';
export { removeSlot, renameSlot, slotTextToProp, flagSlotForManualMove } from './steps/slots';
export { childElementsToArrayProp } from './steps/child-elements-to-array-prop';
export { propFromSource } from './steps/prop-from-source';
export { fromSlot, fromProp, firstFoundOf, fromText, fromAttribute } from './steps/sources';
export { renameEvent, removeEvent } from './steps/events';
export { renameVModelArgument } from './steps/rename-v-model-argument';
export { renameTemplateBlocks, removeTemplateBlocks, componentMembers } from './steps/step-lists';
