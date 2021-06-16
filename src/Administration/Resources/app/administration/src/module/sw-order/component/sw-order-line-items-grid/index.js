import template from './sw-order-line-items-grid.html.twig';
import './sw-order-line-items-grid.scss';

const { Component, Service, Utils } = Shopware;
const { get, format } = Utils;

// merge 16.11.2020
Component.register('sw-order-line-items-grid', {
    template,

    inject: ['repositoryFactory', 'orderService', 'acl', 'feature'],
    props: {
        order: {
            type: Object,
            required: true,
        },
        context: {
            type: Object,
            required: true,
        },
        editable: {
            type: Boolean,
            required: false,
            default: true,
        },
    },

    data() {
        return {
            isLoading: false,
            selectedItems: {},
            searchTerm: '',
            nestedLineItemsModal: null,
        };
    },
    computed: {
        canCreateDiscounts() {
            return this.acl.can('orders.create_discounts');
        },

        orderLineItemRepository() {
            return Service('repositoryFactory').create('order_line_item');
        },

        orderLineItems() {
            if (!this.searchTerm) {
                return this.order.lineItems;
            }

            // Filter based on the product label is not blank and contains the search term or not
            const keyWords = this.searchTerm.split(/[\W_]+/ig);
            return this.order.lineItems.filter(item => {
                if (!item.label) {
                    return false;
                }

                return keyWords.every(key => item.label.toLowerCase().includes(key.toLowerCase()));
            });
        },

        lineItemTypes() {
            return Service('cartStoreService').getLineItemTypes();
        },

        taxStatus() {
            return get(this.order, 'taxStatus', '');
        },

        unitPriceLabel() {
            if (this.taxStatus === 'net') {
                return this.$tc('sw-order.detailBase.columnPriceNet');
            }

            if (this.taxStatus === 'tax-free') {
                return this.$tc('sw-order.detailBase.columnPriceTaxFree');
            }

            return this.$tc('sw-order.detailBase.columnPriceGross');
        },

        getLineItemColumns() {
            const columnDefinitions = [{
                property: 'label',
                dataIndex: 'label',
                label: 'sw-order.detailBase.columnProductName',
                allowResize: false,
                primary: true,
                inlineEdit: true,
                multiLine: true,
                width: '200px',
            }, {
                property: 'unitPrice',
                dataIndex: 'unitPrice',
                label: this.unitPriceLabel,
                allowResize: false,
                align: 'right',
                inlineEdit: true,
                width: '120px',
            }, {
                property: 'quantity',
                dataIndex: 'quantity',
                label: 'sw-order.detailBase.columnQuantity',
                allowResize: false,
                align: 'right',
                inlineEdit: true,
                width: '80px',
            }, {
                property: 'totalPrice',
                dataIndex: 'totalPrice',
                label: this.taxStatus === 'gross' ?
                    'sw-order.detailBase.columnTotalPriceGross' :
                    'sw-order.detailBase.columnTotalPriceNet',
                allowResize: false,
                align: 'right',
                width: '80px',
            }];

            if (this.taxStatus !== 'tax-free') {
                return [...columnDefinitions, {
                    property: 'price.taxRules[0]',
                    label: 'sw-order.detailBase.columnTax',
                    allowResize: false,
                    align: 'right',
                    inlineEdit: true,
                    width: '100px',
                }];
            }

            return columnDefinitions;
        },

        salesChannelId() {
            return this.order?.salesChannelId ?? '';
        },
    },
    methods: {
        onInlineEditSave(item) {
            return new Promise((resolve) => {
                if (item.isNew()) {
                    // This item is based on a product
                    if (item.type === this.lineItemTypes.PRODUCT) {
                        this.orderService.addProductToOrder(
                            this.order.id,
                            this.order.versionId,
                            item.identifier,
                            item.quantity,
                        ).then((lineItem) => {
                            this.$emit('item-edit');
                            resolve(lineItem);
                        });
                    } else if (item.type === this.lineItemTypes.CREDIT) {
                        this.orderService.addCreditItemToOrder(
                            this.order.id,
                            this.order.versionId,
                            item,
                        ).then((lineItem) => {
                            this.$emit('item-edit');
                            resolve(lineItem);
                        });
                    } else {
                        // This item not based on an existing product (blank item)
                        this.orderService.addCustomLineItemToOrder(
                            this.order.id,
                            this.order.versionId,
                            item,
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
                price: 0,
            };
            item.price = {
                taxRules: [{ taxRate: 0 }],
                unitPrice: 0,
                quantity: 1,
                totalPrice: 0,
            };
            item.quantity = 1;
            item.unitPrice = '...';
            item.totalPrice = '...';
            item.precision = 2;
            item.label = '';

            return item;
        },

        onInsertBlankItem() {
            const item = this.createNewOrderLineItem();
            item.description = 'custom line item';
            item.type = this.lineItemTypes.CUSTOM;
            this.orderLineItems.unshift(item);
        },

        onInsertExistingItem() {
            const item = this.createNewOrderLineItem();
            item.type = this.lineItemTypes.PRODUCT;
            this.orderLineItems.unshift(item);
        },

        onInsertCreditItem() {
            const item = this.createNewOrderLineItem();
            item.description = 'credit line item';
            item.type = this.lineItemTypes.CREDIT;
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
            return item.isNew() && item.type === this.lineItemTypes.PRODUCT;
        },

        onSearchTermChange(searchTerm) {
            this.searchTerm = searchTerm.toLowerCase();
        },

        isCreditItem(id) {
            const item = this.orderLineItems.find((elem) => { return elem.id === id; });
            return item.type === this.lineItemTypes.CREDIT;
        },

        isProductItem(item) {
            return item.type === this.lineItemTypes.PRODUCT;
        },

        isPromotionItem(item) {
            return item.type === this.lineItemTypes.PROMOTION;
        },

        getMinItemPrice(id) {
            if (this.isCreditItem(id)) {
                return null;
            }
            return 0;
        },

        showTaxValue(item) {
            return (this.isCreditItem(item.id) || this.isPromotionItem(item)) && (item.price.taxRules.length > 1)
                ? this.$tc('sw-order.detailBase.textCreditTax')
                : `${item.price.taxRules[0].taxRate} %`;
        },

        checkItemPrice(price, item) {
            if (this.isCreditItem(item.id)) {
                item.priceDefinition.price = Math.abs(price) * -1;
                return;
            }

            item.priceDefinition.price = price;
        },

        tooltipTaxDetail(item) {
            const sortTaxes = [...item.price.calculatedTaxes].sort((prev, current) => {
                return prev.taxRate - current.taxRate;
            });

            const decorateTaxes = sortTaxes.map((taxItem) => {
                return this.$tc('sw-order.detailBase.taxDetail', 0, {
                    taxRate: taxItem.taxRate,
                    tax: format.currency(taxItem.tax, this.order.currency.shortName),
                });
            });

            return {
                showDelay: 300,
                message: `${this.$tc('sw-order.detailBase.tax')}<br>${decorateTaxes.join('<br>')}`,
            };
        },

        openNestedLineItemsModal(item) {
            this.nestedLineItemsModal = item;
        },

        closeNestedLineItemsModal() {
            this.nestedLineItemsModal = null;
        },

        hasChildren(item) {
            return item.children && item.children.length > 0;
        },

        hasMultipleTaxes(item) {
            return get(item, 'price.calculatedTaxes') && item.price.calculatedTaxes.length > 1;
        },

        updateItemQuantity(item) {
            if (item.type !== this.lineItemTypes.CUSTOM) {
                return;
            }

            item.priceDefinition.quantity = item.quantity;
        },

        showTaxRulesInlineEdit(item) {
            return !this.itemCreatedFromProduct(item.id) &&
                item.priceDefinition &&
                item.priceDefinition.taxRules &&
                !this.isCreditItem(item.id);
        },
    },
});
