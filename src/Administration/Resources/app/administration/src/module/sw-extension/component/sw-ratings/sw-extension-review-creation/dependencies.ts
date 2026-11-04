/**
 * @sw-package checkout
 *
 * Everything sw-extension-review-creation needs from outside its mini-module.
 */
import { sessionStoreId } from 'src/app/store/session';
import { shopwareExtensionsStoreId } from '../../../store/extensions.store';

/** @private */
export { default as ShopwareError } from 'src/core/data/ShopwareError';
/** @private */
export { extensionStoreActionService } from 'src/app/service-names';

/** @private */
export const useSessionStore = () => Shopware.Store.get(sessionStoreId);

/** @private */
export const useShopwareExtensionsStore = () => Shopware.Store.get(shopwareExtensionsStoreId);
