/**
 * @sw-package framework
 *
 * Everything sw-text-editor needs from outside its mini-module.
 */
import { cmsPageStoreId } from 'src/module/sw-cms/store/cms-page.store';

/** @private */
export { reactive } from 'vue';
/** @private */
export { feature as featureService } from 'src/app/service-names';

/** @private */
export const useCmsPageStore = () => Shopware.Store.get(cmsPageStoreId);
