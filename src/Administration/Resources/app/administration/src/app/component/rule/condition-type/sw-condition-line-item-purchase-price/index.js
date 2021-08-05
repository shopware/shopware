import template from './sw-condition-line-item-purchase-price.html.twig';
import './sw-condition-line-item-purchase-price.scss';

const { Component } = Shopware;
const { mapPropertyErrors } = Component.getComponentHelper();

Component.extend('sw-condition-line-item-purchase-price', 'sw-condition-base', {
    template,

    inject: ['feature'],

    computed: {
        operators() {
            return this.conditionDataProviderService.addEmptyOperatorToOperatorSet(
                this.conditionDataProviderService.getOperatorSet('number'),
            );
        },

        isNetOperators() {
            return this.conditionDataProviderService.getOperatorSet('isNet');
        },

        amount: {
            get() {
                this.ensureValueExist();
                return this.condition.value.amount;
            },
            set(amount) {
                this.ensureValueExist();
                this.condition.value = { ...this.condition.value, amount };
            },
        },

        ...mapPropertyErrors('condition', ['value.operator', 'value.amount']),

        currentError() {
            return this.conditionValueIsNetError
                || this.conditionValueOperatorError
                || this.conditionValueAmountError;
        },
    },

    watch: {
        operator() {
            if (this.isEmpty) {
                delete this.condition.value.amount;
            }
        },
    },
});
