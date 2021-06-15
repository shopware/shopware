import template from './sw-condition-is-company.html.twig';

const { Component } = Shopware;
const { mapPropertyErrors } = Component.getComponentHelper();

/**
 * @public
 * @description Condition for the IsCompanyRule. This component must a be child of sw-condition-tree.
 * @status prototype
 * @example-type code-only
 * @component-example
 * <sw-condition-is-company :condition="condition" :level="0"></sw-condition-is-company>
 */
Component.extend('sw-condition-is-company', 'sw-condition-base', {
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

        isCompany: {
            get() {
                this.ensureValueExist();
                return this.condition.value.isCompany;
            },
            set(isCompany) {
                this.ensureValueExist();
                this.condition.value = { ...this.condition.value, isCompany };
            },
        },

        ...mapPropertyErrors('condition', ['value.isCompany']),

        currentError() {
            return this.conditionValueIsCompanyError;
        },
    },
});
