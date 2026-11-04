/**
 * @sw-package inventory
 *
 * Everything sw-product-detail-layout needs from outside its mini-module.
 */
import { cmsPageStoreId } from 'src/module/sw-cms/store/cms-page.store';

/** @private */
export { default as Criteria } from 'src/core/data/criteria.data';
/** @private */
export {
    acl as aclService,
    cmsService,
    feature as featureService,
    repositoryFactory as repositoryFactoryService,
} from 'src/app/service-names';

/** @private */
export const useCmsPageStore = () => Shopware.Store.get(cmsPageStoreId);

/** @private */
export { useSwProductDetailStore as useProductDetailStore } from '../../page/sw-product-detail/store';

/** @private */
export const getApiContext = () => Shopware.Context.api;

/** @private */
export const get = ((...args: unknown[]) =>
    (Shopware.Utils.object.get as (...args: unknown[]) => unknown)(...args)) as typeof Shopware.Utils.object.get;
