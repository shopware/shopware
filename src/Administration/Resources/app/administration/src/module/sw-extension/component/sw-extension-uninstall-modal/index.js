import template from './sw-extension-uninstall-modal.html.twig';
import './sw-extension-uninstall-modal.scss';

/**
 * @package checkout
 * @private
 */
export default {
    template,

    compatConfig: Shopware.compatConfig,

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

    data() {
        return {
            removePluginData: false,
        };
    },

    computed: {
        title() {
            return this.$t(
                'sw-extension-store.component.sw-extension-uninstall-modal.title',
                { extensionName: this.extensionName },
            );
        },
    },

    methods: {
        emitClose() {
            if (this.isLoading) {
                return;
            }

            this.$emit('modal-close');
        },

        emitUninstall() {
            this.$emit('uninstall-extension', this.removePluginData);
        },
    },
};
