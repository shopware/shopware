import { Component } from 'src/core/shopware';
import utils from 'src/core/service/util.service';
import LocalStore from 'src/core/data/LocalStore';
import template from './sw-condition-operator-select.html.twig';

/**
 * @public
 * @description Provides the operator select for the sw-condition-base.
 * @status prototype
 * @example-type code-only
 * @component-example
 * <sw-condition-operator-select operatorSetName="multiStore" v-model="operator"></sw-condition-operator-select>
 */
Component.register('sw-condition-operator-select', {
    template,
    inject: ['ruleConditionDataProviderService'],

    props: {
        operatorSetName: {
            type: String,
            required: true
        },
        value: {
            required: true
        }
    },

    watch: {
        value: {
            immediate: true,
            handler(newValue) {
                this.operatorValue = newValue;
            }
        }
    },

    computed: {
        operators() {
            const operators = this.ruleConditionDataProviderService.getOperatorSet(this.operatorSetName, (operator) => {
                if (operator.meta) {
                    return;
                }

                operator.translated.label = this.$tc(operator.label);
            });

            return new LocalStore(operators, 'identifier');
        }
    },

    data() {
        return {
            operatorValue: null
        };
    },

    methods: {
        createId() {
            return utils.createId();
        }
    }
});
