import { Component, Mixin } from 'src/core/shopware';
import template from './sw-condition-base.html.twig';
import './sw-condition-base.less';

/**
 * @public
 * @description TODO: Add description
 * @status prototype
 * @example-type code-only
 * @component-example
 * <sw-condition-base :condition="condition"></sw-condition-and-container>
 */
Component.register('sw-condition-base', {
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

    computed: {
        fieldNames() {
            return [];
        }
    },

    mounted() {
        this.mountComponent();
    },

    methods: {
        mountComponent() {
            if (!this.condition.value) {
                this.condition.value = {};
            }

            Object.keys(this.condition.value).forEach((key) => {
                if (!this.fieldNames.includes(key)) {
                    delete this.condition.value[key];
                }
            });

            const keys = Object.keys(this.condition.value);
            this.fieldNames.forEach((fieldName) => {
                if (!keys.includes(fieldName)) {
                    this.condition.value[fieldName] = undefined;
                }
            });
        },
        getLabel(type) {
            return this.ruleConditionService.getByType(type).label;
        }
    }
});
