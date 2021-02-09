import template from './sw-promotion-v2-settings-discount-type.html.twig';

const { Component } = Shopware;

Component.register('sw-promotion-v2-settings-discount-type', {
    template,

    inject: [
        'acl',
        'repositoryFactory'
    ],

    props: {
        discount: {
            type: Object,
            required: true
        },

        discountScope: {
            type: String,
            required: true,
            validator(value) {
                return ['basic', 'buy-x-get-y', 'shipping-discount'].includes(value);
            }
        },

        preselectedDiscountType: {
            type: String,
            required: false,
            validator(value) {
                return ['fixed', 'fixed_unit', 'percentage', 'free'].includes(value);
            },
            default() {
                return 'fixed';
            }
        },

        preselectedApplyDiscountTo: {
            type: String,
            required: false,
            validator(value) {
                return ['ALL', 'SELECT'].includes(value);
            },
            default() {
                return 'ALL';
            }
        }
    },

    computed: {
        labelValue() {
            return this.$tc(
                'sw-promotion-v2.detail.discounts.settings.discountType.form.labelValue',
                this.discount.type !== 'percentage'
            );
        }
    },

    watch: {
        'discount.type'(value, oldValue) {
            if (oldValue === 'percentage') {
                this.discount.maxValue = null;
            }

            if (value === 'free') {
                this.discount.applierKey = 'SELECT';

                return;
            }

            if (value === 'percentage') {
                this.discount.value = Math.min(this.discount.value, 100);
            }
        }
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            if (!this.discount.isNew) {
                return;
            }

            let config = {};
            if (this.discountScope === 'basic') {
                config = {
                    scope: 'cart',
                    type: this.preselectedDiscountType,
                    applierKey: this.preselectedApplyDiscountTo
                };
            }

            Object.assign(this.discount, config);
        },

        getDiscountTypeSelection() {
            const prefix = 'sw-promotion-v2.detail.discounts.settings.discountType.form.discountTypeSelection';
            return [{
                value: 'percentage',
                display: this.$tc(`${prefix}.displayPercentage`)
            }, {
                value: 'fixed',
                display: this.$tc(`${prefix}.displayFixedDiscount`)
            }, {
                value: 'fixed_unit',
                display: this.$tc(`${prefix}.displayFixedPrice`)
            }, {
                value: 'free',
                display: this.$tc(`${prefix}.displayFree`)
            }];
        },

        getApplyDiscountToSelection() {
            const prefix = 'sw-promotion-v2.detail.discounts.settings.discountType.form.applyDiscountTo';

            return [{
                value: 'ALL',
                display: this.$tc(`${prefix}.displayTotalPrice`)
            }, {
                value: 'SELECT',
                display: this.$tc(`${prefix}.displayProductPrice`)
            }];
        }
    }
});
