import LocalStore from 'src/core/data/LocalStore';
import template from './sw-condition-date-range.html.twig';

const { Component } = Shopware;

/**
 * @public
 * @description Condition for the DateRangeRule. This component must a be child of sw-condition-tree.
 * @status prototype
 * @example-type code-only
 * @component-example
 * <sw-condition-date-range :condition="condition" :level="0"></sw-condition-date-range>
 */
Component.extend('sw-condition-date-range', 'sw-condition-base', {
    template,

    computed: {
        selectValues() {
            const values = [
                {
                    label: this.$tc('global.sw-condition.condition.withTime'),
                    value: 'true'
                },
                {
                    label: this.$tc('global.sw-condition.condition.withoutTime'),
                    value: 'false'
                }
            ];

            return new LocalStore(values, 'value');
        },
        fieldNames() {
            return ['fromDate', 'toDate', 'useTime'];
        },
        defaultValues() {
            return {
                useTime: false
            };
        }
    },

    watch: {
        useTime: {
            handler(newValue) {
                this.condition.value.useTime = newValue === String(true);
                this.$set(this.datepickerConfig, 'enableTime', this.condition.value.useTime);
            }
        },
        fromDate: {
            handler(newValue) {
                if (!newValue) {
                    this.condition.value.fromDate = null;
                    return;
                }
                this.condition.value.fromDate = newValue;
            }
        },
        toDate: {
            handler(newValue) {
                if (!newValue) {
                    this.condition.value.toDate = null;
                    return;
                }
                this.condition.value.toDate = newValue;
            }
        }
    },

    data() {
        return {
            datepickerConfig: {},
            fromDate: this.condition.value.fromDate,
            toDate: this.condition.value.toDate,
            useTime: this.condition.value.useTime ? String(this.condition.value.useTime) : String(false)
        };
    }
});
