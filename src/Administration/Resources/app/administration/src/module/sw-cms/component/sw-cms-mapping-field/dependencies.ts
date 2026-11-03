/**
 * @sw-package discovery
 *
 * Everything sw-cms-mapping-field needs from outside its mini-module.
 */
import { cmsPageStoreId } from '../../store/cms-page.store';

/** @private */
export { defineComponent as wrapComponentConfig } from 'vue';
/** @private */
export { cmsService, repositoryFactory as repositoryFactoryService } from 'src/app/service-names';

/** @private */
export const useCmsPageStore = () => Shopware.Store.get(cmsPageStoreId);

/** @private */
export const getApiContext = () => Shopware.Context.api;
