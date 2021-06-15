import template from './sw-condition-operator-select.html.twig';
import './sw-condition-operator-select.scss';

const { Component } = Shopware;

Component.register('sw-condition-operator-select', {
    template: template,

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
                    this.condition.value = {};
                }
                this.condition.value = { ...this.condition.value, operator };
            },
        },

        translatedOperators() {
            return this.operators.map(({ identifier, label }) => {
                return {
                    identifier,
                    label: this.$tc(label),
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
