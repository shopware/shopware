/**
 * @sw-package discovery
 *
 * Everything sw-cms-block-product-heading needs from outside its mini-module.
 */
import { cmsPageStoreId } from '../../../../store/cms-page.store';

/** @private */
export const useCmsPageStore = () => Shopware.Store.get(cmsPageStoreId);
