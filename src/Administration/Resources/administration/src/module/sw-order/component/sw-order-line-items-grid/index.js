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
            lineItemActionsEnabled: false,
            pendingNewLineItemsFromProduct: [],
            pendingNewLineItemsCustom: []
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
                this.$refs['order-line-items-grid'].allSelectedChecked = false;
                this.$refs['order-line-items-grid'].selection = {};
            }

            return this.lineItemsStore.getList(params).then((response) => {
                this.total = response.total;
                this.orderLineItems = response.items;
                this.isLoading = false;

                return this.orderLineItems;
            });
        },
        onInlineEditSave(item) {
            const manuallyCreatedFromProductIndex = this.pendingNewLineItemsFromProduct.indexOf(item.id);
            const manuallyCreatedCustomIndex = this.pendingNewLineItemsCustom.indexOf(item.id);
            if (manuallyCreatedFromProductIndex !== -1) {
                this.orderService.addProductToOrder(this.order.id,
                    this.order.versionId,
                    item.identifier,
                    item.quantity).then(() => {
                    this.$emit('sw-order-line-items-grid-item-edited');
                });

                this.pendingNewLineItemsFromProduct.splice(manuallyCreatedFromProductIndex, 1);
            } else if (manuallyCreatedCustomIndex !== -1) {
                this.orderService.addCustomLineItemToOrder(this.order.id,
                    this.order.versionId,
                    item).then(() => {
                    this.$emit('sw-order-line-items-grid-item-edited');
                });
                this.pendingNewLineItemsCustom.splice(manuallyCreatedCustomIndex, 1);
            } else {
                item.save().then(() => {
                    this.$emit('sw-order-line-items-grid-item-edited');
                });
            }
        },
        onInlineEditCancel(item) {
            item.discardChanges();
        },

        onInsertBlankItem() {
            const item = this.lineItemsStore.create();
            item.versionId = this.order.versionId;
            item.priceDefinition.taxRules.elements = [];
            item.priceDefinition.isCalculated = false;
            item.priceDefinition.taxRules.elements.push({ taxRate: 0, percentage: 100 });
            item.description = 'custom line item';
            item.quantity = 1;
            item.type = 'custom';
            this.pendingNewLineItemsCustom.push(item.id);
            this.orderLineItems.unshift(item);
        },

        onInsertExistingItem() {
            const item = this.lineItemsStore.create();
            item.versionId = this.order.versionId;
            item.priceDefinition.taxRules.elements = [];
            item.priceDefinition.taxRules.elements.push({ taxRate: 0 });
            item.quantity = 1;
            this.pendingNewLineItemsFromProduct.push(item.id);
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
                this.$emit('sw-order-line-items-grid-item-edited');
            });
        },
        columnShouldDisplayProducts(id) {
            return this.pendingNewLineItemsFromProduct.includes(id);
        }
    }
});
