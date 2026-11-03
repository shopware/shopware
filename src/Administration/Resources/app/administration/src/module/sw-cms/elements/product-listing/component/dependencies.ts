/**
 * @sw-package discovery
 *
 * Everything sw-cms-el-product-listing needs from outside its mini-module.
 */
import { cmsPageStoreId } from '../../../store/cms-page.store';

/** @private */
export { default as cmsElementMixin } from '../../../mixin/sw-cms-element.mixin';

/** @private */
export const useCmsPageStore = () => Shopware.Store.get(cmsPageStoreId);
