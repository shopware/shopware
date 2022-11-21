import template from './sw-flow-leave-page-modal.html.twig';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
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
