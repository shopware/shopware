import template from './sw-condition-line-item-creation-date.html.twig';
import './sw-condition-line-item-creation-date.scss';

const { Component } = Shopware;
const { mapPropertyErrors } = Component.getComponentHelper();

Component.extend('sw-condition-line-item-creation-date', 'sw-condition-base', {
    template,

    computed: {
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
