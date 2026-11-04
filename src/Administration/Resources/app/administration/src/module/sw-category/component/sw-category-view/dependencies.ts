/**
 * @sw-package discovery
 *
 * Everything sw-category-view needs from outside its mini-module.
 */
import { cmsPageStoreId } from 'src/module/sw-cms/store/cms-page.store';

/** @private */
export { acl as aclService, feature as featureService } from 'src/app/service-names';
/** @private */
export { mapPageErrors } from 'src/app/service/map-errors.service';
/** @private */
export { default as errorConfig } from '../../error-config.json';

/** @private */
export { useSwCategoryDetailStore as useCategoryDetailStore } from '../../page/sw-category-detail/store';

/** @private */
export const useCmsPageStore = () => Shopware.Store.get(cmsPageStoreId);
