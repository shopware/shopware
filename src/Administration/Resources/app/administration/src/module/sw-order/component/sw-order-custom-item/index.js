import template from './sw-order-custom-item.html.twig';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    props: {
        customItem: {
            type: Object,
            required: true,
        },

        currency: {
            type: Object,
            required: true,
        },

        taxStatus: {
            type: String,
            required: true,
        },
    },

    data() {
        return {
            taxId: null,
        };
    },

    computed: {
        pricePlaceholder() {
            return this.taxStatus === 'gross'
                ? this.$tc('sw-order.itemModal.customItem.placeholderPriceGross')
                : this.$tc('sw-order.itemModal.customItem.placeholderPriceNet');
        },

        priceLabel() {
            return this.taxStatus === 'gross'
                ? this.$tc('sw-order.createBase.columnPriceGross')
                : this.$tc('sw-order.createBase.columnPriceNet');
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.taxId = this.customItem?.tax?.id;
        },

        onChangeTax(id, tax) {
            this.customItem.tax = tax;
        },
    },
};
