/**
 * @sw-package framework
 *
 * Everything sw-settings-services-grant-permissions-modal needs from outside its mini-module.
 */
import { assetFilterName } from 'src/app/filter/asset.filter';
import { notificationStoreId } from 'src/app/store/notification.store';

/** @private */
export { defineComponent as wrapComponentConfig } from 'vue';
/** @private */
export { default as useSession } from 'src/app/composables/use-session';
/** @private */
export { default as extractErrorMessage } from '../../composables/extract-error';
/** @private */
export { grantPermissions } from '../../composables/permissions';
/** @private */
export { useShopwareServicesStore } from '../../store/shopware-services.store';

/** @private */
export const useNotificationStore = () => Shopware.Store.get(notificationStoreId);

/** @private */
export const getAssetFilter = () => Shopware.Filter.getByName(assetFilterName);
