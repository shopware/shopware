import template from './sw-order-create-initial-modal.html.twig';
import './sw-order-create-initial-modal.scss';

const { State, Service, Mixin } = Shopware;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    mixins: [
        Mixin.getByName('notification'),
        Mixin.getByName('cart-notification'),
    ],

    data() {
        return {
            productItems: [],
            customItem: {},
            creditItem: {},
            promotionCodes: [],
            isLoading: false,
            disabledAutoPromotions: false,
            context: {
                currencyId: '',
                paymentMethodId: '',
                shippingMethodId: '',
                languageId: '',
                billingAddressId: '',
                shippingAddressId: '',
            },
        };
    },

    computed: {
        lineItemTypes() {
            return Service('cartStoreService').getLineItemTypes();
        },

        lineItemPriceTypes() {
            return Service('cartStoreService').getLineItemPriceTypes();
        },

        orderLineItemRepository() {
            return Service('repositoryFactory').create('order_line_item');
        },

        taxStatus() {
            return this.cart?.price?.taxStatus ?? '';
        },

        salesChannelId() {
            return this.customer?.salesChannelId || '';
        },

        salesChannelContext() {
            return State.get('swOrder').context;
        },

        cart() {
            return State.get('swOrder').cart;
        },

        currency() {
            return this.salesChannelContext.currency;
        },

        customer() {
            return State.get('swOrder').customer;
        },
    },

    watch: {
        salesChannelContext() {
            State.dispatch('swOrder/updateContextParameters', this.context);
        },
    },

    methods: {
        onCloseModal() {
            if (!this.customer || !this.cart.token) {
                this.$emit('modal-close');
                return;
            }

            State.dispatch('swOrder/cancelCart', {
                salesChannelId: this.customer.salesChannelId,
                contextToken: this.cart.token,
            }).then(() => {
                this.$emit('modal-close');
            });
        },

        async onPreviewOrder() {
            const promises = [];

            this.isLoading = true;

            promises.push(this.updateOrderContext());

            if (this.disabledAutoPromotions) {
                promises.push(this.disableAutoAppliedPromotions());
            }

            // Get product line items
            let items = this.productItems.map(product => this.addExistingProduct(product));

            if (this.isValidItem(this.customItem)) {
                items.push(this.addCustomItem(this.customItem));
            }

            if (this.isValidItem(this.creditItem)) {
                items.push(this.addCreditItem(this.creditItem));
            }

            if (this.promotionCodes.length) {
                items = [...items, ...this.addPromotionCodes()];
            }

            promises.push(this.onSaveItem(items));

            try {
                const responses = await Promise.all(promises);

                if (responses) {
                    this.$emit('order-preview');
                }
            } catch (error) {
                this.createNotificationError({ message: error.message });
                items = [];
            } finally {
                this.isLoading = false;
            }
        },

        onSaveItem(items) {
            return Service('cartStoreService')
                .addMultipleLineItems(this.customer.salesChannelId, this.cart.token, items);
        },

        createNewOrderLineItem() {
            const item = this.orderLineItemRepository.create();
            item.versionId = Shopware.Context.api.liveVersionId;
            this.initLineItem(item);

            return item;
        },

        initLineItem(item) {
            item.priceDefinition = {
                isCalculated: true,
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
            item.unitPrice = 0;
            item.totalPrice = 0;
            item.precision = 2;
            item.label = '';
        },

        addExistingProduct(product) {
            const item = this.createNewOrderLineItem();
            item.type = this.lineItemTypes.PRODUCT;
            item.identifier = product.id;
            item.label = product.name;
            item.priceDefinition.price = this.taxStatus === 'gross'
                ? product.price[0].gross
                : product.price[0].net;
            item.priceDefinition.type = this.lineItemPriceTypes.QUANTITY;
            item.price.taxRules[0].taxRate = product.tax.taxRate;
            item.quantity = product.amount;
            item.priceDefinition.taxRules[0].taxRate = product.tax.taxRate;

            return item;
        },

        addCustomItem(customItem) {
            const item = this.createNewOrderLineItem();
            item.description = 'custom line item';
            item.type = this.lineItemTypes.CUSTOM;
            item.priceDefinition.type = this.lineItemPriceTypes.QUANTITY;
            item.priceDefinition.taxRules[0].taxRate = customItem.tax?.taxRate || 0;
            item.priceDefinition.quantity = customItem.quantity;
            item.quantity = customItem.quantity;
            item.label = customItem.label;
            item.priceDefinition.price = customItem.price;

            return item;
        },

        addCreditItem(credit) {
            const item = this.createNewOrderLineItem();
            item.description = 'credit line item';
            item.type = this.lineItemTypes.CREDIT;
            item.priceDefinition.type = this.lineItemPriceTypes.ABSOLUTE;
            item.priceDefinition.quantity = 1;
            item.label = credit.label;
            item.priceDefinition.price = credit.price;

            return item;
        },

        onProductChange(products) {
            this.productItems = products.filter(item => item.amount > 0);
        },

        isValidItem(item) {
            return item?.label && item?.price;
        },

        addPromotionCodes() {
            return this.promotionCodes.map(code => {
                return {
                    type: this.lineItemTypes.PROMOTION,
                    referencedId: code,
                };
            });
        },

        updatePromotion(promotions) {
            this.promotionCodes = promotions;
        },

        updateOrderContext() {
            return State.dispatch('swOrder/updateOrderContext', {
                context: this.context,
                salesChannelId: this.customer.salesChannelId,
                contextToken: this.cart.token,
            });
        },

        disableAutoAppliedPromotions() {
            const additionalParams = { salesChannelId: this.customer.salesChannelId };

            return Service('cartStoreService').disableAutomaticPromotions(this.cart.token, additionalParams)
                .then(() => {
                    State.commit('swOrder/setDisabledAutoPromotion', true);
                });
        },

        updateAutoPromotionsToggle(value) {
            this.disabledAutoPromotions = value;
        },
    },
};
