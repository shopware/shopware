/**
 * @sw-package discovery
 *
 * Everything sw-landing-page-detail-base needs from outside its mini-module.
 */
import { cmsPageStoreId } from 'src/module/sw-cms/store/cms-page.store';

/** @private */
export { default as placeholderMixin } from 'src/app/mixin/placeholder.mixin';
/** @private */
export { acl as aclService, repositoryFactory as repositoryFactoryService } from 'src/app/service-names';
/** @private */
export { mapPropertyErrors } from 'src/app/service/map-errors.service';

/** @private */
export { useSwCategoryDetailStore as useCategoryDetailStore } from '../../page/sw-category-detail/store';

/** @private */
export const useCmsPageStore = () => Shopware.Store.get(cmsPageStoreId);
