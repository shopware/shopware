import template from './sw-extension-removal-modal.html.twig';
import './sw-extension-removal-modal.scss';

/**
 * @sw-package checkout
 * @private
 */
export default {
    template,

    emits: ['modal-close', 'remove-extension'],

    props: {
        extensionName: {
            type: String,
            required: true,
        },
        isLicensed: {
            type: Boolean,
            required: true,
        },
        hasDeletableFiles: {
            type: Boolean,
            required: false,
            default: true,
        },
        isLoading: {
            type: Boolean,
            required: true,
        },
    },

    computed: {
        title() {
            if (!this.isLicensed) {
                return this.$t('sw-extension-store.component.sw-extension-removal-modal.titleRemove', {
                    extensionName: this.extensionName,
                });
            }

            if (!this.hasDeletableFiles) {
                return this.$t('sw-extension-store.component.sw-extension-removal-modal.titleCancelSubscription', {
                    extensionName: this.extensionName,
                });
            }

            return this.$t('sw-extension-store.component.sw-extension-removal-modal.titleCancel', {
                extensionName: this.extensionName,
            });
        },

        alert() {
            if (!this.isLicensed) {
                return this.$t('sw-extension-store.component.sw-extension-removal-modal.alertRemove');
            }

            if (!this.hasDeletableFiles) {
                return this.$t('sw-extension-store.component.sw-extension-removal-modal.alertCancelSubscription');
            }

            return this.$t('sw-extension-store.component.sw-extension-removal-modal.alertCancel');
        },

        btnLabel() {
            if (!this.isLicensed) {
                return this.$t('global.default.remove');
            }

            if (!this.hasDeletableFiles) {
                return this.$t('sw-extension-store.component.sw-extension-removal-modal.labelCancelSubscription');
            }

            return this.$t('sw-extension-store.component.sw-extension-removal-modal.labelCancel');
        },
    },

    methods: {
        emitClose() {
            if (this.isLoading) {
                return;
            }

            this.$emit('modal-close');
        },

        emitRemoval() {
            this.$emit('remove-extension');
        },
    },
};
