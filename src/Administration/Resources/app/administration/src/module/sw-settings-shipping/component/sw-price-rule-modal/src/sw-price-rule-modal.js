import template from './sw-price-rule-modal.html.twig';

/**
 * @sw-package checkout
 *
 * @public
 */
export default {
    template,

    computed: {
        modalTitle() {
            return this.$t('sw-settings-shipping.shippingPriceModal.modalTitle');
        },
    },

    methods: {
        createdComponent() {
            this.$super('createdComponent');
        },
    },
};
