import template from './sw-condition-line-item-is-new.html.twig';

const { Component } = Shopware;
const { mapPropertyErrors } = Component.getComponentHelper();

Component.extend('sw-condition-line-item-is-new', 'sw-condition-base', {
    template,

    computed: {
        isNew: {
            get() {
                this.ensureValueExist();
                return !!this.condition.value.isNew;
            },
            set(isNew) {
                this.ensureValueExist();
                this.condition.value = { ...this.condition.value, isNew };
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

        ...mapPropertyErrors('condition', ['value.isNew']),

        currentError() {
            return this.conditionValueIsNewError;
        },
    },
});
