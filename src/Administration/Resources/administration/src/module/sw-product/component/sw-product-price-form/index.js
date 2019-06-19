import { Component, Mixin } from 'src/core/shopware';
import { mapState, mapGetters } from 'vuex';
import template from './sw-product-price-form.html.twig';
import './sw-product-price-form.scss';

Component.register('sw-product-price-form', {
    template,

    mixins: [
        Mixin.getByName('placeholder')
    ],

    computed: {
        productTaxRate() {
            if (!this.taxes.items) {
                return {};
            }

            if (this.$refs.taxIdInheritation) {
                const isInherited = this.$refs.taxIdInheritation.isInheritField && this.$refs.taxIdInheritation.isInherited;
                return Object.values(this.taxes.items).find((taxRate) => {
                    const taxId = isInherited ? this.parentProduct.taxId : this.product.taxId;
                    return taxRate.id === taxId;
                });
            }

            return {};
        },

        defaultCurrency() {
            if (!this.currencies.items) {
                return {};
            }

            return Object.values(this.currencies.items).find((currency) => {
                return currency.isDefault;
            });
        },

        ...mapGetters('swProductDetail', [
            'isLoading'
        ]),

        ...mapState('swProductDetail', [
            'product',
            'parentProduct',
            'taxes',
            'currencies'
        ])
    }
});
