import { Component } from 'src/core/shopware';
import template from './sw-condition-date-range.html.twig';

/**
 * @public
 * @description TODO: Add description
 * @status prototype
 * @example-type code-only
 * @component-example
 * <sw-condition-date-range :condition="condition"></sw-condition-date-range>
 */
Component.extend('sw-condition-date-range', 'sw-condition-base', {
    template,

    computed: {
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
        'condition.value.useTime': {
            handler() {
                this.$set(this.datepickerConfig, 'enableTime', !!this.condition.value.useTime);
            }
        },
        fromDate: {
            handler(newValue) {
                this.condition.value.fromDate = this.convertValueToAtom(newValue);
            }
        },
        toDate: {
            handler(newValue) {
                this.condition.value.toDate = this.convertValueToAtom(newValue);
            }
        }
    },

    data() {
        return {
            datepickerConfig: {},
            fromDate: this.condition.value.fromDate,
            toDate: this.condition.value.toDate
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
