import { Component } from 'src/core/shopware';
import LocalStore from 'src/core/data/LocalStore';
import template from './sw-condition-date-range.html.twig';

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
                    label: this.$tc('global.sw-condition.condition.yes'),
                    value: 'true'
                },
                {
                    label: this.$tc('global.sw-condition.condition.no'),
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
                this.condition.value.fromDate = this.convertValueToAtom(newValue);
            }
        },
        toDate: {
            handler(newValue) {
                if (!newValue) {
                    this.condition.value.toDate = null;
                    return;
                }
                this.condition.value.toDate = this.convertValueToAtom(newValue);
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
    },

    methods: {
        convertValueToAtom(value) {
            let date = new Date(value);
            date = new Date(Date.UTC(
                date.getFullYear(),
                date.getMonth(),
                date.getDate(),
                date.getHours(),
                date.getMinutes(),
                date.getSeconds()
            ));

            let dateString = date.toISOString();
            dateString = dateString.substring(0, dateString.length - 5);
            dateString += '+00:00';
            return dateString;
        }
    }
});
