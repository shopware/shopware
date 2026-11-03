/**
 * @sw-package framework
 *
 * Everything the services page needs from outside its mini-module.
 */
import { notificationStoreId } from 'src/app/store/notification.store';
import { assetFilterName } from 'src/app/filter/asset.filter';
import { serviceRegistryClient, shopwareServicesService } from 'src/app/service-names';

/** @private */
export { defineAsyncComponent, defineComponent as wrapComponentConfig } from 'vue';
/** @private */
export { mapState } from 'pinia';
/** @private */
export { acl as aclService } from 'src/app/service-names';
/** @private */
export { default as useSession } from 'src/app/composables/use-session';
/** @private */
export { useShopwareServicesStore } from '../../store/shopware-services.store';
/** @private */
export type { ServiceDescription } from '../../service/shopware-services.service';
/** @private */
export { default as extractError } from '../../composables/extract-error';
/** @private */
export {
    getServicesWithShopwareAccountRequirement,
    type ServiceWithShopwareAccountRequirement,
} from '../../requirements/index';

/** Lists, enables and reads the context of the Shopware services. @private */
export const getShopwareServicesService = () => Shopware.Service(shopwareServicesService);

/** @private */
export const getServiceRegistryClient = () => Shopware.Service(serviceRegistryClient);

/** @private */
export const useNotificationStore = () => Shopware.Store.get(notificationStoreId);

/** @private */
export const getAssetFilter = () => Shopware.Filter.getByName(assetFilterName);
