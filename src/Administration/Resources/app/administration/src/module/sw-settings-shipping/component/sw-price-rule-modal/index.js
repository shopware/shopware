import template from './sw-price-rule-modal.html.twig';

/**
 * @package checkout
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    compatConfig: Shopware.compatConfig,

    computed: {
        modalTitle() {
            return this.$tc('sw-settings-shipping.shippingPriceModal.modalTitle');
        },
    },

    methods: {
        createdComponent() {
            this.$super('createdComponent');
        },

    },
};
