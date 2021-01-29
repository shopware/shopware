import template from './sw-promotion-v2-discounts.html.twig';
import './sw-promotion-v2-discounts.scss';

const { Component } = Shopware;

Component.register('sw-promotion-v2-discounts', {
    template,

    inject: [
        'acl',
        'repositoryFactory'
    ],

    mixins: [
        'notification'
    ],

    props: {
        promotion: {
            type: Object,
            required: true
        }
    },

    data() {
        return {
            isActive: false,
            newDiscount: null,
            selectedDiscountType: 'basic',
            showDiscountModal: false
        };
    },

    computed: {
        promotionDiscountRepository() {
            return this.repositoryFactory.create('promotion_discount');
        }
    },

    methods: {
        onButtonClick() {
            this.isActive = !this.isActive;
        },

        onChangeSelection(value) {
            this.selectedDiscountType = value;
        },

        onShowDiscountModal() {
            this.newDiscount = this.createNewDiscount();
            this.showDiscountModal = true;
        },

        onCloseDiscountModal() {
            this.newDiscount = null;
            this.selectedDiscountType = 'basic';
            this.showDiscountModal = false;
        },

        onFinishDiscountModal() {
            if (this.newDiscount.type === 'free') {
                Object.assign(this.newDiscount, {
                    type: 'percentage',
                    value: 100,
                    applierKey: 'SELECT'
                });
            }

            this.promotionDiscountRepository.save(this.newDiscount, Shopware.Context.api).then(() => {
                this.onCloseDiscountModal();
            }).catch(() => {
                this.createNotificationError({
                    message: this.$tc('global.notification.notificationSaveErrorMessage', 0, {
                        entityName: this.promotion.name
                    })
                });
            });
        },

        createNewDiscount() {
            const discount = this.promotionDiscountRepository.create(Shopware.Context.api);
            Object.assign(discount, {
                promotionId: this.promotion.id,
                value: 0,
                considerAdvancedRules: false,
                sorterKey: 'PRICE_ASC',
                usageKey: 'ALL'
            });

            return discount;
        }
    }
});
