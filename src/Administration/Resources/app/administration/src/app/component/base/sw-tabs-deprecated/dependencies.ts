/**
 * @sw-package framework
 *
 * Everything sw-tabs-deprecated needs from outside its mini-module.
 */
import { tabsStoreId } from 'src/app/store/tabs';

/** @private */
export { default as Utils } from 'src/core/service/util.service';
/** @private */
export { feature as featureService } from 'src/app/service-names';

/** @private */
export const useTabsStore = () => Shopware.Store.get(tabsStoreId);
