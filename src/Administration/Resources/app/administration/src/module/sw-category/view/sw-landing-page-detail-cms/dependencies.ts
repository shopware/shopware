/**
 * @sw-package discovery
 *
 * Everything sw-landing-page-detail-cms needs from outside its mini-module.
 */
import { cmsPageStoreId } from 'src/module/sw-cms/store/cms-page.store';
import { swCategoryDetailStoreId } from '../../page/sw-category-detail/store';

/** @private */
export const useCategoryDetailStore = () => Shopware.Store.get(swCategoryDetailStoreId);

/** @private */
export const useCmsPageStore = () => Shopware.Store.get(cmsPageStoreId);
