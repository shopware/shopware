/**
 * @sw-package framework
 *
 * Everything sw-settings-usage-data-consent-modal needs from outside its mini-module.
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
export type { ConsentEvents } from 'src/core/consent/events';
/** @private */
export { acl as aclService } from 'src/app/service-names';

/** @private */
export const useNotificationStore = () => Shopware.Store.get(notificationStoreId);

/** @private */
export const getAssetFilter = () => Shopware.Filter.getByName(assetFilterName);
