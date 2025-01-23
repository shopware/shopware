import template from './sw-cms-el-config-product-listing-config-delete-modal.html.twig';
import './sw-cms-el-config-product-listing-config-delete-modal.scss';

/**
 * @private
 * @sw-package discovery
 */
export default {
    template,

    compatConfig: Shopware.compatConfig,

    emits: [
        'confirm',
        'cancel',
    ],

    props: {
        productSorting: {
            type: Object,
            required: true,
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
