/**
 * @sw-package inventory
 *
 * Everything sw-bulk-edit-product needs from outside its mini-module.
 */
import { userConfigService } from 'src/app/service-names';
import { contextStoreId } from 'src/app/store/context';
import { swBulkEditStoreId } from 'src/app/store/sw-bulk-edit';

/** @private */
export { default as Criteria } from 'src/core/data/criteria.data';
/** @private */
export { default as EntityCollection } from 'src/core/data/entity-collection.data';
/** @private */
export {
    bulkEditApiFactory as bulkEditApiFactoryService,
    customFieldDataProviderService,
    feature as featureService,
    repositoryFactory as repositoryFactoryService,
} from 'src/app/service-names';

/** @private */
export const getUserConfigService = () => Shopware.Service(userConfigService);

/** @private */
export const useBulkEditStore = () => Shopware.Store.get(swBulkEditStoreId);

/** @private */
export const useContextStore = () => Shopware.Store.get(contextStoreId);

/** @private */
export { useSwProductDetailStore as useProductDetailStore } from 'src/module/sw-product/page/sw-product-detail/store';

/** @private */
export const getApiContext = () => Shopware.Context.api;

/** @private */
export const getAppContext = () => Shopware.Context.app;

/** @private */
export const chunk = ((...args: unknown[]) =>
    (Shopware.Utils.array.chunk as (...args: unknown[]) => unknown)(...args)) as typeof Shopware.Utils.array.chunk;

/** @private */
export const cloneDeep = ((...args: unknown[]) =>
    (Shopware.Utils.object.cloneDeep as (...args: unknown[]) => unknown)(...args)) as typeof Shopware.Utils.object.cloneDeep;

/** @private */
export const convert = ((...args: unknown[]) =>
    (Shopware.Utils.unitConversion.convert as (...args: unknown[]) => unknown)(
        ...args,
    )) as typeof Shopware.Utils.unitConversion.convert;

/** @private */
export const isArray = ((...args: unknown[]) =>
    (Shopware.Utils.types.isArray as (...args: unknown[]) => unknown)(...args)) as typeof Shopware.Utils.types.isArray;

/** @private */
export const isEmpty = ((...args: unknown[]) =>
    (Shopware.Utils.types.isEmpty as (...args: unknown[]) => unknown)(...args)) as typeof Shopware.Utils.types.isEmpty;
