import template from './sw-condition-base.html.twig';
import './sw-condition-base.scss';

const { Component } = Shopware;
const { mapPropertyErrors } = Component.getComponentHelper();

/**
 * @public
 * @description Base condition for the condition-tree. This component must be a child of sw-condition-tree.
 * @status prototype
 * @example-type code-only
 * @component-example
 * <sw-condition-base :condition="condition"></sw-condition-base>
 */
Component.register('sw-condition-base', {
    template,
    inheritAttrs: false,

    inject: [
        'conditionDataProviderService',
        'availableTypes',
        'childAssociationField',
    ],

    props: {
        condition: {
            type: Object,
            required: false,
            default: null,
        },

        disabled: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    computed: {
        conditionClasses() {
            return {
                'has--error': this.hasError,
                'is--disabled': this.hasNoComponent || this.disabled,
            };
        },

        ...mapPropertyErrors('condition', ['type']),

        currentError() {
            return this.conditionTypeError;
        },

        hasError() {
            return this.currentError !== null;
        },

        valueErrorPath() {
            return `${this.condition.getEntityName()}.${this.condition.id}.value`;
        },

        value() {
            return this.condition.value;
        },

        hasNoComponent() {
            const component = this.conditionDataProviderService.getComponentByCondition(this.condition);

            return component === 'sw-condition-not-found';
        },

        operator() {
            return this.condition.value?.operator ?? null;
        },

        isEmpty() {
            return this.operator === this.conditionDataProviderService.getOperatorSet('empty')[0].identifier;
        },
    },

    watch: {
        value() {
            if (this.hasError) {
                this.$store.commit('error/removeApiError', { expression: this.valueErrorPath });
            }
            if (this.isEmpty && !!this.inputKey) {
                this.$delete(this.condition.value, this.inputKey);
            }
        },
    },

    methods: {
        onCreateBefore() {
            this.$emit('create-before');
        },

        onCreateAfter() {
            this.$emit('create-after');
        },

        onDeleteCondition() {
            this.$emit('condition-delete');
        },

        ensureValueExist() {
            if (typeof this.condition.value === 'undefined' || this.condition.value === null) {
                this.condition.value = {};
            }
        },
    },
});
