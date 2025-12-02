/**
 * @sw-package framework
 */
import type { PropType } from 'vue';
import { MtModal, MtModalAction, MtModalRoot } from '@shopware-ag/meteor-component-library';
import template from './sw-settings-usage-data-consent-modal.html.twig';
import './sw-settings-usage-data-consent-modal.scss';

import SwSettingsUsageDataStoreDataConsentCard from './subcomponents/sw-settings-usage-data-store-data-consent-card';
import SwSettingsUsageDataUserDataConsentCard from './subcomponents/sw-settings-usage-data-user-data-consent-card';
import SwSettingsUsageDataConsentCheckList from './subcomponents/sw-settings-usage-data-consent-check-list';

type ConsentStruct = {
    value: boolean;
};

/**
 * @private
 */
export default Shopware.Component.wrapComponentConfig({
    template,

    components: {
        MtModal,
        MtModalRoot,
        MtModalAction,
        SwSettingsUsageDataStoreDataConsentCard,
        SwSettingsUsageDataUserDataConsentCard,
        SwSettingsUsageDataConsentCheckList,
    },

    props: {
        initialStoreDataConsent: {
            type: Object as PropType<ConsentStruct>,
            required: true,
        },
        initialUserDataConsent: {
            type: Object as PropType<ConsentStruct>,
            required: true,
        },
    },

    data() {
        return {
            unionPath: Shopware.Filter.getByName('asset')(
                '/administration/administration/static/img/data-sharing/union.svg',
            ),
            storeDataConsent: false,
            userDataConsent: false,
        };
    },

    create() {
        this.storeDataConsent = this.initialStoreDataConsent.value;
        this.userDataConsent = this.initialUserDataConsent.value;
    },

    computed: {
        showConsentModal() {
            return true;
        },

        showStoreDataConsent() {
            if (this.initialStoreDataConsent.value) {
                return false;
            }

            return true;
        },

        showSavePreferences() {
            if (!this.showStoreDataConsent) {
                return true;
            }

            if (this.storeDataConsent === true || this.userDataConsent === true) {
                return true;
            }

            return false;
        },
    },

    methods: {
        savePreferences(done: () => void) {
            done();
        },

        shareAll(done: () => void) {
            done();
        },

        shareNothing(done: () => void) {
            done();
        },
    },
});
