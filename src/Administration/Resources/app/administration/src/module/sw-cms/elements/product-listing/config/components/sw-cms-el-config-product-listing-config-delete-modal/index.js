import template from './sw-cms-el-config-product-listing-config-delete-modal.html.twig';
import './sw-cms-el-config-product-listing-config-delete-modal.scss';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Shopware.Component.register('sw-cms-el-config-product-listing-config-delete-modal', {
    template,

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
});
