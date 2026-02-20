/**
 * @sw-package framework
 */
import useConsentStore from 'src/core/consent/consent.store';
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
    },

    computed: {
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
        async savePreferences(done: () => void) {
            this.isLoading = true;
            const consentStore = useConsentStore();

            try {
                if (this.storeDataConsent) {
                    await consentStore.accept('backend_data');
                } else {
                    await consentStore.revoke('backend_data');
                }

                if (this.userDataConsent) {
                    await consentStore.accept('product_analytics');
                } else {
                    await consentStore.revoke('product_analytics');
                }
            } finally {
                this.isLoading = false;
                done();
            }
        },

        async shareAll(done: () => void) {
            this.sharesAll = true;

            try {
                const consentStore = useConsentStore();

                await consentStore.accept('backend_data');
                await consentStore.accept('product_analytics');
            } finally {
                this.sharesAll = false;
                done();
            }
        },

        async shareNothing(done: () => void) {
            this.revokesAll = true;

            try {
                const consentStore = useConsentStore();

                await consentStore.revoke('backend_data');
                await consentStore.revoke('product_analytics');
            } finally {
                this.revokesAll = false;
                done();
            }
        },
    },
});
