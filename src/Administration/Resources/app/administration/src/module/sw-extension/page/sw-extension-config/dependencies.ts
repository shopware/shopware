/**
 * @sw-package checkout
 *
 * Everything sw-extension-config needs from outside its mini-module.
 */
import { assetFilterName } from 'src/app/filter/asset.filter';
import { shopwareExtensionsStoreId } from '../../store/extensions.store';

/** @private */
export { defineComponent as wrapComponentConfig } from 'vue';
/** @private */
export type { RouteLocationNamedRaw } from 'vue-router';
/** @private */
export { default as notificationMixin } from 'src/app/mixin/notification.mixin';
/** @private */
export { shopwareExtensionService } from 'src/app/service-names';
/** @private */
export type { Extension } from '../../service/extension-store-action.service';

/** @private */
export const useShopwareExtensionsStore = () => Shopware.Store.get(shopwareExtensionsStoreId);

/** @private */
export const getAssetFilter = () => Shopware.Filter.getByName(assetFilterName);
