import template from './sw-order-create-invalid-promotion-modal.html.twig';
import './sw-order-create-invalid-promotion-modal.scss';
import { useOrderStore } from '../dependencies';

/**
 * @sw-package checkout
 */

/**
 * @public
 */
export default {
    template,

    emits: ['close', 'confirm'],

    computed: {
        invalidPromotionCodes() {
            return useOrderStore().invalidPromotionCodes;
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
