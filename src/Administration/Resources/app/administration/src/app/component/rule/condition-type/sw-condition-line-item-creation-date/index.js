import template from './sw-condition-line-item-creation-date.html.twig';

const { Component } = Shopware;
const { mapPropertyErrors } = Component.getComponentHelper();

Component.extend('sw-condition-line-item-creation-date', 'sw-condition-base', {
    template,

    computed: {

        operators() {
            return this.conditionDataProviderService.getOperatorSet('date');
        },

        lineItemCreationDate: {
            get() {
                this.ensureValueExist();
                return this.condition.value.lineItemCreationDate || null;
            },
            set(lineItemCreationDate) {
                this.ensureValueExist();
                this.condition.value = { ...this.condition.value, lineItemCreationDate };
            }
        },

        ...mapPropertyErrors('condition', ['value.useTime', 'value.lineItemCreationDate']),

        currentError() {
            return this.conditionValueLineItemCreationDateError;
        }
    }
});
