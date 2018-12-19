import { Component, Mixin, State } from 'src/core/shopware';
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
        condition: {
            type: Object,
            required: false,
            default() {
                return {};
            }
        }
    },

    data() {
        return {
            conditionFields: {}
        };
    },

    created() {
        this.createdComponent();
    },

    methods: {
        getStore(name) {
            return State.getStore(name);
        },
        createdComponent() {
            if (!this.condition.value) {
                this.condition.value = {};
            }
            const conditionType = this.condition.type;
            this.conditionFields = this.ruleConditionService.getByType(conditionType).fields;
        },

        handleConditionChange(event) {
            this.condition.type = event.target.value;
            this.conditionFields = this.ruleConditionService.getByType(this.condition.type).fields;
            Object.keys(this.condition.value).forEach((key) => {
                delete this.condition.value[key];
            });
            this.$forceUpdate();
        }
    }
});
