import template from './sw-promotion-v2-settings-discount-type.html.twig';

const { Component } = Shopware;

Component.register('sw-promotion-v2-settings-discount-type', {
    template,

    props: {
        preselectedDiscountType: {
            type: String,
            required: false,
            validator(value) {
                return ['fixed_price', 'fixed_discount', 'percentage', 'free'].includes(value);
            },
            default() {
                return 'fixed_price';
            }
        },
        preselectedApplyDiscountTo: {
            type: String,
            required: false,
            validator(value) {
                return ['total_price', 'product_price'].includes(value);
            },
            default() {
                return 'total_price';
            }
        },
        preselectedApplyTo: {
            type: String,
            required: false,
            validator(value) {
                return ['net', 'gross'].includes(value);
            },
            default() {
                return 'gross';
            }
        }
    },

    data() {
        return {
            discountType: this.preselectedDiscountType,
            applyDiscountTo: this.preselectedApplyDiscountTo,
            applyTo: this.preselectedApplyTo
        };
    },

    methods: {
        getDiscountTypeSelection() {
            const prefix = 'sw-promotion-v2.detail.discounts.settings.discountType.form.discountTypeSelection';

            return [{
                value: 'fixed_price',
                display: this.$tc(`${prefix}.displayFixedPrice`)
            }, {
                value: 'fixed_discount',
                display: this.$tc(`${prefix}.displayFixedDiscount`)
            }, {
                value: 'percentage',
                display: this.$tc(`${prefix}.displayPercentage`)
            }, {
                value: 'free',
                display: this.$tc(`${prefix}.displayFree`)
            }];
        },

        getApplyDiscountToSelection() {
            const prefix = 'sw-promotion-v2.detail.discounts.settings.discountType.form.applyDiscountTo';

            return [{
                value: 'total_price',
                display: this.$tc(`${prefix}.displayTotalPrice`)
            }, {
                value: 'product_price',
                display: this.$tc(`${prefix}.displayProductPrice`)
            }];
        },

        getApplyToSelection() {
            const prefix = 'sw-promotion-v2.detail.discounts.settings.discountType.form.applyTo';

            return [{
                value: 'net',
                display: this.$tc(`${prefix}.displayNet`)
            }, {
                value: 'gross',
                display: this.$tc(`${prefix}.displayGross`)
            }];
        }
    }
});
