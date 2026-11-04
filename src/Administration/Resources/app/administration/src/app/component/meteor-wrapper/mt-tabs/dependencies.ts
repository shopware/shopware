/**
 * @sw-package framework
 *
 * Everything mt-tabs needs from outside its mini-module.
 */
import { tabsStoreId } from 'src/app/store/tabs';

/** @private */
export { default as MtTabs } from '@shopware-ag/meteor-component-library/dist/esm/MtTabs';
/** @private */
export type { TabItem } from '@shopware-ag/meteor-component-library/dist/esm/MtTabs';
/** @private */
export { defineComponent as wrapComponentConfig } from 'vue';
/** @private */
export type { TabItemEntry } from '../../../store/tabs';

/** @private */
export const useTabsStore = () => Shopware.Store.get(tabsStoreId);
