import template from './sw-condition-always-valid.html.twig';

const { Component } = Shopware;
const { mapPropertyErrors } = Component.getComponentHelper();

/**
 * @public
 * @package business-ops
 * @description Always valid condition item for the condition-tree. This component must be a child of sw-condition-tree.
 * @status prototype
 * @example-type code-only
 * @component-example
 * <sw-condition-is-always-valid :condition="condition"></sw-condition-is-always-valid>
 */
Component.extend('sw-condition-is-always-valid', 'sw-condition-base', {
    template,

    computed: {
        isAlwaysValid() {
            return true;
        },
        defaultValues() {
            return {
                isAlwaysValid: true,
            };
        },
        selectValues() {
            return [
                {
                    label: this.$tc('global.sw-condition.condition.yes'),
                    value: true,
                },
            ];
        },
        ...mapPropertyErrors('condition', ['value.isNew']),

        currentError() {
            return this.conditionValueIsNewError;
        },
    },
});
