import template from './sw-order-create-credit.html.twig';

const { Component, Utils, Service } = Shopware;
const { get } = Utils;

Component.register('sw-order-create-credit', {
    template,

    props: {
        cart: {
            type: Object,
            required: true,
        },
    },

    data() {
        return {
            creditName: '',
            creditPrice: 0,
            creditVat: 'Auto',
            item: null,
        };
    },

    computed: {
        orderLineItemRepository() {
            return Service('repositoryFactory').create('order_line_item');
        },

        lineItemTypes() {
            return Service('cartStoreService').getLineItemTypes();
        },

        cartLineItems() {
            return this.cart.lineItems;
        },

        taxStatus() {
            return get(this.cart, 'price.taxStatus', '');
        },

        unitPriceLabel() {
            if (this.taxStatus === 'net') {
                return this.$tc('sw-order.createBase.columnPriceNet');
            }

            if (this.taxStatus === 'tax-free') {
                return this.$tc('sw-order.createBase.columnPriceTaxFree');
            }

            return this.$tc('sw-order.createBase.columnPriceGross');
        },
    },
});
