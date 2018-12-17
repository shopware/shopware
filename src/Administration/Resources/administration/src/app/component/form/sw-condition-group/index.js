import { Component, Mixin } from 'src/core/shopware';
import template from './sw-condition-group.html.twig';
import './sw-condition-group.less';

/**
 * @public
 * @description Universal condition field group component which supports all basic conditions, operators
 * and field value types.
 * @status prototype
 * @example-type code-only
 * @component-example
 * <sw-condition-group :type="rule.type" :condition="condition"></sw-condition-group>
 */
Component.register('sw-condition-group', {
    template,

    inject: ['ruleConditionService'],
    mixins: [
        Mixin.getByName('validation'),
        Mixin.getByName('notification')
    ],

    /**
     * All additional passed attributes are bound explicit to the correct child element.
     */
    inheritAttrs: false,

    props: {
        label: {
            type: String,
            required: false,
            default: ''
        },
        condition: {
            type: Object,
            required: false,
            default() {
                return {};
            }
        },
        options: {
            type: Array,
            required: false,
            default: () => {
                return [];
            }
        }
    },

    data() {
        return {
            currentValue: null,
            boundExpression: '',
            conditionOperators: {}
        };
    },

    watch: {
        value(value) {
            this.currentConditionType = this.convertValueType(value);
        }
    },

    created() {
        this.createdComponent();
    },

    mounted() {
        this.mountedComponent();
    },

    methods: {
        mountedComponent() {
            this.conditionTypeSelect = this.$refs.conditionTypeSelect;
        },

        createdComponent() {
            const conditionType = this.condition.type;
            this.conditionOperators = this.ruleConditionService.getByType(conditionType).operatorSet;
        },

        handleConditionChange(event) {
            this.conditionOperators = this.ruleConditionService.getByType(event.target.value).operatorSet;
        },

        handleOperatorChange() {
            // todo
        }
    }
});
