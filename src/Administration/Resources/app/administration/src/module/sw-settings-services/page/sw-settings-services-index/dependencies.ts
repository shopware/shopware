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
/** @private */
export { default as SwSettingsServicesHero } from '../../component/sw-settings-services-hero';
/** @private */
export { default as SwSettingsServicesGrantPermissionsCard } from '../../component/sw-settings-services-grant-permissions-card';
/** @private */
export { default as SwSettingsServicesRevokePermissionsModal } from '../../component/sw-settings-services-revoke-permissions-modal';
/** @private */
export { default as SwSettingsServicesDeactivateModal } from '../../component/sw-settings-services-deactivate-modal';

/** Lists, enables and reads the context of the Shopware services. @private */
export const getShopwareServicesService = () => Shopware.Service(shopwareServicesService);

/** @private */
export const getServiceRegistryClient = () => Shopware.Service(serviceRegistryClient);

/** @private */
export const useNotificationStore = () => Shopware.Store.get(notificationStoreId);

/** @private */
export const getAssetFilter = () => Shopware.Filter.getByName(assetFilterName);
