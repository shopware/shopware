/**
 * @sw-package checkout
 *
 * Everything sw-extension-card-base needs from outside its mini-module.
 */
import { assetFilterName } from 'src/app/filter/asset.filter';
import { dateFilterName } from 'src/app/filter/date.filter';
import { contextStoreId } from 'src/app/store/context.store';
import { extensionMainModulesStoreId } from 'src/app/store/main-module.store';

/** @private */
export { cacheApiService, extensionStoreActionService, shopwareExtensionService } from 'src/app/service-names';

/** @private */
export const useContextStore = () => Shopware.Store.get(contextStoreId);

/** @private */
export const useExtensionMainModulesStore = () => Shopware.Store.get(extensionMainModulesStoreId);

/** @private */
export const getAssetFilter = () => Shopware.Filter.getByName(assetFilterName);

/** @private */
export const getDateFilter = () => Shopware.Filter.getByName(dateFilterName);

/** @private */
export const warn = ((...args: unknown[]) =>
    (Shopware.Utils.debug.warn as (...args: unknown[]) => unknown)(...args)) as typeof Shopware.Utils.debug.warn;
