/**
 * @sw-package framework
 */
import useConsentStore from 'src/core/consent/consent.store';
import { dispatchConsentEvent } from 'src/core/consent/events';
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
            isLoading: false,
        };
    },

    computed: {
        suspended() {
            const consentStore = useConsentStore();
            return !consentStore.consents.product_analytics;
        },

        userDataConsent() {
            const consentStore = useConsentStore();

            try {
                return consentStore.isAccepted('product_analytics');
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
                    await consentStore.accept('product_analytics');
                    dispatchConsentEvent('consent_option_changed', { option: 'user_tracking', state: 'enabled' });
                } else {
                    await consentStore.revoke('product_analytics');
                    dispatchConsentEvent('consent_option_changed', { option: 'user_tracking', state: 'disabled' });
                    dispatchConsentEvent('consent_revoked', { accepted_options: [], declined_options: ['user_tracking'] });
                }
            } catch {
                Shopware.Store.get('notification').createNotification({
                    variant: 'critical',
                    title: this.$t('global.default.error'),
                    message: this.$t('sw-settings-usage-data.errors.consent-update-error', {
                        consent: 'product_analytics',
                    }),
                });
            } finally {
                this.isLoading = false;
            }
        },

        onLegalLinkClick() {
            dispatchConsentEvent('consent_legal_link_clicked', { link_target: 'privacy_policy', source: 'user' });
        },
    },
});
