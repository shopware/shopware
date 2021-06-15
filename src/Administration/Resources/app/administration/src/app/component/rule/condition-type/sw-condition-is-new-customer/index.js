import template from './sw-condition-is-new-customer.html.twig';

const { Component } = Shopware;
const { mapPropertyErrors } = Component.getComponentHelper();

/**
 * @public
 * @description Condition for the IsNewCustomerRule. This component must a be child of sw-condition-tree.
 * @status prototype
 * @example-type code-only
 * @component-example
 * <sw-condition-is-new-customer :condition="condition" :level="0"></sw-condition-is-new-customer>
 */
Component.extend('sw-condition-is-new-customer', 'sw-condition-base', {
    template,

    computed: {
        selectValues() {
            return [
                {
                    label: this.$tc('global.sw-condition.condition.yes'),
                    value: true,
                },
                {
                    label: this.$tc('global.sw-condition.condition.no'),
                    value: false,
                },
            ];
        },

        isNewCustomer: {
            get() {
                this.ensureValueExist();
                return this.condition.value.isNew;
            },
            set(isNew) {
                this.ensureValueExist();
                this.condition.value = { ...this.condition.value, isNew };
            },
        },

        ...mapPropertyErrors('condition', ['value.isNew']),

        currentError() {
            return this.conditionValueIsNewError;
        },
    },
});
