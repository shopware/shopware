/**
 * @sw-package inventory
 *
 * Everything product-creation needs from outside its mini-module.
 */
import { cacheService } from 'src/app/service-names';
import { shopwareAppsStoreId } from 'src/app/store/shopware-apps';

/** @private */
export { default as Criteria } from 'src/core/data/criteria.data';
/** @private */
export { types } from 'src/core/service/util.service';
/** @private */
export { useSwProductDetailStore as useProductDetailStore } from 'src/module/sw-product/page/sw-product-detail/store';

/** @private */
export const getCacheService = () => Shopware.Service(cacheService);

/** @private */
export const useShopwareAppsStore = () => Shopware.Store.get(shopwareAppsStoreId);

/** @private */
export const getApiContext = () => Shopware.Context.api;

/** @private */
export const isFeatureActive = (flag: string) => Shopware.Feature.isActive(flag);
