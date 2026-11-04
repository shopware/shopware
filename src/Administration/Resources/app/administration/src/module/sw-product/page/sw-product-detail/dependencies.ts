/**
 * @sw-package inventory
 *
 * Everything sw-product-detail needs from outside its mini-module.
 */
import { cacheService, customFieldDataProviderService, userConfigService } from 'src/app/service-names';
import { contextStoreId } from 'src/app/store/context';
import { errorStoreId } from 'src/app/store/error';
import { sessionStoreId } from 'src/app/store/session';
import { shopwareAppsStoreId } from 'src/app/store/shopware-apps';
import { cmsPageStoreId } from 'src/module/sw-cms/store/cms-page.store';
import { swSeoUrlStoreId } from 'src/module/sw-settings-seo/component/sw-seo-url/store';

/** @private */
export { default as ChangesetGenerator } from 'src/core/data/changeset-generator.data';
/** @private */
export { default as Criteria } from 'src/core/data/criteria.data';
/** @private */
export { types } from 'src/core/service/util.service';
/** @private */
export { default as EventBus } from 'src/core/service/utils/eventBus.utils';
/** @private */
export { default as notificationMixin } from 'src/app/mixin/notification';
/** @private */
export { default as placeholderMixin } from 'src/app/mixin/placeholder';
/** @private */
export {
    acl as aclService,
    customFieldDataProviderService,
    entityValidationService,
    feature as featureService,
    mediaService,
    numberRangeService,
    repositoryFactory as repositoryFactoryService,
    seoUrlService,
    systemConfigApiService,
} from 'src/app/service-names';
/** @private */
export { default as EntityValidationService } from 'src/app/service/entity-validation.service';
/** @private */
export { mapPageErrors } from 'src/app/service/map-errors.service';
/** @private */
export { useSwProductDetailStore as useProductDetailStore } from './store';

/** @private */
export const getCacheService = () => Shopware.Service(cacheService);

/** @private */
export const getCustomFieldDataProviderService = (): ServiceContainer['customFieldDataProviderService'] =>
    Shopware.Service(customFieldDataProviderService);

/** @private */
export const getUserConfigService = () => Shopware.Service(userConfigService);

/** @private */
export const useCmsPageStore = () => Shopware.Store.get(cmsPageStoreId);

/** @private */
export const useContextStore = () => Shopware.Store.get(contextStoreId);

/** @private */
export const useErrorStore = () => Shopware.Store.get(errorStoreId);

/** @private */
export const useSeoUrlStore = () => Shopware.Store.get(swSeoUrlStoreId);

/** @private */
export const useSessionStore = () => Shopware.Store.get(sessionStoreId);

/** @private */
export const useShopwareAppsStore = () => Shopware.Store.get(shopwareAppsStoreId);

/** @private */
export const getApiContext = () => Shopware.Context.api;

/** @private */
export const isFeatureActive = (flag: string) => Shopware.Feature.isActive(flag);

/** @private */
export const cloneDeep = ((...args: unknown[]) =>
    (Shopware.Utils.object.cloneDeep as (...args: unknown[]) => unknown)(...args)) as typeof Shopware.Utils.object.cloneDeep;

/** @private */
export const publishData = ((...args: unknown[]) =>
    (Shopware.ExtensionAPI.publishData as (...args: unknown[]) => unknown)(
        ...args,
    )) as typeof Shopware.ExtensionAPI.publishData;
