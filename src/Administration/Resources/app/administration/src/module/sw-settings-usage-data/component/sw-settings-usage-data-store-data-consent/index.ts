/**
 * @sw-package framework
 */
import template from './sw-settings-usage-data-store-data-consent.html.twig';
import './sw-settings-usage-data-store-data-consent.scss';

/* eslint-disable max-len */
import SwSettingsUsageDataStoreDataConsentCard from '../sw-settings-usage-data-consent-modal/subcomponents/sw-settings-usage-data-store-data-consent-card';
import SwSettingsUsageDataConsentCheckList from '../sw-settings-usage-data-consent-modal/subcomponents/sw-settings-usage-data-consent-check-list';
/* eslint-enable max-len */

/**
 * @private
 */
export default Shopware.Component.wrapComponentConfig({
    template,
    name: 'sw-settings-usage-data-store-data-consent',

    components: {
        SwSettingsUsageDataStoreDataConsentCard,
        SwSettingsUsageDataConsentCheckList,
    },

    data() {
        return {
            storeDataConsent: false,
            isLoading: false,
        };
    },

    computed: {
        unionPath() {
            return Shopware.Filter.getByName('asset')('/administration/administration/static/img/data-sharing/union.svg');
        },
    },

    methods: {
        updateConsent() {
            this.isLoading = true;

            try {
                // update consent
            } finally {
                this.isLoading = true;
            }
        },
    },
});
