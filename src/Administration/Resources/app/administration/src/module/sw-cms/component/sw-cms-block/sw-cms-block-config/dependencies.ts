/**
 * @sw-package discovery
 *
 * Everything sw-cms-block-config needs from outside its mini-module.
 */
import { cmsPageStoreId } from '../../../store/cms-page.store';

/** @private */
export { defineComponent as wrapComponentConfig } from 'vue';
/** @private */
export { cmsService, repositoryFactory as repositoryFactoryService } from 'src/app/service-names';
/** @private */
export { default as cmsStateMixin } from '../../../mixin/sw-cms-state.mixin';
/** @private */
export type { default as MediaUploadResult } from '../../../shared/MediaUploadResult';

/** @private */
export const useCmsPageStore = () => Shopware.Store.get(cmsPageStoreId);
