/**
 * @sw-package framework
 */
import template from './sw-settings-usage-data-store-data-consent.html.twig';
import './sw-settings-usage-data-store-data-consent.scss';
import {
    dispatchConsentEvent,
    getAssetFilter,
    SwSettingsUsageDataConsentCheckList,
    SwSettingsUsageDataStoreDataConsentCard,
    useConsentStore,
    useNotificationStore,
    wrapComponentConfig,
} from '../dependencies';

/**
 * @private
 */
export default wrapComponentConfig({
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
            return getAssetFilter()('/administration/administration/static/img/data-sharing/union.svg');
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
                useNotificationStore().createNotification({
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

        onLegalLinkClick() {
            dispatchConsentEvent('consent_legal_link_clicked', { link_target: 'privacy_policy', source: 'setting' });
        },
    },
});
