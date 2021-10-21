import template from './sw-order-create-custom-item.html.twig';

const { Component, Utils } = Shopware;
const { get } = Utils;

Component.register('sw-order-create-custom-item', {
    template,

    props: {
        cart: {
            type: Object,
            required: true,
        },
    },

    data() {
        return {
            item: {
                label: '',
                price: 0,
                amount: 1,
                tax: {},
                itemTotal: 0,
            },
            tax: 0,
        };
    },

    computed: {

        taxStatus() {
            return get(this.cart, 'price.taxStatus', '');
        },

        unitPriceLabel() {
            if (this.taxStatus === 'net') {
                return this.$tc('sw-order.createModal.customItem.labelPriceNet');
            }

            if (this.taxStatus === 'tax-free') {
                return this.$tc('sw-order.createBase.columnPriceTaxFree');
            }

            return this.$tc('sw-order.createModal.customItem.labelPriceGross');
        },

        placeholderLabel() {
            if (this.taxStatus === 'net') {
                return this.$tc('sw-order.createModal.customItem.placeholderPriceNet');
            }

            if (this.taxStatus === 'tax-free') {
                return this.$tc('sw-order.createBase.columnPriceTaxFree');
            }

            return this.$tc('sw-order.createModal.customItem.placeholderPriceGross');
        },
    },
});
