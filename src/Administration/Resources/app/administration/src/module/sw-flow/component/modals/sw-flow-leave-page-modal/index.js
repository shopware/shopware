import template from './sw-flow-leave-page-modal.html.twig';

/**
 * @private
 * @package business-ops
 */
export default {
    template,
    methods: {
        onConfirm() {
            this.$emit('page-leave-confirm');
        },
        onCancel() {
            this.$emit('page-leave-cancel');
        },
    },
};
