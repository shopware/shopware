/**
 * @sw-package framework
 *
 * Everything the service card needs from outside its mini-module.
 */
import { notificationStoreId } from 'src/app/store/notification';
import { assetFilterName } from 'src/app/filter/asset.filter';
import { dateFilterName } from 'src/app/filter/date.filter';
import { shopwareServicesService } from 'src/app/service-names';

/** @private */
export type {
    CategorizedPermissions,
    ServiceDescription,
    ServiceState,
} from '../../../../service/shopware-services.service';
/** @private */
export type { PropType } from 'vue';
/** @private */
export { default as extractErrorMessage } from '../../../../composables/extract-error';

/** @private */
export { defineComponent as wrapComponentConfig } from 'vue';

/** Activates and deactivates services and reads the permissions they ask for. @private */
export const getShopwareServicesService = () => Shopware.Service(shopwareServicesService);

/** @private */
export const useNotificationStore = () => Shopware.Store.get(notificationStoreId);

/** @private */
export const getAssetFilter = () => Shopware.Filter.getByName(assetFilterName);

/** @private */
export const getDateFilter = () => Shopware.Filter.getByName(dateFilterName);
