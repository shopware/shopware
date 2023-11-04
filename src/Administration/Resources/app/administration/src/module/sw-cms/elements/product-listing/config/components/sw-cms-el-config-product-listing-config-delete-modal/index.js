import template from './sw-cms-el-config-product-listing-config-delete-modal.html.twig';
import './sw-cms-el-config-product-listing-config-delete-modal.scss';

/**
 * @private
 * @package content
 */
export default {
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
};
