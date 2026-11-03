/**
 * @sw-package discovery
 *
 * Everything sw-cms-sidebar needs from outside its mini-module.
 */
import { cmsPageStoreId } from '../../store/cms-page.store';

/** @private */
export { defineComponent as wrapComponentConfig } from 'vue';
/** @private */
export { default as Criteria } from 'src/core/data/criteria.data';
/** @private */
export { default as placeholderMixin } from 'src/app/mixin/placeholder.mixin';
/** @private */
export {
    acl as aclService,
    cmsBlockFavorites as cmsBlockFavoritesService,
    cmsPageTypeService,
    cmsService,
    feature as featureService,
    repositoryFactory as repositoryFactoryService,
} from 'src/app/service-names';
/** @private */
export { mapPropertyErrors } from 'src/app/service/map-errors.service';
/** @private */
export { default as CMS } from '../../constant/sw-cms.constant';
/** @private */
export { default as cmsStateMixin } from '../../mixin/sw-cms-state.mixin';
/** @private */
export type { PageType } from '../../service/cms-page-type.service';
/** @private */
export type { default as MediaUploadResult } from '../../shared/MediaUploadResult';

/** @private */
export const useCmsPageStore = () => Shopware.Store.get(cmsPageStoreId);

/** @private */
export const getApiContext = () => Shopware.Context.api;

/** @private */
export const cloneDeep = ((...args: unknown[]) =>
    (Shopware.Utils.object.cloneDeep as (...args: unknown[]) => unknown)(...args)) as typeof Shopware.Utils.object.cloneDeep;
