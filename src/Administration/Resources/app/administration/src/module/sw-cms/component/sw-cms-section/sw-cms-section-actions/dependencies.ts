/**
 * @sw-package discovery
 *
 * Everything sw-cms-section-actions needs from outside its mini-module.
 */
import { cmsPageStoreId } from '../../../store/cms-page.store';

/** @private */
export { defineComponent as wrapComponentConfig } from 'vue';

/** @private */
export const useCmsPageStore = () => Shopware.Store.get(cmsPageStoreId);
