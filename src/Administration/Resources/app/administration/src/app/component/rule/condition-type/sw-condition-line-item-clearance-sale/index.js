import template from './sw-condition-line-item-clearance-sale.html.twig';

const { Component } = Shopware;
const { mapPropertyErrors } = Component.getComponentHelper();

/**
 * @package business-ops
 * @deprecated tag:v6.5.0 This rule component will be removed. Use sw-condition-generic-line-item instead.
 */
Component.extend('sw-condition-line-item-clearance-sale', 'sw-condition-base-line-item', {
    template,

    computed: {
        clearanceSale: {
            get() {
                this.ensureValueExist();
                return !!this.condition.value.clearanceSale;
            },
            set(clearanceSale) {
                this.ensureValueExist();
                this.condition.value = { ...this.condition.value, clearanceSale };
            },
        },
        trueOption() {
            return { value: true, label: this.$tc('global.sw-condition.condition.yes') };
        },
        falseOption() {
            return { value: false, label: this.$tc('global.sw-condition.condition.no') };
        },

        options() {
            return [this.trueOption, this.falseOption];
        },

        ...mapPropertyErrors('condition', ['value.clearanceSale']),

        currentError() {
            return this.conditionValueClearanceSaleError;
        },
    },
});
