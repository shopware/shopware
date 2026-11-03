import template from './sw-order-leave-page-modal.html.twig';

/**
 * @sw-package checkout
 */

/**
 * @public
 */
export default {
    template,

    emits: ['page-leave-confirm', 'page-leave-cancel'],

    methods: {
        onConfirm() {
            this.$emit('page-leave-confirm');
        },
        onCancel() {
            this.$emit('page-leave-cancel');
        },
    },
};
