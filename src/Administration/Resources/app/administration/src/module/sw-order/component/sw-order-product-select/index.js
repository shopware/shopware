import template from './sw-order-product-select.html.twig';
import './sw-order-product-select.scss';

const { Component, Service } = Shopware;
const { Criteria } = Shopware.Data;

Component.register('sw-order-product-select', {
    template,

    props: {
        item: {
            type: Object,
            required: true,
        },

        salesChannelId: {
            type: String,
            required: true,
            default: '',
        },

        taxStatus: {
            type: String,
            required: true,
            default: '',
        },
    },

    data() {
        return {
            product: null,
        };
    },

    computed: {
        productRepository() {
            return Service('repositoryFactory').create('product');
        },

        lineItemTypes() {
            return Service('cartStoreService').getLineItemTypes();
        },

        lineItemPriceTypes() {
            return Service('cartStoreService').getLineItemPriceTypes();
        },

        isShownProductSelect() {
            return this.item._isNew && this.item.type === this.lineItemTypes.PRODUCT;
        },

        isShownItemLabelInput() {
            return this.item.type !== this.lineItemTypes.PRODUCT;
        },

        contextWithInheritance() {
            return { ...Shopware.Context.api, inheritance: true };
        },

        productCriteria() {
            const criteria = new Criteria();

            criteria.addAssociation('options.group');

            criteria.addFilter(
                Criteria.multi(
                    'OR',
                    [
                        Criteria.equals('product.childCount', 0),
                        Criteria.equals('product.childCount', null),
                    ],
                ),
            );

            criteria.addFilter(
                Criteria.equals('product.visibilities.salesChannelId', this.salesChannelId),
            );

            return criteria;
        },
    },

    methods: {
        onItemChanged(newProductId) {
            this.productRepository.get(newProductId, this.contextWithInheritance).then((newProduct) => {
                this.item.identifier = newProduct.id;
                this.item.label = newProduct.name;
                this.item.priceDefinition.price = this.taxStatus === 'gross'
                    ? newProduct.price[0].gross
                    : newProduct.price[0].net;
                this.item.priceDefinition.type = this.lineItemPriceTypes.QUANTITY;
                this.item.price.taxRules[0].taxRate = newProduct.tax.taxRate;
                this.item.price.unitPrice = '...';
                this.item.price.totalPrice = '...';
                this.item.price.quantity = 1;
                this.item.unitPrice = '...';
                this.item.totalPrice = '...';
                this.item.precision = 2;
                this.item.priceDefinition.taxRules[0].taxRate = newProduct.tax.taxRate;
            });
        },
    },
});
