import template from './sw-flow-sequence-modal.html.twig';

/**
 * @private
 * @sw-package after-sales
 */
export default {
    template,

    emits: [
        'process-finish',
        'modal-close',
    ],

    props: {
        sequence: {
            type: Object,
            required: true,
        },

        modalName: {
            type: String,
            required: true,
        },

        action: {
            type: String,
            required: false,
            default: null,
        },
    },

    methods: {
        processSuccess(data) {
            this.$emit('process-finish', data);
        },

        onClose() {
            this.$emit('modal-close');
        },
    },
};
