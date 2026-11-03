import template from './sw-order-save-changes-beforehand-modal.html.twig';

/**
 * @sw-package checkout
 */

/**
 * @public
 */
export default {
    template,

    emits: ['confirm', 'cancel'],

    methods: {
        onConfirm() {
            this.$emit('confirm');
        },
        onCancel() {
            this.$emit('cancel');
        },
    },
};
