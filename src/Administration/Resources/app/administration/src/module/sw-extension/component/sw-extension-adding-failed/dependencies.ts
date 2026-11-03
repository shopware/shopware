/**
 * @sw-package checkout
 *
 * Everything sw-extension-adding-failed needs from outside its mini-module.
 */
import { shopwareExtensionsStoreId } from '../../store/extensions.store';

/** @private */
export { shopwareExtensionService } from 'src/app/service-names';

/** @private */
export const useShopwareExtensionsStore = () => Shopware.Store.get(shopwareExtensionsStoreId);
