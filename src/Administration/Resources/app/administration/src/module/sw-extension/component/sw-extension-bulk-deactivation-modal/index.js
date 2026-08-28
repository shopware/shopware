import template from './sw-extension-bulk-deactivation-modal.html.twig';
import './sw-extension-bulk-deactivation-modal.scss';

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

    computed: {
        count() {
            return this.extensions.length;
        },

        title() {
            return this.$t('sw-extension.my-extensions.bulk.deactivationModal.title', { count: this.count }, this.count);
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
            this.$emit('confirm');
        },
    },
};
