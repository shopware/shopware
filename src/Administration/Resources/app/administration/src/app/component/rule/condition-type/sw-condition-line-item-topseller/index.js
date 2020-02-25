import template from './sw-condition-line-item-topseller.html.twig';

const { Component } = Shopware;
const { mapPropertyErrors } = Component.getComponentHelper();

Component.extend('sw-condition-line-item-topseller', 'sw-condition-base', {
    template,

    computed: {
        isTopseller: {
            get() {
                this.ensureValueExist();
                return !!this.condition.value.isTopseller;
            },
            set(isTopseller) {
                this.ensureValueExist();
                this.condition.value = { ...this.condition.value, isTopseller };
            }
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

        ...mapPropertyErrors('condition', ['value.isTopseller']),

        currentError() {
            return this.conditionValueIsTopsellerError;
        }
    }
});
