import template from './sw-order-save-changes-beforehand-modal.html.twig';

/**
 * @sw-package checkout
 */

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    props:  {
        reason: {
            type: String,
            default: 'status',
            validate(value) {
                return ['status'].includes(value);
            },
        },
    },

    emits: [
        'confirm',
        'cancel',
    ],

    computed: {
        reasonDescription() {
            return this.$t(`sw-order.saveChangesBeforehandModal.${this.reason}Description`);
        },
    },

    methods: {
        onConfirm() {
            this.$emit('confirm');
        },
        onCancel() {
            this.$emit('cancel');
        },
    },
};
