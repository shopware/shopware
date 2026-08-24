import template from './sw-emits-inferred.html.twig';

export default {
    template,

    methods: {
        onConfirm() {
            this.$emit('confirm', true);
        },

        onCancel() {
            this.$emit('cancel');
        },
    },
};
