/**
 * @sw-package discovery
 *
 * Everything sw-category-detail needs from outside its mini-module.
 */
import { assetFilterName } from 'src/app/filter/asset.filter';
import { errorStoreId } from 'src/app/store/error';
import { shopwareAppsStoreId } from 'src/app/store/shopware-apps';
import { cmsPageStoreId } from 'src/module/sw-cms/store/cms-page.store';
import { swSeoUrlStoreId } from 'src/module/sw-settings-seo/component/sw-seo-url/store';

/** @private */
export { default as ChangesetGenerator } from 'src/core/data/changeset-generator.data';
/** @private */
export { default as Criteria } from 'src/core/data/criteria.data';
/** @private */
export { default as EntityCollection } from 'src/core/data/entity-collection.data';
/** @private */
export { default as ShopwareError } from 'src/core/data/ShopwareError';
/** @private */
export { default as notificationMixin } from 'src/app/mixin/notification.mixin';
/** @private */
export { default as placeholderMixin } from 'src/app/mixin/placeholder.mixin';
/** @private */
export {
    acl as aclService,
    cmsService,
    customFieldDataProviderService,
    repositoryFactory as repositoryFactoryService,
    seoUrlService,
    systemConfigApiService,
} from 'src/app/service-names';
/** @private */
export { useSwCategoryDetailStore as useCategoryDetailStore } from './store';

/** @private */
export const useCmsPageStore = () => Shopware.Store.get(cmsPageStoreId);

/** @private */
export const useErrorStore = () => Shopware.Store.get(errorStoreId);

/** @private */
export const useSeoUrlStore = () => Shopware.Store.get(swSeoUrlStoreId);

/** @private */
export const useShopwareAppsStore = () => Shopware.Store.get(shopwareAppsStoreId);

/** @private */
export const getAssetFilter = () => Shopware.Filter.getByName(assetFilterName);

/** @private */
export const getApiContext = () => Shopware.Context.api;

/** @private */
export const isArray = ((...args: unknown[]) =>
    (Shopware.Utils.types.isArray as (...args: unknown[]) => unknown)(...args)) as typeof Shopware.Utils.types.isArray;

/** @private */
export const isEmpty = ((...args: unknown[]) =>
    (Shopware.Utils.types.isEmpty as (...args: unknown[]) => unknown)(...args)) as typeof Shopware.Utils.types.isEmpty;

/** @private */
export const isEqual = ((...args: unknown[]) =>
    (Shopware.Utils.types.isEqual as (...args: unknown[]) => unknown)(...args)) as typeof Shopware.Utils.types.isEqual;

/** @private */
export const publishData = ((...args: unknown[]) =>
    (Shopware.ExtensionAPI.publishData as (...args: unknown[]) => unknown)(
        ...args,
    )) as typeof Shopware.ExtensionAPI.publishData;
