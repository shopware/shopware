/**
 * @sw-package framework
 *
 * Everything sw-settings-usage-data-profile-consent needs from outside its mini-module.
 */
import { assetFilterName } from 'src/app/filter/asset.filter';
import { notificationStoreId } from 'src/app/store/notification.store';

/** @private */
export { defineComponent as wrapComponentConfig } from 'vue';
/** @private */
export { default as useConsentStore } from 'src/core/consent/consent.store';
/** @private */
export { dispatchConsentEvent } from 'src/core/consent/events';
/** @private */
export { default as SwSettingsUsageDataConsentCheckList } from '../sw-settings-usage-data-consent-modal/subcomponents/sw-settings-usage-data-consent-check-list';
/** @private */
export { default as SwSettingsUsageDataUserDataConsentCard } from '../sw-settings-usage-data-consent-modal/subcomponents/sw-settings-usage-data-user-data-consent-card';

/** @private */
export const useNotificationStore = () => Shopware.Store.get(notificationStoreId);

/** @private */
export const getAssetFilter = () => Shopware.Filter.getByName(assetFilterName);
