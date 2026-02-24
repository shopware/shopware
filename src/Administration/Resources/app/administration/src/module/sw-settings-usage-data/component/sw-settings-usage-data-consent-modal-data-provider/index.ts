/**
 * @sw-package framework
 */
import useConsentStore from 'src/core/consent/consent.store';
import template from './sw-settings-usage-data-consent-modal-data-provider.html.twig';

import SwSettingsUsageDataConsentModal from '../sw-settings-usage-data-consent-modal';
/**
 * @private
 */
export default Shopware.Component.wrapComponentConfig({
    template,
    name: 'sw-settings-usage-data-consent-modal-data-provider',

    components: {
        SwSettingsUsageDataConsentModal,
    },

    computed: {
        storeDataConsent() {
            const consentStore = useConsentStore();

            try {
                return consentStore.isAccepted('backend_data');
            } catch {
                return false;
            }
        },
        userDataConsent() {
            const consentStore = useConsentStore();

            try {
                return consentStore.isAccepted('product_analytics');
            } catch {
                return false;
            }
        },

        areConsentsLoaded() {
            const consentStore = useConsentStore();

            return consentStore.consents.backend_data && consentStore.consents.product_analytics;
        },

        showConsentModal() {
            if (!this.areConsentsLoaded) {
                return false;
            }

            return true;
        },
    },
});
