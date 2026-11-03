/**
 * @sw-package framework
 *
 * Everything sw-settings-usage-data-consent-modal-data-provider needs from outside its mini-module.
 */
import { contextStoreId } from 'src/app/store/context.store';
import { sessionStoreId } from 'src/app/store/session.store';

/** @private */
export { defineComponent as wrapComponentConfig } from 'vue';
/** @private */
export { default as useConsentStore } from 'src/core/consent/consent.store';
/** @private */
export { acl as aclService } from 'src/app/service-names';
/** @private */
export { default as SwSettingsUsageDataConsentModal } from '../sw-settings-usage-data-consent-modal';

/** @private */
export const useContextStore = () => Shopware.Store.get(contextStoreId);

/** @private */
export const useSessionStore = () => Shopware.Store.get(sessionStoreId);
