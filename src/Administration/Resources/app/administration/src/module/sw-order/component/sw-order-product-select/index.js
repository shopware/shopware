import template from './sw-order-product-select.html.twig';
import './sw-order-product-select.scss';

const { Component, Service } = Shopware;

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
        /** @deprecated tag:v6.4.0 */
        displayProductSelection: {
            type: Boolean,
            required: false,
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
            return Service('repositoryFactory').create('product');
        },

        lineItemTypes() {
            return Service('cartSalesChannelService').getLineItemTypes();
        },

        lineItemPriceTypes() {
            return Service('cartSalesChannelService').getLineItemPriceTypes();
        },

        isShownProductSelect() {
            return this.item._isNew && this.item.type === this.lineItemTypes.PRODUCT;
        },

        isShownItemLabelInput() {
            return this.item.type !== this.lineItemTypes.PRODUCT;
        },

        contextWithInheritance() {
            return { ...Shopware.Context.api, inheritance: true };
        }
    },

    methods: {
        onItemChanged(newProductId) {
            this.productRepository.get(newProductId, this.contextWithInheritance).then((newProduct) => {
                this.item.identifier = newProduct.id;
                this.item.label = newProduct.name;
                this.item.priceDefinition.price = newProduct.price[0].gross;
                this.item.priceDefinition.type = this.lineItemPriceTypes.QUANTITY;
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
