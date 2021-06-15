import template from './sw-condition-line-item-with-quantity.html.twig';
import './sw-condition-line-item-with-quantity.scss';

const { Component } = Shopware;
const { mapPropertyErrors } = Component.getComponentHelper();

/**
 * @public
 * @description Condition for the LineItemWithQuantityRule. This component must a be child of sw-condition-tree.
 * @status prototype
 * @example-type code-only
 * @component-example
 * <sw-condition-line-item-with-quantity :condition="condition" :level="0"></sw-condition-line-item-with-quantity>
 */
Component.extend('sw-condition-line-item-with-quantity', 'sw-condition-base', {
    template,

    inject: ['repositoryFactory'],

    data() {
        return {
            initialProduct: null,
        };
    },

    computed: {
        operators() {
            return this.conditionDataProviderService.getOperatorSet('number');
        },

        productRepository() {
            return this.repositoryFactory.create('product');
        },

        quantity: {
            get() {
                this.ensureValueExist();
                return this.condition.value.quantity;
            },
            set(quantity) {
                this.ensureValueExist();
                this.condition.value = { ...this.condition.value, quantity };
            },
        },

        id: {
            get() {
                this.ensureValueExist();
                return this.condition.value.id;
            },
            set(id) {
                this.ensureValueExist();
                this.condition.value = { ...this.condition.value, id };
            },
        },

        ...mapPropertyErrors('condition', ['value.operator', 'value.quantity', 'value.id']),

        currentError() {
            return this.conditionValueOperatorError || this.conditionValueQuantityError || this.conditionValueIdError;
        },
    },
});
