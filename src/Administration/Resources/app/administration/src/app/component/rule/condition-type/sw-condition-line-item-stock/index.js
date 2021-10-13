import template from './sw-condition-line-item-stock.html.twig';

const { Component } = Shopware;
const { mapPropertyErrors } = Component.getComponentHelper();

Component.extend('sw-condition-line-item-stock', 'sw-condition-base-line-item', {
    template,

    data() {
        return {
            inputKey: 'stock',
        };
    },

    computed: {
        operators() {
            return this.conditionDataProviderService.getOperatorSet('number');
        },

        stock: {
            get() {
                this.ensureValueExist();
                return this.condition.value.stock;
            },
            set(stock) {
                this.ensureValueExist();
                this.condition.value = { ...this.condition.value, stock };
            },
        },

        ...mapPropertyErrors('condition', ['value.operator', 'value.stock']),

        currentError() {
            return this.conditionValueOperatorError || this.conditionValueStockError;
        },
    },
});
