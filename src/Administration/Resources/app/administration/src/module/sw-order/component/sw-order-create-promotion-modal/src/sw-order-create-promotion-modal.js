import template from './sw-order-create-promotion-modal.html.twig';
import './sw-order-create-promotion-modal.scss';
import { currency, DiscountScopes, DiscountTypes, getCartStoreService, useOrderStore } from '../dependencies';

/**
 * @sw-package checkout
 */

/**
 * @public
 */
export default {
    template,

    emits: ['close', 'save'],

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
            return useOrderStore().cart;
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

            getCartStoreService()
                .disableAutomaticPromotions(this.cart.token, additionalParams)
                .then(() => {
                    this.isLoading = false;
                    this.$emit('save');
                });
        },

        getDescription(item) {
            const { totalPrice } = item.price;
            const { value, discountScope, discountType, groupId } = item.payload;
            const snippet = `sw-order.createBase.textPromotionDescription.${discountScope}`;

            if (
                discountScope === DiscountScopes.CART &&
                discountType === DiscountTypes.ABSOLUTE &&
                Math.abs(totalPrice) < value
            ) {
                return this.$t(`${snippet}.absoluteUpto`, {
                    value: currency(Number(value), this.currency.isoCode),
                    totalPrice: currency(Math.abs(totalPrice), this.currency.isoCode),
                });
            }

            const discountValue =
                discountType === DiscountTypes.PERCENTAGE ? value : currency(Number(value), this.currency.isoCode);

            return this.$t(`${snippet}.${discountType}`, {
                value: discountValue,
                groupId,
            });
        },
    },
};
