import template from './sw-order-product-select.html.twig';
import './sw-order-product-select.scss';

const { Component } = Shopware;

Component.register('sw-order-product-select', {
    template,

    inject: ['repositoryFactory'],

    props: {
        item: {
            type: Object,
            required: true,
            default() {
                return [];
            }
        },
        displayProductSelection: {
            type: Boolean,
            required: true,
            default() {
                return true;
            }
        }
    },

    data() {
        return {
            product: null
        };
    },

    computed: {
        productRepository() {
            return this.repositoryFactory.create('product');
        }
    },
    methods: {
        onItemChanged(newProductId) {
            this.productRepository.get(newProductId, Shopware.Context.api).then((newProduct) => {
                this.item.identifier = newProduct.id;
                this.item.label = newProduct.name;
                this.item.priceDefinition.price = newProduct.price[0].gross;
                this.item.priceDefinition.type = 'quantity';
                this.item.price.unitPrice = newProduct.price[0].gross;
                this.item.price.totalPrice = 0;
                this.item.price.quantity = 1;
                this.item.totalPrice = 0;
                this.item.precision = 2;
                this.item.priceDefinition.taxRules.taxRate = newProduct.tax.taxRate;
            });
        }
    }
});
