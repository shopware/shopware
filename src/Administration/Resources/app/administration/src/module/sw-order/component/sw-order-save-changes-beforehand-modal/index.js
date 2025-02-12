import template from './sw-order-save-changes-beforehand-modal.html.twig';

/**
 * @sw-package checkout
 */

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    emits: [
        'confirm',
        'proceed',
        'cancel',
    ],

    methods: {
        onConfirm() {
            this.$emit('confirm');
        },
        onProceed() {
            this.$emit('proceed');
        },
        onCancel() {
            this.$emit('cancel');
        },
    },
};
