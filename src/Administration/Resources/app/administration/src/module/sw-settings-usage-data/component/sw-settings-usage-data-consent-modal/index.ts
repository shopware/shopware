/**
 * @sw-package framework
 */
import useConsentStore from 'src/core/consent/consent.store';
import { dispatchConsentEvent, type ConsentEvents } from 'src/core/consent/events';
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
        dispatchConsentEvent('consent_modal_viewed', { consents_shown: this.visibleOptions });
    },

    computed: {
        visibleOptions(): Array<'backend_data' | 'product_analytics'> {
            return this.showStoreDataConsent
                ? [
                      'backend_data',
                      'product_analytics',
                  ]
                : ['product_analytics'];
        },

        showSingleOptionActions() {
            return !this.showStoreDataConsent;
        },

        showStoreDataConsent() {
            if (this.initialStoreDataConsent) {
                return false;
            }

            return this.acl.can('system.system_config');
        },

        showSavePreferences() {
            return this.showStoreDataConsent && (this.storeDataConsent || this.userDataConsent);
        },
    },

    methods: {
        getModalTimeSpentInSeconds() {
            return Math.round((Date.now() - this.modalOpenedAt) / 1000);
        },

        trackLegalLinkClick(linkTarget: 'privacy_policy' | 'data_use_details') {
            dispatchConsentEvent('consent_legal_link_clicked', { link_target: linkTarget, source: 'modal' });
        },

        trackDecisionEventForVisibleOptions(storeDataConsent: boolean, userDataConsent: boolean) {
            const eventProps: ConsentEvents['consent_modal_decision'] = {
                product_analytics: {
                    status: userDataConsent ? 'accepted' : 'revoked',
                    changed: userDataConsent !== this.initialUserDataConsent,
                },
                time_spent_on_modal: this.getModalTimeSpentInSeconds(),
            };

            if (this.showStoreDataConsent) {
                eventProps.backend_data = {
                    status: storeDataConsent ? 'accepted' : 'revoked',
                    changed: storeDataConsent !== this.initialStoreDataConsent,
                };
            }

            dispatchConsentEvent('consent_modal_decision', eventProps);
        },

        async savePreferences(done: () => void) {
            this.isLoading = true;

            try {
                await this.updateConsents(this.storeDataConsent, this.userDataConsent);
            } finally {
                this.isLoading = false;
                done();
            }
        },

        async giveSingleOptionConsent(done: () => void) {
            this.sharesAll = true;
            this.userDataConsent = true;

            try {
                await this.updateConsents(this.storeDataConsent, true);
            } finally {
                this.sharesAll = false;
                done();
            }
        },

        async declineSingleOptionConsent(done: () => void) {
            this.revokesAll = true;
            this.userDataConsent = false;

            try {
                await this.updateConsents(this.storeDataConsent, false);
            } finally {
                this.revokesAll = false;
                done();
            }
        },

        async shareAll(done: () => void) {
            this.sharesAll = true;

            try {
                await this.updateConsents(true, true);
            } finally {
                this.sharesAll = false;
                done();
            }
        },

        async shareNothing(done: () => void) {
            this.revokesAll = true;

            try {
                await this.updateConsents(false, false);
            } finally {
                this.revokesAll = false;
                done();
            }
        },

        async updateConsents(storeDataConsent: boolean, userDataConsent: boolean) {
            if (this.acl.can('system.system_config')) {
                await this.updateSingleConsent('backend_data', storeDataConsent);
            }

            if (this.acl.can('user.update_profile')) {
                await this.updateSingleConsent('product_analytics', userDataConsent);
            }

            this.trackDecisionEventForVisibleOptions(storeDataConsent, userDataConsent);
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
