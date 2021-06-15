import template from './sw-condition-line-item-promoted.html.twig';

const { Component } = Shopware;
const { mapPropertyErrors } = Component.getComponentHelper();

Component.extend('sw-condition-line-item-promoted', 'sw-condition-base', {
    template,

    computed: {
        isPromoted: {
            get() {
                this.ensureValueExist();
                return !!this.condition.value.isPromoted;
            },
            set(isPromoted) {
                this.ensureValueExist();
                this.condition.value = { ...this.condition.value, isPromoted };
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

        ...mapPropertyErrors('condition', ['value.isPromoted']),

        currentError() {
            return this.conditionValueIsPromotedError;
        },
    },
});
