import { DiscountTypes, DiscountScopes } from 'src/module/sw-promotion/helper/promotion.helper';
import template from './sw-order-create-promotion-modal.html.twig';
import './sw-order-create-promotion-modal.scss';

const { Component, State, Utils, Service } = Shopware;
const { format } = Utils;

Component.register('sw-order-create-promotion-modal', {
    template,

    props: {
        currency: {
            type: Object,
            required: true,
        },
        salesChannelId: {
            type: String,
            required: false,
            default: null,
        },
    },

    data() {
        return {
            isLoading: false,
        };
    },

    computed: {
        cart() {
            return State.get('swOrder').cart;
        },

        cartAutomaticPromotionItems() {
            return this.cart.lineItems.filter((item) => {
                return item.type === 'promotion' && item.payload.code === '';
            });
        },

        hasNoAutomaticPromotions() {
            return this.cartAutomaticPromotionItems.length === 0;
        },
    },

    methods: {
        onCancel() {
            this.$emit('close');
        },

        onSave() {
            this.disableAutomaticPromotions();
        },

        disableAutomaticPromotions() {
            this.isLoading = true;
            const additionalParams = { salesChannelId: this.salesChannelId };

            Service('cartStoreService').disableAutomaticPromotions(this.cart.token, additionalParams).then(() => {
                this.isLoading = false;
                this.$emit('save');
            });
        },

        getDescription(item) {
            const { totalPrice } = item.price;
            const { value, discountScope, discountType, groupId } = item.payload;
            const snippet = `sw-order.createBase.textPromotionDescription.${discountScope}`;

            if (discountScope === DiscountScopes.CART &&
                discountType === DiscountTypes.ABSOLUTE &&
                Math.abs(totalPrice) < value) {
                return this.$tc(`${snippet}.absoluteUpto`, 0, {
                    value: format.currency(Number(value), this.currency.shortName),
                    totalPrice: format.currency(Math.abs(totalPrice), this.currency.shortName),
                });
            }

            const discountValue = discountType === DiscountTypes.PERCENTAGE
                ? value
                : format.currency(Number(value), this.currency.shortName);

            return this.$tc(`${snippet}.${discountType}`, 0, { value: discountValue, groupId });
        },
    },
});
