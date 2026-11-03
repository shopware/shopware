/**
 * @sw-package framework
 *
 * Everything sw-meteor-card needs from outside its mini-module.
 */
/** @private */
export { feature as featureService } from 'src/app/service-names';
/** @private */
export { getTabItemsFromSlotContent, getTextFromSlotItem, triggerTabItemClick } from '../tab-slot-parser';
