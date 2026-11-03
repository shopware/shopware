/**
 * @sw-package fundamentals@discovery
 *
 * Everything sw-multi-snippet-drag-and-drop needs from outside its mini-module.
 */
/** @private */
export { defineComponent as wrapComponentConfig } from 'vue';
/** @private */
export type { ComponentObjectPropsOptions } from 'vue';
/** @private */
export type { DragConfig } from 'src/app/directive/dragdrop.directive';
/** @private */
export { feature as featureService } from 'src/app/service-names';
