import template from './sw-condition-base.html.twig';
import './sw-condition-base.scss';

const ShopwareError = Shopware.Classes.ShopwareError;

/**
 * @private
 * @sw-package fundamentals@after-sales
 * @description Base condition for the condition-tree. This component must be a child of sw-condition-tree.
 * @status prototype
 * @example-type code-only
 * @component-example
 * <sw-condition-base :condition="condition"></sw-condition-base>
 */
export default {
    template,

    inheritAttrs: false,

    inject: [
        'conditionDataProviderService',
        'availableTypes',
        'childAssociationField',
        'availableGroups',
    ],

    provide() {
        return {
            conditionType: this.conditionTypeIdentifier,
        };
    },

    emits: [
        'create-before',
        'create-after',
        'condition-delete',
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
        conditionTypeIdentifier() {
            return this.condition?.type ?? null;
        },

        conditionClasses() {
            return {
                'has--error': this.hasError,
                'is--disabled': this.isDisabled,
            };
        },

        errorTree() {
            if (!this.condition?.id) {
                return null;
            }

            return Shopware.Store.get('error').getErrorsForEntity('rule_condition', this.condition.id);
        },

        fieldErrors() {
            const valueErrors = this.errorTree?.value;

            if (!valueErrors) {
                return {};
            }

            return Object.entries(valueErrors).reduce(
                (
                    acc,
                    [
                        key,
                        node,
                    ],
                ) => {
                    if (node instanceof ShopwareError) {
                        acc[key] = node;
                    }
                    return acc;
                },
                {},
            );
        },

        typeError() {
            const node = this.errorTree?.type;

            return node instanceof ShopwareError ? node : null;
        },

        errorCount() {
            return Object.keys(this.fieldErrors).length + (this.typeError ? 1 : 0);
        },

        listedErrors() {
            if (!this.typeError) {
                return this.fieldErrors;
            }

            return {
                type: this.typeError,
                ...this.fieldErrors,
            };
        },

        currentError() {
            return this.typeError ?? Object.values(this.fieldErrors)[0] ?? null;
        },

        hasError() {
            return this.errorCount > 0;
        },

        valueErrorPath() {
            return `${this.condition.getEntityName()}.${this.condition.id}.value`;
        },

        value() {
            return this.condition.value;
        },

        isDisabled() {
            return this.disabled || this.hasNoComponent;
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
                Shopware.Store.get('error').removeApiError(this.valueErrorPath);
            }

            if (this.isEmpty && !!this.inputKey) {
                delete this.condition.value[this.inputKey];
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
};
