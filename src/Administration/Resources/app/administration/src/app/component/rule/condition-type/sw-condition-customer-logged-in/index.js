import template from './sw-condition-customer-logged-in.html.twig';

const { Component } = Shopware;
const { mapPropertyErrors } = Component.getComponentHelper();

/**
 * @public
 * @description Condition for the CustomerLoggedInRule. This component must a be child of sw-condition-tree.
 * @status prototype
 * @example-type code-only
 * @component-example
 * <sw-condition-customer-logged-in :condition="condition" :level="0"></sw-condition-customer-logged-in>
 */
Component.extend('sw-condition-customer-logged-in', 'sw-condition-base', {
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

        isLoggedIn: {
            get() {
                this.ensureValueExist();
                return this.condition.value.isLoggedIn;
            },
            set(isLoggedIn) {
                this.ensureValueExist();
                this.condition.value = { ...this.condition.value, isLoggedIn };
            },
        },

        ...mapPropertyErrors('condition', ['value.isLoggedIn']),

        currentError() {
            return this.conditionValueIsLoggedInError;
        },
    },
});
