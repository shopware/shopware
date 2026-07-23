import template from './sw-extension-bulk-uninstall-modal.html.twig';
import './sw-extension-bulk-uninstall-modal.scss';

/**
 * @sw-package checkout
 * @private
 */
export default {
    template,

    emits: [
        'modal-close',
        'confirm',
    ],

    props: {
        extensions: {
            type: Array,
            required: true,
        },
        isLoading: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    data() {
        return {
            removePluginData: false,
        };
    },

    computed: {
        count() {
            return this.extensions.length;
        },

        title() {
            return this.$t('sw-extension.my-extensions.bulk.uninstallModal.title', { count: this.count }, this.count);
        },
    },

    methods: {
        emitClose() {
            if (this.isLoading) {
                return;
            }

            this.$emit('modal-close');
        },

        emitConfirm() {
            this.$emit('confirm', this.removePluginData);
        },
    },
};
