import { Component, State } from 'src/core/shopware';
import template from './sw-order-product-select.html.twig';
import './sw-order-product-select.less';

Component.register('sw-order-product-select', {
    template,
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
    computed: {
        productStore() {
            return State.getStore('product');
        }
    },
    methods: {
        onItemChanged(newProductId) {
            this.productStore.getByIdAsync(newProductId).then((newProduct) => {
                this.item.identifier = newProduct.id;
                this.item.label = newProduct.name;
                this.item.priceDefinition.price = newProduct.price.gross;
                this.item.unitPrice = newProduct.price.gross;
                this.item.priceDefinition.taxRules.elements[0].taxRate = newProduct.tax.taxRate;
            });
        }
    }
});
