import template from './sw-condition-volume-of-cart.html.twig';

const { Component } = Shopware;
const { mapPropertyErrors } = Component.getComponentHelper();

/**
 * @public
 * @description Condition for the CartVolumeRule. This component must a be child of sw-condition-tree.
 * @status prototype
 * @example-type code-only
 * @component-example
 * <sw-condition-volume-of-cart :condition="condition" :level="0"></sw-condition-volume-of-cart>
 */
Component.extend('sw-condition-volume-of-cart', 'sw-condition-base', {
    template,

    computed: {
        operators() {
            return this.conditionDataProviderService.getOperatorSet('number');
        },

        volume: {
            get() {
                this.ensureValueExist();
                return this.condition.value.volume;
            },
            set(volume) {
                this.ensureValueExist();
                this.condition.value = { ...this.condition.value, volume };
            },
        },

        ...mapPropertyErrors('condition', ['value.operator', 'value.volume']),

        currentError() {
            return this.conditionValueOperatorError || this.conditionValueVolumeError;
        },
    },
});
