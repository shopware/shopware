import template from './sw-order-line-items-grid.html.twig';
import './sw-order-line-items-grid.scss';

const { Component } = Shopware;

Component.register('sw-order-line-items-grid', {
    template,

    inject: [
        'orderService',
        'repositoryFactory'
    ],

    data() {
        return {
            isLoading: false,
            selectedItems: {}
        };
    },
    props: {
        order: {
            type: Object,
            required: true
        },
        context: {
            type: Object,
            required: true
        },
        editable: {
            type: Boolean,
            required: false,
            default: true
        }
    },
    computed: {

        orderLineItemRepository() {
            return this.repositoryFactory.create('order_line_item');
        },

        lineItemActionsEnabled() {
            return this.selectedItems.length !== 0;
        },

        orderLineItems() {
            return this.order.lineItems;
        },

        getLineItemColumns() {
            const columnDefinitions = [{
                property: 'label',
                dataIndex: 'label',
                label: this.$tc('sw-order.detailBase.columnProductName'),
                allowResize: false,
                primary: true,
                inlineEdit: true,
                width: '200px'
            }, {
                property: 'unitPrice',
                dataIndex: 'unitPrice',
                label: this.order.taxStatus === 'net' ?
                    this.$tc('sw-order.detailBase.columnPriceNet') :
                    this.$tc('sw-order.detailBase.columnPriceGross'),
                allowResize: false,
                align: 'right',
                inlineEdit: true,
                width: '120px'
            }, {
                property: 'quantity',
                dataIndex: 'quantity',
                label: this.$tc('sw-order.detailBase.columnQuantity'),
                allowResize: false,
                align: 'right',
                inlineEdit: true,
                width: '80px'
            }, {
                property: 'totalPrice',
                dataIndex: 'totalPrice',
                label: this.order.taxStatus === 'net' ?
                    this.$tc('sw-order.detailBase.columnTotalPriceNet') :
                    this.$tc('sw-order.detailBase.columnTotalPriceGross'),
                allowResize: false,
                align: 'right',
                width: '80px'
            }];

            if (this.order.price.taxStatus !== 'tax-free') {
                columnDefinitions.push(
                    {
                        property: 'price.taxRules[0]',
                        label: this.$tc('sw-order.detailBase.columnTax'),
                        allowResize: false,
                        align: 'right',
                        inlineEdit: true,
                        width: '100px'
                    }
                );
            }

            return columnDefinitions;
        }
    },
    methods: {
        onInlineEditSave(item) {
            return new Promise((resolve) => {
                if (item.isNew()) {
                    // The item is a custom item
                    if (item.type === '') {
                        // This item is based on a product
                        this.orderService.addProductToOrder(
                            this.order.id,
                            this.order.versionId,
                            item.identifier,
                            item.quantity
                        ).then((lineItem) => {
                            this.$emit('item-edit');
                            resolve(lineItem);
                        });
                    } else if (item.type === 'credit') {
                        this.orderService.addCreditItemToOrder(
                            this.order.id,
                            this.order.versionId,
                            item
                        ).then((lineItem) => {
                            this.$emit('item-edit');
                            resolve(lineItem);
                        });
                    } else {
                        // This item not based on an existing product (blank item)
                        this.orderService.addCustomLineItemToOrder(
                            this.order.id,
                            this.order.versionId,
                            item
                        ).then((lineItem) => {
                            this.$emit('item-edit');
                            resolve(lineItem);
                        });
                    }
                } else {
                    this.$emit('existing-item-edit');
                    resolve();
                }
            });
        },

        onInlineEditCancel() {
            this.$emit('item-cancel');
        },

        createNewOrderLineItem() {
            const item = this.orderLineItemRepository.create();
            item.versionId = this.order.versionId;
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

        onInsertBlankItem() {
            const item = this.createNewOrderLineItem();
            item.description = 'custom line item';
            item.type = 'custom';
            this.orderLineItems.unshift(item);
        },

        onInsertExistingItem() {
            const item = this.createNewOrderLineItem();
            item.type = '';
            this.orderLineItems.unshift(item);
        },

        onInsertCreditItem() {
            const item = this.createNewOrderLineItem();
            item.description = 'credit line item';
            item.type = 'credit';
            this.orderLineItems.unshift(item);
        },

        onSelectionChanged(selection) {
            this.selectedItems = selection;
        },

        onDeleteSelectedItems() {
            const deletionPromises = [];
            Object.keys(this.selectedItems).forEach((id) => {
                deletionPromises.push(this.orderLineItemRepository.delete(id, this.context));
            });

            this.selectedItems = {};

            Promise.all(deletionPromises).then(() => {
                this.$emit('item-delete');
            });
        },

        itemCreatedFromProduct(id) {
            const item = this.orderLineItems.find((elem) => { return elem.id === id; });
            return item.isNew() && item.type === '';
        },

        itemIsCredit(id) {
            const item = this.orderLineItems.find((elem) => { return elem.id === id; });
            return item.type === 'credit';
        },

        getMinItemPrice(id) {
            if (this.itemIsCredit(id)) {
                return null;
            }
            return 0;
        },

        getMaxItemPrice(id) {
            if (!this.itemIsCredit(id)) {
                return null;
            }
            return 0;
        }
    }
});
