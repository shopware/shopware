import template from './sw-condition-operator-select.html.twig';
import './sw-condition-operator-select.scss';

const { Component } = Shopware;

/**
 * @private
 * @package services-settings
 */
Component.register('sw-condition-operator-select', {
    template: template,

    compatConfig: Shopware.compatConfig,

    props: {
        operators: {
            type: Array,
            required: true,
        },

        condition: {
            type: Object,
            required: true,
        },

        disabled: {
            type: Boolean,
            required: false,
            default: false,
        },

        /**
         * The used condition snippets depend on the pre-operator snippets and should be plural or singular
         * depending on the pre-operator selection.
         */
        plural: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    computed: {
        operator: {
            get() {
                if (!this.condition.value) {
                    return null;
                }
                return this.condition.value.operator;
            },
            set(operator) {
                if (!this.condition.value) {
                    // eslint-disable-next-line vue/no-mutating-props
                    this.condition.value = {};
                }
                // eslint-disable-next-line vue/no-mutating-props
                this.condition.value = { ...this.condition.value, operator };
            },
        },

        translatedOperators() {
            return this.operators.map(({ identifier, label }) => {
                return {
                    identifier,
                    label: this.plural ? this.$tc(label, 2) : this.$tc(label),
                };
            });
        },
    },

    methods: {
        changeOperator(event) {
            this.operator = event;
        },
    },
});
