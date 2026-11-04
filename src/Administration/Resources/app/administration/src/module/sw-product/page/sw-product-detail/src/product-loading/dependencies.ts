/**
 * @sw-package inventory
 *
 * Everything product-loading needs from outside its mini-module.
 */
import { customFieldDataProviderService } from 'src/app/service-names';
import { shopwareAppsStoreId } from 'src/app/store/shopware-apps';

/** @private */
export { default as Criteria } from 'src/core/data/criteria.data';
/** @private */
export { useSwProductDetailStore as useProductDetailStore } from 'src/module/sw-product/page/sw-product-detail/store';

/** @private */
export const getCustomFieldDataProviderService = (): { getCustomFieldSets(entity: string): Promise<unknown> } =>
    Shopware.Service(customFieldDataProviderService) as { getCustomFieldSets(entity: string): Promise<unknown> };

/** @private */
export const useShopwareAppsStore = () => Shopware.Store.get(shopwareAppsStoreId);

/** @private */
export const getApiContext = () => Shopware.Context.api;

/** @private */
export const trackTelemetry = (...args: Parameters<typeof Shopware.Telemetry.track>) => Shopware.Telemetry.track(...args);

/** @private */
export const cloneDeep = ((...args: unknown[]) =>
    (Shopware.Utils.object.cloneDeep as (...args: unknown[]) => unknown)(...args)) as typeof Shopware.Utils.object.cloneDeep;
