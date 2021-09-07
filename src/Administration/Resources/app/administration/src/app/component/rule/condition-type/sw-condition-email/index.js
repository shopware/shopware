import template from './sw-condition-email.html.twig';

const { Component } = Shopware;
const { mapPropertyErrors } = Component.getComponentHelper();

/**
 * @public
 * @description Condition for the EmailRule. This component must a be child of sw-condition-tree.
 * @status prototype
 * @example-type code-only
 * @component-example
 * <sw-condition-email :condition="condition" :level="0"></sw-condition-email>
 */
Component.extend('sw-condition-email', 'sw-condition-base', {
    template,

    data() {
        return {
            inputKey: 'email',
        };
    },

    computed: {
        operators() {
            return this.conditionDataProviderService.getOperatorSet('string');
        },

        email: {
            get() {
                this.ensureValueExist();
                return this.condition.value.email;
            },
            set(email) {
                this.ensureValueExist();
                this.condition.value = { ...this.condition.value, email };
            },
        },

        ...mapPropertyErrors('condition', ['value.operator', 'value.email']),

        currentError() {
            return this.conditionValueOperatorError || this.conditionValueEmailError;
        },
    },
});
