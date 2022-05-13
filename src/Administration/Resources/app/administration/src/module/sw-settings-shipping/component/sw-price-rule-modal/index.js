import template from './sw-price-rule-modal.html.twig';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    computed: {
        modalTitle() {
            return this.$tc('sw-settings-shipping.shippingPriceModal.modalTitle');
        },
    },

    methods: {
        createdComponent() {
            this.$super('createdComponent');
            this.rule.moduleTypes = { types: ['shipping'] };
        },

    },
};
