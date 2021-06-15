import template from './sw-condition-cart-has-delivery-free-item.html.twig';

const { Component } = Shopware;
const { mapPropertyErrors } = Component.getComponentHelper();

Component.extend('sw-condition-cart-has-delivery-free-item', 'sw-condition-base', {
    template,

    computed: {
        allowed: {
            get() {
                this.ensureValueExist();
                return !!this.condition.value.allowed;
            },
            set(allowed) {
                this.ensureValueExist();
                this.condition.value = { ...this.condition.value, allowed };
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

        ...mapPropertyErrors('condition', ['value.allowed']),

        currentError() {
            return this.conditionValueAllowedError;
        },
    },
});
