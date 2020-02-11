import template from './sw-condition-line-item-dimension-height.html.twig';

const { Component } = Shopware;
const { mapApiErrors } = Component.getComponentHelper();

Component.extend('sw-condition-line-item-dimension-height', 'sw-condition-base', {
    template,

    computed: {
        operators() {
            return this.conditionDataProviderService.getOperatorSet('number');
        },

        amount: {
            get() {
                this.ensureValueExist();
                return this.condition.value.amount;
            },
            set(amount) {
                this.ensureValueExist();
                this.condition.value = { ...this.condition.value, amount };
            }
        },

        ...mapApiErrors('condition', ['value.operator', 'value.amount']),

        currentError() {
            return this.conditionValueOperatorError || this.conditionValueAmountError;
        }
    }
});
