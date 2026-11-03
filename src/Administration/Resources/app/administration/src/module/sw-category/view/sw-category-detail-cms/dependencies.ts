/**
 * @sw-package discovery
 *
 * Everything sw-category-detail-cms needs from outside its mini-module.
 */
import { cmsPageStoreId } from 'src/module/sw-cms/store/cms-page.store';
import { swCategoryDetailStoreId } from '../../page/sw-category-detail/store';

/** @private */
export { acl as aclService } from 'src/app/service-names';

/** @private */
export const useCategoryDetailStore = () => Shopware.Store.get(swCategoryDetailStoreId);

/** @private */
export const useCmsPageStore = () => Shopware.Store.get(cmsPageStoreId);
