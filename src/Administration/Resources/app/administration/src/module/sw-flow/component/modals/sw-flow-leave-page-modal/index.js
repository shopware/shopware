import template from './sw-flow-leave-page-modal.html.twig';

/**
 * @private
 * @sw-package after-sales
 */
export default {
    template,

    emits: [
        'page-leave-confirm',
        'page-leave-cancel',
    ],

    compatConfig: Shopware.compatConfig,

    methods: {
        onConfirm() {
            this.$emit('page-leave-confirm');
        },
        onCancel() {
            this.$emit('page-leave-cancel');
        },
    },
};
