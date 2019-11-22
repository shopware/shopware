import template from './sw-condition-days-since-last-order.html.twig';

const { Component } = Shopware;
const { mapApiErrors } = Component.getComponentHelper();

Component.extend('sw-condition-days-since-last-order', 'sw-condition-base', {
    template,

    computed: {
        operators() {
            return this.conditionDataProviderService.getOperatorSet('number');
        },

        daysPassed: {
            get() {
                this.ensureValueExist();
                return this.condition.value.daysPassed;
            },
            set(daysPassed) {
                this.ensureValueExist();
                this.condition.value = { ...this.condition.value, daysPassed };
            }
        },

        ...mapApiErrors('condition', ['value.operator', 'value.daysPassed']),

        currentError() {
            return this.conditionValueOperatorError || this.conditionValueDaysPassedError;
        }
    }
});
