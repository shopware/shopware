import template from './sw-order-line-items-grid-sales-channel.html.twig';
import { LineItemType } from '../../order.types';
import './sw-order-line-items-grid-sales-channel.scss';

/**
 * @package customer-order
 */

const { Utils, State, Service } = Shopware;
const { get, format } = Utils;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: ['feature'],

    props: {
        salesChannelId: {
            type: String,
            required: true,
            default: '',
        },

        cart: {
            type: Object,
            required: true,
        },

        currency: {
            type: Object,
            required: true,
        },

        isCustomerActive: {
            type: Boolean,
            default: false,
        },

        isLoading: {
            type: Boolean,
            default: false,
        },
    },

    data() {
        return {
            selectedItems: {},
            searchTerm: '',
        };
    },

    computed: {
        orderLineItemRepository() {
            return Service('repositoryFactory').create('order_line_item');
        },

        cartLineItems() {
            if (!this.searchTerm) {
                return this.cart.lineItems;
            }

            // Filter based on the product label is not blank and contains the search term or not
            const keyWords = this.searchTerm.split(/[\W_]+/ig);
            return this.cart.lineItems.filter(item => {
                if (!item.label) {
                    return false;
                }

                return keyWords.every(key => item.label.toLowerCase().includes(key.toLowerCase()));
            });
        },

        lineItemTypes() {
            return LineItemType;
        },

        isCartTokenAvailable() {
            return State.getters['swOrder/isCartTokenAvailable'];
        },

        isAddNewItemButtonDisabled() {
            return !this.isCustomerActive || !this.isCartTokenAvailable || this.isLoading;
        },

        taxStatus() {
            return get(this.cart, 'price.taxStatus', '');
        },

        unitPriceLabel() {
            if (this.taxStatus === 'net') {
                return this.$tc('sw-order.createBase.columnPriceNet');
            }

            if (this.taxStatus === 'tax-free') {
                return this.$tc('sw-order.createBase.columnPriceTaxFree');
            }

            return this.$tc('sw-order.createBase.columnPriceGross');
        },

        getLineItemColumns() {
            const columnDefinitions = [{
                property: 'quantity',
                dataIndex: 'quantity',
                label: this.$tc('sw-order.createBase.columnQuantity'),
                allowResize: false,
                align: 'right',
                inlineEdit: true,
                width: '80px',
            }, {
                property: 'label',
                dataIndex: 'label',
                label: this.$tc('sw-order.createBase.columnProductName'),
                allowResize: false,
                primary: true,
                inlineEdit: true,
                multiLine: true,
            }, {
                property: 'unitPrice',
                dataIndex: 'unitPrice',
                label: this.unitPriceLabel,
                allowResize: false,
                align: 'right',
                inlineEdit: true,
                width: '120px',
            }];

            if (this.taxStatus !== 'tax-free') {
                columnDefinitions.push({
                    property: 'tax',
                    label: this.$tc('sw-order.createBase.columnTax'),
                    allowResize: false,
                    align: 'right',
                    inlineEdit: true,
                    width: '100px',
                });
            }

            return [...columnDefinitions, {
                property: 'totalPrice',
                dataIndex: 'totalPrice',
                label: this.taxStatus === 'gross' ?
                    this.$tc('sw-order.createBase.columnTotalPriceGross') :
                    this.$tc('sw-order.createBase.columnTotalPriceNet'),
                allowResize: false,
                align: 'right',
                width: '80px',
            }];
        },
    },

    methods: {
        onInlineEditSave(item) {
            if (item.label === '') {
                return;
            }
            item.priceDefinition.isCalculated = true;
            this.$emit('on-save-item', item);
        },

        onInlineEditCancel(item) {
            if (item._isNew) {
                this.initLineItem(item);
                delete item.identifier;
            }

            // Reset quantity
            if (item.initialQuantity) {
                item.quantity = item.initialQuantity;
            }
        },

        createNewOrderLineItem() {
            this.searchTerm = '';
            this.$refs.itemFilter.term = '';

            const item = this.orderLineItemRepository.create();
            item.versionId = Shopware.Context.api.liveVersionId;
            this.initLineItem(item);

            return item;
        },

        initLineItem(item) {
            item.priceDefinition = {
                isCalculated: false,
                taxRules: [{ taxRate: 0, percentage: 100 }],
                price: 0,
            };
            item.price = {
                taxRules: [{ taxRate: 0 }],
                unitPrice: '...',
                quantity: 1,
                totalPrice: '...',
            };
            item.quantity = 1;
            item.unitPrice = 0;
            item.totalPrice = 0;
            item.precision = 2;
            item.label = '';
        },

        onInsertExistingItem() {
            const item = this.createNewOrderLineItem();
            item.type = this.lineItemTypes.PRODUCT;
            this.cartLineItems.unshift(item);
            State.commit('swOrder/setCartLineItems', this.cartLineItems);
        },

        onInsertBlankItem() {
            const item = this.createNewOrderLineItem();
            item.description = 'custom line item';
            item.type = this.lineItemTypes.CUSTOM;
            this.cartLineItems.unshift(item);
            State.commit('swOrder/setCartLineItems', this.cartLineItems);
        },

        onInsertCreditItem() {
            const item = this.createNewOrderLineItem();
            item.description = 'credit line item';
            item.type = this.lineItemTypes.CREDIT;
            this.cartLineItems.unshift(item);
            State.commit('swOrder/setCartLineItems', this.cartLineItems);
        },

        onSelectionChanged(selection) {
            this.selectedItems = selection;
        },

        onDeleteSelectedItems() {
            const selectedIds = [];

            Object.keys(this.selectedItems).forEach(key => {
                if (this.selectedItems[key].label === '') {
                    State.commit('swOrder/removeEmptyLineItem', key);
                } else {
                    selectedIds.push(key);
                }
            });

            if (selectedIds.length > 0) {
                this.$emit('on-remove-items', selectedIds);
            }

            this.$refs.dataGrid.resetSelection();
        },

        itemCreatedFromProduct(item) {
            return item._isNew && item.type === this.lineItemTypes.PRODUCT;
        },

        onSearchTermChange(searchTerm) {
            this.searchTerm = searchTerm.toLowerCase();
        },

        isCreditItem(item) {
            return item.type === this.lineItemTypes.CREDIT;
        },

        isProductItem(item) {
            return item.type === this.lineItemTypes.PRODUCT;
        },

        getMinItemPrice(item) {
            if (this.isCreditItem(item)) {
                return null;
            }
            return 0;
        },

        isPromotionItem(item) {
            return item.type === this.lineItemTypes.PROMOTION;
        },

        isAutoPromotionItem(item) {
            return this.isPromotionItem(item) && !get(item, 'payload.code');
        },

        showTaxValue(item) {
            return (this.isCreditItem(item) || this.isPromotionItem(item)) && (item.price.taxRules.length > 1)
                ? this.$tc('sw-order.createBase.textCreditTax')
                : `${item.price.taxRules[0].taxRate} %`;
        },

        checkItemPrice(price, item) {
            if (this.isCreditItem(item)) {
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
                return this.$tc('sw-order.createBase.taxDetail', 0, {
                    taxRate: taxItem.taxRate,
                    tax: format.currency(taxItem.tax, this.currency.shortName),
                });
            });

            return {
                showDelay: 300,
                message: `${this.$tc('sw-order.createBase.tax')}<br>${decorateTaxes.join('<br>')}`,
            };
        },

        hasMultipleTaxes(item) {
            return get(item, 'price.calculatedTaxes') && item.price.calculatedTaxes.length > 1;
        },

        changeItemQuantity(value, item) {
            if (!item.initialQuantity) {
                item.initialQuantity = item.quantity;
            }

            item.quantity = value;
        },
    },
};
