import { Component, State, Mixin } from 'src/core/shopware';
import template from './sw-order-line-items-grid.html.twig';
import './sw-order-line-items-grid.scss';

Component.register('sw-order-line-items-grid', {
    template,

    inject: ['orderService'],

    mixins: [
        Mixin.getByName('listing')
    ],

    data() {
        return {
            orderLineItems: [],
            isLoading: false,
            selectedItems: {}
        };
    },
    props: {
        order: {
            type: Object,
            required: true,
            default() {
                return {};
            }
        },
        editable: {
            type: Boolean,
            required: false,
            default: true
        }
    },
    computed: {
        lineItemsStore() {
            return this.order.getAssociation('lineItems');
        },

        productStore() {
            return State.getStore('product');
        },

        lineItemColumns() {
            return this.getLineItemColumns();
        },

        lineItemActionsEnabled() {
            return Object.keys(this.selectedItems).length !== 0;
        }
    },
    methods: {
        getList() {
            this.isLoading = true;
            const params = this.getListingParams();
            params.sortBy = 'createdAt';
            params.sortDirection = 'DESC';
            params.versionId = this.order.versionId;

            this.orderLineItems = [];
            if (this.$refs['order-line-items-grid']) {
                this.$refs['order-line-items-grid'].selectAll(false);
            }

            return this.lineItemsStore.getList(params).then((response) => {
                this.total = response.total;
                this.orderLineItems = response.items;
                this.isLoading = false;

                return this.orderLineItems;
            });
        },
        onInlineEditSave(item) {
            this.saveLineItem(item).then(() => {
                this.$emit('item-edited');
            });
        },
        saveLineItem(item) {
            let returnVal = false;
            if (item.isLocal === true) {
                // The item is a custom item
                if (item.type === '') {
                    // This item is based on a product
                    returnVal = this.orderService.addProductToOrder(this.order.id,
                        this.order.versionId,
                        item.identifier,
                        item.quantity);
                } else if (item.type === 'credit') {
                    returnVal = this.orderService.addCreditItemToOrder(this.order.id, this.order.versionId, item);
                } else {
                    // This item not based on an existing product (blank item)
                    returnVal = this.orderService.addCustomLineItemToOrder(this.order.id, this.order.versionId, item);
                }
            } else {
                // The item already existed in the order
                returnVal = item.save();
            }
            return returnVal;
        },

        onInlineEditCancel(item) {
            item.discardChanges();
        },

        onInsertBlankItem() {
            const item = this.lineItemsStore.create();
            item.versionId = this.order.versionId;
            item.priceDefinition.taxRules = [];
            item.priceDefinition.isCalculated = false;
            item.priceDefinition.taxRules.push({ taxRate: 0, percentage: 100 });
            item.price.taxRules = [];
            item.price.taxRules.push({ taxRate: 0 });
            item.description = 'custom line item';
            item.quantity = 1;
            item.type = 'custom';
            this.orderLineItems.unshift(item);
        },

        onInsertExistingItem() {
            const item = this.lineItemsStore.create();
            item.versionId = this.order.versionId;
            item.priceDefinition.taxRules = [];
            item.priceDefinition.taxRules.push({ taxRate: 0 });
            item.priceDefinition.price = 0;
            item.price.taxRules = [];
            item.price.taxRules.push({ taxRate: 0 });
            item.quantity = 1;
            this.orderLineItems.unshift(item);
        },

        onInsertCreditItem() {
            const item = this.lineItemsStore.create();
            item.versionId = this.order.versionId;
            item.price.taxRules = [];
            item.price.taxRules.push({ taxRate: 0, percentage: 100 });
            item.priceDefinition.isCalculated = false;
            item.description = 'credit line item';
            item.quantity = 1;
            item.type = 'credit';
            this.orderLineItems.unshift(item);
        },

        onSelectionChanged(selection) {
            this.selectedItems = selection;
        },

        onDeleteSelectedItems() {
            const deletionPromises = [];
            Object.keys(this.selectedItems).forEach((id) => {
                const item = this.orderLineItems.find((elem) => { return elem.id === id; });
                deletionPromises.push(item.delete(true));
            });

            this.selectedItems = {};

            Promise.all(deletionPromises).then(() => {
                this.$emit('item-deleted');
            });
        },

        itemCreatedFromProduct(id) {
            const item = this.orderLineItems.find((elem) => { return elem.id === id; });
            return item.isLocal && item.type === '';
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
        },

        getLineItemColumns() {
            const columnDefintions = [{
                property: 'label',
                dataIndex: 'label',
                label: this.$tc('sw-order.detailBase.columnProductName'),
                allowResize: false,
                primary: true,
                inlineEdit: true
            }, {
                property: 'unitPrice',
                dataIndex: 'unitPrice',
                label: this.order.taxStatus === 'net' ?
                    this.$tc('sw-order.detailBase.columnPriceNet') :
                    this.$tc('sw-order.detailBase.columnPriceGross'),
                allowResize: false,
                align: 'right',
                inlineEdit: true
            }, {
                property: 'quantity',
                dataIndex: 'quantity',
                label: this.$tc('sw-order.detailBase.columnQuantity'),
                allowResize: false,
                align: 'right',
                inlineEdit: true
            }, {
                property: 'totalPrice',
                dataIndex: 'totalPrice',
                label: this.order.taxStatus === 'net' ?
                    this.$tc('sw-order.detailBase.columnTotalPriceNet') :
                    this.$tc('sw-order.detailBase.columnTotalPriceGross'),
                allowResize: false,
                align: 'right'
            }];

            if (this.order.price.taxStatus !== 'tax-free') {
                columnDefintions.push(
                    {
                        property: 'price.taxRules[0]',
                        label: this.$tc('sw-order.detailBase.columnTax'),
                        allowResize: false,
                        align: 'right',
                        inlineEdit: true
                    }
                );
            }

            return columnDefintions;
        }
    }
});
