/**
 * @sw-package framework
 */
import useConsentStore from 'src/core/consent/consent.store';
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
            isLoading: false,
        };
    },

    computed: {
        suspended() {
            const consentStore = useConsentStore();
            return !consentStore.consents.backend_data;
        },

        storeDataConsent() {
            const consentStore = useConsentStore();

            try {
                return consentStore.isAccepted('backend_data');
            } catch {
                return false;
            }
        },

        unionPath() {
            return Shopware.Filter.getByName('asset')('/administration/administration/static/img/data-sharing/union.svg');
        },
    },

    methods: {
        async updateConsent(newValue: boolean) {
            const consentStore = useConsentStore();
            this.isLoading = true;

            try {
                if (newValue) {
                    await consentStore.accept('backend_data');
                } else {
                    await consentStore.revoke('backend_data');
                }
            } catch {
                Shopware.Store.get('notification').createNotification({
                    variant: 'critical',
                    title: this.$t('global.default.error'),
                    message: this.$t('sw-settings-usage-data.errors.consent-update-error', {
                        consent: 'backend_data',
                    }),
                });
            } finally {
                this.isLoading = false;
            }
        },
    },
});
