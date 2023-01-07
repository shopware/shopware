import template from './sw-extension-removal-modal.html.twig';
import './sw-extension-removal-modal.scss';

/**
 * @package merchant-services
 * @private
 */
export default {
    template,

    props: {
        extensionName: {
            type: String,
            required: true,
        },
        isLicensed: {
            type: Boolean,
            required: true,
        },
        isLoading: {
            type: Boolean,
            required: true,
        },
    },

    computed: {
        title() {
            return this.isLicensed ?
                this.$t(
                    'sw-extension-store.component.sw-extension-removal-modal.titleCancel',
                    { extensionName: this.extensionName },
                ) :
                this.$t(
                    'sw-extension-store.component.sw-extension-removal-modal.titleRemove',
                    { extensionName: this.extensionName },
                );
        },

        alert() {
            return this.isLicensed ? this.$tc('sw-extension-store.component.sw-extension-removal-modal.alertCancel') :
                this.$tc('sw-extension-store.component.sw-extension-removal-modal.alertRemove');
        },

        btnLabel() {
            return this.isLicensed ? this.$tc('sw-extension-store.component.sw-extension-removal-modal.labelCancel') :
                this.$tc('sw-extension-store.component.sw-extension-removal-modal.labelRemove');
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
