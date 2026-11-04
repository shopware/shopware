/**
 * @sw-package discovery
 *
 * Everything sw-cms-detail needs from outside its mini-module.
 */
import { contextStoreId } from 'src/app/store/context';
import { errorStoreId } from 'src/app/store/error';
import { shopwareAppsStoreId } from 'src/app/store/shopware-apps';

/** @private */
export { default as Criteria } from 'src/core/data/criteria.data';
/** @private */
export { default as ShopwareError } from 'src/core/data/ShopwareError';
/** @private */
export { default as notificationMixin } from 'src/app/mixin/notification.mixin';
/** @private */
export { default as placeholderMixin } from 'src/app/mixin/placeholder.mixin';
/** @private */
export {
    acl as aclService,
    appCmsService,
    cmsDataResolverService,
    cmsPageTypeService,
    cmsService,
    entityFactory as entityFactoryService,
    entityHydrator as entityHydratorService,
    loginService,
    repositoryFactory as repositoryFactoryService,
    systemConfigApiService,
} from 'src/app/service-names';
/** @private */
export { mapPropertyErrors } from 'src/app/service/map-errors.service';
/** @private */
export { default as CMS } from '../../constant/sw-cms.constant';
/** @private */
export { default as cmsStateMixin } from '../../mixin/sw-cms-state.mixin';

/** @private */
export const useContextStore = () => Shopware.Store.get(contextStoreId);

/** @private */
export const useErrorStore = () => Shopware.Store.get(errorStoreId);

/** @private */
export const useShopwareAppsStore = () => Shopware.Store.get(shopwareAppsStoreId);

/** @private */
export const getApiContext = () => Shopware.Context.api;

/** @private */
export const camelCase = ((...args: unknown[]) =>
    (Shopware.Utils.string.camelCase as (...args: unknown[]) => unknown)(...args)) as typeof Shopware.Utils.string.camelCase;

/** @private */
export const cloneDeep = ((...args: unknown[]) =>
    (Shopware.Utils.object.cloneDeep as (...args: unknown[]) => unknown)(...args)) as typeof Shopware.Utils.object.cloneDeep;

/** @private */
export const debounce = ((...args: unknown[]) =>
    (Shopware.Utils.debounce as (...args: unknown[]) => unknown)(...args)) as typeof Shopware.Utils.debounce;

/** @private */
export const getObjectDiff = ((...args: unknown[]) =>
    (Shopware.Utils.object.getObjectDiff as (...args: unknown[]) => unknown)(
        ...args,
    )) as typeof Shopware.Utils.object.getObjectDiff;

/** @private */
export const isEmpty = ((...args: unknown[]) =>
    (Shopware.Utils.types.isEmpty as (...args: unknown[]) => unknown)(...args)) as typeof Shopware.Utils.types.isEmpty;

/** @private */
export const publishData = ((...args: unknown[]) =>
    (Shopware.ExtensionAPI.publishData as (...args: unknown[]) => unknown)(
        ...args,
    )) as typeof Shopware.ExtensionAPI.publishData;

/** @private */
export const warn = ((...args: unknown[]) =>
    (Shopware.Utils.debug.warn as (...args: unknown[]) => unknown)(...args)) as typeof Shopware.Utils.debug.warn;
