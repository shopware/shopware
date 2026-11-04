/**
 * @sw-package discovery
 *
 * Everything sw-landing-page-view needs from outside its mini-module.
 */
import { cmsPageStoreId } from 'src/module/sw-cms/store/cms-page.store';

/** @private */
export { default as placeholderMixin } from 'src/app/mixin/placeholder';
/** @private */
export { acl as aclService, feature as featureService } from 'src/app/service-names';

/** @private */
export { useSwCategoryDetailStore as useCategoryDetailStore } from '../../page/sw-category-detail/store';

/** @private */
export const useCmsPageStore = () => Shopware.Store.get(cmsPageStoreId);
