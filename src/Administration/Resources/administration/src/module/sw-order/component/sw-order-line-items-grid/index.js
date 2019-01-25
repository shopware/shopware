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
            if (item.isLocal === true) {
                // The item is a custom item
                if (item.type === 'custom') {
                    this.orderService.addCustomLineItemToOrder(this.order.id,
                        this.order.versionId,
                        item).then(() => {
                        this.$emit('sw-order-line-items-grid-item-edited');
                    });
                } else {
                    // The is item is based on a product
                    this.orderService.addProductToOrder(this.order.id,
                        this.order.versionId,
                        item.identifier,
                        item.quantity).then(() => {
                        this.$emit('sw-order-line-items-grid-item-edited');
                    });
                }
            } else {
                // The item already existed in the order
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
            this.orderLineItems.unshift(item);
        },

        onInsertExistingItem() {
            const item = this.lineItemsStore.create();
            item.versionId = this.order.versionId;
            item.priceDefinition.taxRules.elements = [];
            item.priceDefinition.taxRules.elements.push({ taxRate: 0 });
            item.quantity = 1;
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
        itemCreatedFromProduct(id) {
            const item = this.orderLineItems.find((elem) => { return elem.id === id; });
            return item.isLocal && item.type !== 'custom';
        }
    }
});
