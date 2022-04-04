import template from './sw-condition-days-since-last-order.html.twig';

const { Component } = Shopware;
const { mapPropertyErrors } = Component.getComponentHelper();

/**
 * @public
 * @description "Days since last order" item for the condition-tree. This component must be a child of sw-condition-tree.
 * @status prototype
 * @example-type code-only
 * @component-example
 * <sw-condition-days-since-last-order :condition="condition"></sw-condition-days-since-last-order>
 */
Component.extend('sw-condition-days-since-last-order', 'sw-condition-base', {
    template,

    data() {
        return {
            inputKey: 'daysPassed',
        };
    },

    computed: {
        operators() {
            return this.conditionDataProviderService.addEmptyOperatorToOperatorSet(
                this.conditionDataProviderService.getOperatorSet('number'),
            );
        },

        daysPassed: {
            get() {
                this.ensureValueExist();
                return this.condition.value.daysPassed;
            },
            set(daysPassed) {
                this.ensureValueExist();
                this.condition.value = { ...this.condition.value, daysPassed };
            },
        },

        ...mapPropertyErrors('condition', ['value.operator', 'value.daysPassed']),

        currentError() {
            return this.conditionValueOperatorError || this.conditionValueDaysPassedError;
        },
    },
});
