/**
 * @sw-package framework
 */
import useConsentStore from 'src/core/consent/consent.store';
import { dispatchConsentEvent } from 'src/core/consent/events';
import template from './sw-settings-usage-data-consent-modal.html.twig';
import './sw-settings-usage-data-consent-modal.scss';

import SwSettingsUsageDataStoreDataConsentCard from './subcomponents/sw-settings-usage-data-store-data-consent-card';
import SwSettingsUsageDataUserDataConsentCard from './subcomponents/sw-settings-usage-data-user-data-consent-card';
import SwSettingsUsageDataConsentCheckList from './subcomponents/sw-settings-usage-data-consent-check-list';

/**
 * @private
 */
export default Shopware.Component.wrapComponentConfig({
    template,
    name: 'sw-settings-usage-data-consent-modal',

    components: {
        SwSettingsUsageDataStoreDataConsentCard,
        SwSettingsUsageDataUserDataConsentCard,
        SwSettingsUsageDataConsentCheckList,
    },

    inject: [
        'acl',
        'feature',
    ],

    props: {
        storedStoreDataConsent: {
            type: Boolean,
            required: true,
        },
        storedUserDataConsent: {
            type: Boolean,
            required: true,
        },
    },

    data() {
        return {
            unionPath: Shopware.Filter.getByName('asset')(
                '/administration/administration/static/img/data-sharing/union.svg',
            ),
            initialStoreDataConsent: false,
            storeDataConsent: false,
            initialUserDataConsent: false,
            userDataConsent: false,
            sharesAll: false,
            revokesAll: false,
            isLoading: false,
            modalOpenedAt: 0,
        };
    },

    created() {
        /*
         we need to break the reactivity here, otherwise the card
         would disappear when backend data consent is updated
         */
        this.initialStoreDataConsent = this.storedStoreDataConsent;
        this.storeDataConsent = this.initialStoreDataConsent;

        this.initialUserDataConsent = this.storedUserDataConsent;
        this.userDataConsent = this.initialUserDataConsent;

        this.modalOpenedAt = Date.now();
        dispatchConsentEvent('consent_modal_viewed', { option: this.visibleOptions });
    },

    computed: {
        visibleOptions(): Array<'backend_data' | 'user_tracking'> {
            return this.showStoreDataConsent
                ? [
                      'backend_data',
                      'user_tracking',
                  ]
                : ['user_tracking'];
        },

        showStoreDataConsent() {
            if (this.initialStoreDataConsent) {
                return false;
            }

            if (!this.acl.can('system.system_config')) {
                return false;
            }

            return true;
        },

        showSavePreferences() {
            if (!this.showStoreDataConsent) {
                return true;
            }

            return this.storeDataConsent || this.userDataConsent;
        },
    },

    methods: {
        getModalTimeSpentInSeconds() {
            return Math.round((Date.now() - this.modalOpenedAt) / 1000);
        },

        trackLegalLinkClick(linkTarget: 'privacy_policy' | 'data_use_details') {
            dispatchConsentEvent('consent_legal_link_clicked', { link_target: linkTarget, source: 'modal' });
        },

        trackChangedOptionEventsForVisibleOptions() {
            if (this.showStoreDataConsent && this.storeDataConsent !== this.initialStoreDataConsent) {
                dispatchConsentEvent('consent_option_changed', {
                    option: 'backend_data',
                    state: this.storeDataConsent ? 'enabled' : 'disabled',
                });
            }

            if (this.userDataConsent !== this.initialUserDataConsent) {
                dispatchConsentEvent('consent_option_changed', {
                    option: 'user_tracking',
                    state: this.userDataConsent ? 'enabled' : 'disabled',
                });
            }
        },

        trackDecisionEventsForVisibleOptions() {
            const timeSpentOnModal = this.getModalTimeSpentInSeconds();

            if (this.showStoreDataConsent) {
                dispatchConsentEvent('consent_decision_made', {
                    option: 'backend_data',
                    decision: this.storeDataConsent ? 'accepted' : 'revoked',
                    time_spent_on_modal: timeSpentOnModal,
                });
            }

            dispatchConsentEvent('consent_decision_made', {
                option: 'user_tracking',
                decision: this.userDataConsent ? 'accepted' : 'revoked',
                time_spent_on_modal: timeSpentOnModal,
            });
        },

        async savePreferences(done: () => void) {
            this.isLoading = true;

            await this.updateConsents(this.storeDataConsent, this.userDataConsent);
            this.trackChangedOptionEventsForVisibleOptions();
            this.trackDecisionEventsForVisibleOptions();

            this.isLoading = false;
            done();
        },

        async shareAll(done: () => void) {
            this.sharesAll = true;

            if (this.showStoreDataConsent) {
                this.storeDataConsent = true;
            }
            this.userDataConsent = true;

            await this.updateConsents(true, true);
            this.trackChangedOptionEventsForVisibleOptions();
            this.trackDecisionEventsForVisibleOptions();

            this.sharesAll = false;
            done();
        },

        async shareNothing(done: () => void) {
            this.revokesAll = true;

            if (this.showStoreDataConsent) {
                this.storeDataConsent = false;
            }
            this.userDataConsent = false;

            await this.updateConsents(false, false);
            this.trackChangedOptionEventsForVisibleOptions();
            this.trackDecisionEventsForVisibleOptions();

            this.revokesAll = false;
            done();
        },

        async updateConsents(storeDataConsent: boolean, userDataConsent: boolean) {
            if (this.acl.can('system.system_config')) {
                await this.updateSingleConsent('backend_data', storeDataConsent);
            }

            if (this.acl.can('user.update_profile')) {
                await this.updateSingleConsent('product_analytics', userDataConsent);
            }
        },

        async updateSingleConsent(consent: 'backend_data' | 'product_analytics', accepted: boolean) {
            const consentStore = useConsentStore();

            try {
                if (accepted) {
                    await consentStore.accept(consent);
                    return;
                }

                await consentStore.revoke(consent);
            } catch {
                Shopware.Store.get('notification').createNotification({
                    variant: 'critical',
                    title: this.$t('global.default.error'),
                    message: this.$t('sw-settings-usage-data.errors.consent-update-error', {
                        consent,
                    }),
                });
            }
        },
    },
});
