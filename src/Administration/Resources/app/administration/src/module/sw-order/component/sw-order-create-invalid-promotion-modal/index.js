import template from './sw-order-create-invalid-promotion-modal.html.twig';
import './sw-order-create-invalid-promotion-modal.scss';
import useSwOrderStore from 'shopware:stores/swOrder';

/**
 * @sw-package checkout
 */

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    emits: ['close', 'confirm'],

    computed: {
        invalidPromotionCodes() {
            return useSwOrderStore().invalidPromotionCodes;
        },
    },

    methods: {
        onClose() {
            this.$emit('close');
        },

        onConfirm() {
            this.$emit('confirm');
        },
    },
};
