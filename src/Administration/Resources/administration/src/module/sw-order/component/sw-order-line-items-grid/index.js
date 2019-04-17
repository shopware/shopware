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
            lineItemActionsEnabled: false
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
        onSelectionChanged() {
            this.lineItemActionsEnabled = Object.keys(this.$refs['order-line-items-grid'].getSelection()).length !== 0;
        },
        onDeleteSelectedItems() {
            const items = this.$refs['order-line-items-grid'].getSelection();
            const deletionPromises = [];
            Object.keys(items).forEach((id) => {
                const item = this.orderLineItems.find((elem) => { return elem.id === id; });
                deletionPromises.push(item.delete(true));
            });

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
        }
    }
});
