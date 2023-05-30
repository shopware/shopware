import template from './sw-promotion-v2-discounts.html.twig';
import './sw-promotion-v2-discounts.scss';

const { createId } = Shopware.Utils;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: [
        'acl',
        'repositoryFactory',
    ],

    mixins: [
        'notification',
    ],

    props: {
        promotion: {
            type: Object,
            required: true,
        },
    },

    data() {
        return {
            isActive: false,
            newDiscount: null,
            selectedDiscountType: 'basic',
            showDiscountModal: false,
        };
    },

    computed: {
        promotionDiscountRepository() {
            return this.repositoryFactory.create('promotion_discount');
        },
    },

    methods: {
        createWizardPageId() {
            return createId();
        },

        onButtonClick() {
            this.isActive = !this.isActive;
        },

        onChangeSelection(value) {
            this.selectedDiscountType = value;
        },

        onDeleteDiscount(discountId) {
            this.promotion.discounts = this.promotion.discounts.filter(discount => discount.id !== discountId);
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
            this.promotion.discounts.push(this.newDiscount);
            this.onCloseDiscountModal();
        },

        createNewDiscount() {
            const discount = this.promotionDiscountRepository.create();
            Object.assign(discount, {
                promotionId: this.promotion.id,
                value: 0,
                considerAdvancedRules: false,
                sorterKey: 'PRICE_ASC',
                usageKey: 'ALL',
            });

            return discount;
        },

        getScope(discount) {
            const typeMapping = {
                cart: 'basic',
                delivery: 'shipping-discount',
                set: 'buy-x-get-y',
                setgroup: 'buy-x-get-y',
            };

            return typeMapping[discount.scope.split('-')[0]];
        },

        getTitle(type, pageTitle) {
            return this.$tc(`sw-promotion-v2.detail.discounts.wizard.${type}.prefixTitle`, 0, {
                title: this.$tc(`sw-promotion-v2.detail.discounts.wizard.${type}.title${pageTitle}`),
            });
        },
    },
};
