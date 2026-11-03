/**
 * @sw-package discovery
 *
 * Everything sw-category-view needs from outside its mini-module.
 */
import { cmsPageStoreId } from 'src/module/sw-cms/store/cms-page.store';
import { swCategoryDetailStoreId } from '../../page/sw-category-detail/store';

/** @private */
export { acl as aclService, feature as featureService } from 'src/app/service-names';
/** @private */
export { mapPageErrors } from 'src/app/service/map-errors.service';
/** @private */
export { default as errorConfig } from '../../error-config.json';

/** @private */
export const useCategoryDetailStore = () => Shopware.Store.get(swCategoryDetailStoreId);

/** @private */
export const useCmsPageStore = () => Shopware.Store.get(cmsPageStoreId);
