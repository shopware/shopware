/**
 * @sw-package checkout
 *
 * Everything sw-extension-my-extensions-listing needs from outside its mini-module.
 */
import { assetFilterName } from 'src/app/filter/asset.filter';
import { contextStoreId } from 'src/app/store/context.store';
import { shopwareExtensionsStoreId } from '../../store/extensions.store';

/** @private */
export {
    acl as aclService,
    cacheApiService,
    extensionStoreActionService,
    shopwareExtensionService,
} from 'src/app/service-names';

/** @private */
export const useContextStore = () => Shopware.Store.get(contextStoreId);

/** @private */
export const useShopwareExtensionsStore = () => Shopware.Store.get(shopwareExtensionsStoreId);

/** @private */
export const getAssetFilter = () => Shopware.Filter.getByName(assetFilterName);

/** @private */
export const isEmpty = ((...args: unknown[]) =>
    (Shopware.Utils.types.isEmpty as (...args: unknown[]) => unknown)(...args)) as typeof Shopware.Utils.types.isEmpty;
