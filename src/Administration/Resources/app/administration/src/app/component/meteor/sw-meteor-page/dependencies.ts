/**
 * @sw-package framework
 *
 * Everything sw-meteor-page needs from outside its mini-module.
 */
import { errorStoreId } from 'src/app/store/error';

/** @private */
export type { TabItem } from '@shopware-ag/meteor-component-library/dist/esm/MtTabs';
/** @private */
export { defineComponent as wrapComponentConfig } from 'vue';
/** @private */
export type { VNode } from 'vue';
/** @private */
export type { RouteLocationNamedRaw, RouteLocationRaw } from 'vue-router';
/** @private */
export type { ModuleManifest } from 'src/core/factory/module.factory';
/** @private */
export { feature as featureService } from 'src/app/service-names';
/** @private */
export { getTabItemsFromSlotContent, getTextFromSlotItem, triggerTabItemClick } from '../tab-slot-parser';

/** @private */
export const useErrorStore = () => Shopware.Store.get(errorStoreId);
