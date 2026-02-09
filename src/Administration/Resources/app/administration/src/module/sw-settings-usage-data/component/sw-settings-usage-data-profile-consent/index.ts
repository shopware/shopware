/**
 * @sw-package framework
 */
import template from './sw-settings-usage-data-profile-consent.html.twig';
import './sw-settings-usage-data-profile-consent.scss';

/* eslint-disable max-len */
import SwSettingsUsageDataUserDataConsentCard from '../sw-settings-usage-data-consent-modal/subcomponents/sw-settings-usage-data-user-data-consent-card';
import SwSettingsUsageDataConsentCheckList from '../sw-settings-usage-data-consent-modal/subcomponents/sw-settings-usage-data-consent-check-list';
/* eslint-enable max-len */

/**
 * @private
 */
export default Shopware.Component.wrapComponentConfig({
    template,

    components: {
        SwSettingsUsageDataUserDataConsentCard,
        SwSettingsUsageDataConsentCheckList,
    },

    data() {
        return {
            userDataConsent: false,
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
