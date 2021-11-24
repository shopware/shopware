import template from './sw-order-create-details.html.twig';

const { Component, Mixin, State } = Shopware;
const { Criteria } = Shopware.Data;
const { mapState } = Component.getComponentHelper();

Component.register('sw-order-create-details', {
    template,

    mixins: [
        Mixin.getByName('notification'),
        Mixin.getByName('cart-notification'),
        Mixin.getByName('order-cart'),
    ],

    data() {
        return {
            showPromotionModal: false,
            promotionError: null,
            isLoading: false,
        };
    },

    computed: {
        context: {
            get() {
                return this.customer ? this.customer.salesChannel : {};
            },

            set(context) {
                if (this.customer) this.customer.salesChannel = context;
            },
        },

        email() {
            return this.customer?.email ?? '';
        },

        phoneNumber() {
            return this.customer?.defaultBillingAddress?.phoneNumber ?? '';
        },

        salesChannelCriteria() {
            const criteria = new Criteria();

            if (this.salesChannelId) {
                criteria.addFilter(Criteria.equals('salesChannels.id', this.salesChannelId));
            }

            return criteria;
        },

        shippingMethodCriteria() {
            const criteria = new Criteria();

            if (this.salesChannelId) {
                criteria.addFilter(Criteria.equals('salesChannels.id', this.salesChannelId));
            }

            return criteria;
        },

        paymentMethodCriteria() {
            const criteria = new Criteria();

            if (this.salesChannelId) {
                criteria.addFilter(Criteria.equals('salesChannels.id', this.salesChannelId));
            }

            criteria.addFilter(Criteria.equals('afterOrderEnabled', 1));

            return criteria;
        },

        hasLineItem() {
            return this.cart?.lineItems.filter(item => item.hasOwnProperty('id')).length > 0;
        },

        promotionCodeLineItems() {
            return this.cart?.lineItems.filter(item => item.type === 'promotion' && item?.payload?.code);
        },

        ...mapState('swOrder', ['disabledAutoPromotion']),
    },

    watch: {
        context: {
            deep: true,
            handler() {
                // TODO: NEXT-16672 - Display newly created admin order as a draft
                // Handle updateOrderContext API
            },
        },

        cart: {
            deep: true,
            immediate: true,
            handler: 'updatePromotionList',
        },

        promotionCodeTags: {
            handler: 'handlePromotionCodeTags',
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            if (!this.customer) {
                this.$nextTick(() => {
                    this.$router.push({ name: 'sw.order.create.initial' });
                });
            }
        },

        changeDisableAutoPromotions() {
            // TODO: NEXT-16672 - Display newly created admin order as a draft
            // Open promotion modal if this switch is on
        },

        onRemoveExistingCode(item) {
            if (item.isInvalid) {
                this.promotionCodeTags = this.promotionCodeTags.filter(tag => tag.code !== item.code);
            } else {
                this.onRemoveItems([item.discountId]);
            }
        },

        updatePromotionList() {
            // Update data and isInvalid flag for each item in promotionCodeTags
            this.promotionCodeTags = this.promotionCodeTags.map(tag => {
                const matchedItem = this.promotionCodeLineItems.find(lineItem => lineItem.payload.code === tag.code);

                if (matchedItem) {
                    return { ...matchedItem.payload, isInvalid: false };
                }

                return { ...tag, isInvalid: true };
            });

            // Add new items from promotionCodeLineItems which promotionCodeTags doesn't contain
            this.promotionCodeLineItems.forEach(lineItem => {
                const matchedItem = this.promotionCodeTags.find(tag => tag.code === lineItem.payload.code);

                if (!matchedItem) {
                    this.promotionCodeTags = [...this.promotionCodeTags, { ...lineItem.payload, isInvalid: false }];
                }
            });
        },

        handlePromotionCodeTags(newValue, oldValue) {
            this.promotionError = null;

            if (newValue.length < oldValue.length) {
                return;
            }

            const promotionCodeLength = this.promotionCodeTags.length;
            const latestTag = this.promotionCodeTags[promotionCodeLength - 1];

            if (newValue.length > oldValue.length) {
                this.onSubmitCode(latestTag.code);
            }

            if (promotionCodeLength > 0 && latestTag.isInvalid) {
                this.promotionError = { detail: this.$tc('sw-order.createBase.textInvalidPromotionCode') };
            }
        },

        onSubmitCode(code) {
            this.isLoading = true;

            return State.dispatch('swOrder/addPromotionCode', {
                salesChannelId: this.customer.salesChannelId,
                contextToken: this.cart.token,
                code,
            }).finally(() => {
                this.isLoading = false;
            });
        },
    },
});
