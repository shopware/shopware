import template from './sw-condition-last-name.html.twig';

const { Component } = Shopware;
const { mapPropertyErrors } = Component.getComponentHelper();

/**
 * @public
 * @description Condition for the LastNameRule. This component must a be child of sw-condition-tree.
 * @status prototype
 * @example-type code-only
 * @component-example
 * <sw-condition-last-name :condition="condition" :level="0"></sw-condition-last-name>
 */
Component.extend('sw-condition-last-name', 'sw-condition-base', {
    template,

    data() {
        return {
            inputKey: 'lastName',
        };
    },

    computed: {
        operators() {
            return this.conditionDataProviderService.addEmptyOperatorToOperatorSet(
                this.conditionDataProviderService.getOperatorSet('string'),
            );
        },

        lastName: {
            get() {
                this.ensureValueExist();
                return this.condition.value.lastName;
            },
            set(lastName) {
                this.ensureValueExist();
                this.condition.value = { ...this.condition.value, lastName };
            },
        },

        ...mapPropertyErrors('condition', ['value.operator', 'value.lastName']),

        currentError() {
            return this.conditionValueOperatorError || this.conditionValueLastNameError;
        },
    },
});
