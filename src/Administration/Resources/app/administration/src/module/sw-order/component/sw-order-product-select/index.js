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
            default() {
                return [];
            }
        },

        salesChannelId: {
            type: String,
            // @deprecated tag:v6.4.0 - salesChannelId will become required: true
            required: false,
            default: ''
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
                        Criteria.equals('product.childCount', null)
                    ]
                )
            );

            // @deprecated tag:v6.4.0 - If-clause will be removed and filter will always be added
            if (this.salesChannelId) {
                criteria.addFilter(
                    Criteria.equals('product.visibilities.salesChannelId', this.salesChannelId)
                );
            }

            return criteria;
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
