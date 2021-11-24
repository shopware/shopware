import template from './sw-order-custom-item.html.twig';

const { Component } = Shopware;

Component.register('sw-order-custom-item', {
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
});
