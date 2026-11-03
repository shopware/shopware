/**
 * @sw-package checkout
 *
 * Everything sw-extension-my-extensions-account needs from outside its mini-module.
 */
import { assetFilterName } from 'src/app/filter/asset.filter';
import { shopwareExtensionsStoreId } from '../../store/extensions.store';

/** @private */
export type { AxiosError } from 'axios';
/** @private */
export { defineComponent as wrapComponentConfig } from 'vue';
/** @private */
export { default as notificationMixin } from 'src/app/mixin/notification.mixin';
/** @private */
export { shopwareExtensionService, storeService, systemConfigApiService } from 'src/app/service-names';
/** @private */
export type { UserInfo } from '../../../../core/service/api/store.api.service';
/** @private */
export { default as extensionErrorHandler } from '../../service/extension-error-handler.service';
/** @private */
export type { MappedError } from '../../service/extension-error-handler.service';

/** @private */
export const useShopwareExtensionsStore = () => Shopware.Store.get(shopwareExtensionsStoreId);

/** @private */
export const getAssetFilter = () => Shopware.Filter.getByName(assetFilterName);
