import template from './sw-order-line-items-grid-sales-channel.html.twig';
import './sw-order-line-items-grid-sales-channel.scss';

const { Component, Utils } = Shopware;
const { get } = Utils;

Component.register('sw-order-line-items-grid-sales-channel', {
    template,

    inject: ['repositoryFactory'],

    props: {
        cart: {
            type: Object,
            required: true
        },

        currency: {
            type: Object,
            required: true
        },

        isCustomerActive: {
            type: Boolean,
            default: false
        },

        isLoading: {
            type: Boolean,
            default: false
        }
    },

    data() {
        return {
            selectedItems: {}
        };
    },

    computed: {
        orderLineItemRepository() {
            return this.repositoryFactory.create('order_line_item');
        },

        cartLineItems() {
            return this.cart.lineItems;
        },

        getLineItemColumns() {
            const columnDefinitions = [{
                property: 'label',
                dataIndex: 'label',
                label: this.$tc('sw-order.createBase.columnProductName'),
                allowResize: false,
                primary: true,
                inlineEdit: true,
                width: '200px'
            }, {
                property: 'unitPrice',
                dataIndex: 'unitPrice',
                label: get(this.cart, 'price.taxStatus') === 'net' ?
                    this.$tc('sw-order.createBase.columnPriceNet') :
                    this.$tc('sw-order.createBase.columnPriceGross'),
                allowResize: false,
                align: 'right',
                inlineEdit: true,
                width: '120px'
            }, {
                property: 'quantity',
                dataIndex: 'quantity',
                label: this.$tc('sw-order.createBase.columnQuantity'),
                allowResize: false,
                align: 'right',
                inlineEdit: true,
                width: '80px'
            }, {
                property: 'totalPrice',
                dataIndex: 'totalPrice',
                label: get(this.cart, 'price.taxStatus') === 'net' ?
                    this.$tc('sw-order.createBase.columnTotalPriceNet') :
                    this.$tc('sw-order.createBase.columnTotalPriceGross'),
                allowResize: false,
                align: 'right',
                width: '80px'
            }, {
                property: 'tax',
                label: this.$tc('sw-order.createBase.columnTax'),
                allowResize: false,
                align: 'right',
                inlineEdit: true,
                width: '100px'
            }];

            return columnDefinitions;
        }
    },

    methods: {
        onInlineEditSave(item) {
            if (item._isNew) {
                if (item.type === '') {
                    this.$emit('on-add-item', item);
                } else if (item.type === 'credit') {
                    // TODO:  implement for credit
                } else {
                    // TODO: implement for custom item
                }
            } else {
                this.$emit('on-edit-item', item);
            }
        },

        onInlineEditCancel() {
            // TODO: implement cancel saving item
        },

        createNewOrderLineItem() {
            const item = this.orderLineItemRepository.create();
            item.versionId = Shopware.Context.api.liveVersionId;
            item.priceDefinition = {
                isCalculated: false,
                taxRules: [{ taxRate: 0, percentage: 100 }],
                price: 0
            };
            item.price = {
                taxRules: [{ taxRate: 0 }],
                unitPrice: 0,
                quantity: 1,
                totalPrice: 0
            };
            item.quantity = 1;
            item.unitPrice = 0;
            item.totalPrice = 0;
            item.precision = 2;
            item.label = '';

            return item;
        },

        onInsertExistingItem() {
            const item = this.createNewOrderLineItem();
            item.type = '';
            this.cartLineItems.unshift(item);
        },

        onSelectionChanged(selection) {
            this.selectedItems = selection;
        },

        onDeleteSelectedItems() {
            const selectedItemKeys = Object.keys(this.selectedItems);
            this.$emit('on-remove-item', selectedItemKeys);
        },

        itemCreatedFromProduct(id) {
            const item = this.cartLineItems.find((elem) => { return elem.id === id; });
            return item._isNew && item.type === '';
        }
    }
});
