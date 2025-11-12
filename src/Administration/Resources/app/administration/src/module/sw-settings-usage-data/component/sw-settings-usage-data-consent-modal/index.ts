/**
 * @sw-package framework
 */
import { MtModal, MtModalAction, MtModalRoot } from '@shopware-ag/meteor-component-library';
import template from './sw-settings-usage-data-consent-modal.html.twig';
import './sw-settings-usage-data-consent-modal.scss';

/**
 * @private
 */
export default Shopware.Component.wrapComponentConfig({
    template,

    components: {
        MtModal,
        MtModalRoot,
        MtModalAction,
    },

    props: {
        initialDataUsageConsent: {
            type: Object,
            required: true,
        },
        initialTrackingConsent: {
            type: Object,
            required: true,
        },
    },

    data() {
        return {
            unionPath: Shopware.Filter.getByName('asset')(
                '/administration/administration/static/img/data-sharing/union.svg',
            ),
            showSavePreferences: false,
        };
    },

    computed: {
        showConsentModal() {
            return true;
        },

        showStoreDataConsent() {
            return true;
        },

        showSavePreferences() {
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
