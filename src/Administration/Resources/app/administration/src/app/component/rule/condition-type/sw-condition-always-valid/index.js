import template from './sw-condition-always-valid.html.twig';

const { Component } = Shopware;
const { mapApiErrors } = Component.getComponentHelper();

Component.extend('sw-condition-is-always-valid', 'sw-condition-base', {
    template,

    computed: {
        isAlwaysValid() {
            return true;
        },
        defaultValues() {
            return {
                isAlwaysValid: true
            };
        },
        selectValues() {
            return [
                {
                    label: this.$tc('global.sw-condition.condition.yes'),
                    value: true
                }
            ];
        },
        ...mapApiErrors('condition', ['value.isNew']),

        currentError() {
            return this.conditionValueIsNewError;
        }
    }
});
