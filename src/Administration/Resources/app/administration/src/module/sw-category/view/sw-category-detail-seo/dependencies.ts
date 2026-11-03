/**
 * @sw-package discovery
 *
 * Everything sw-category-detail-seo needs from outside its mini-module.
 */
import { swCategoryDetailStoreId } from '../../page/sw-category-detail/store';

/** @private */
export { acl as aclService } from 'src/app/service-names';

/** @private */
export const useCategoryDetailStore = () => Shopware.Store.get(swCategoryDetailStoreId);
