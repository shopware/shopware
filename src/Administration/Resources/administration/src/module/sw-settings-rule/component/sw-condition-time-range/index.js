import { Component } from 'src/core/shopware';
import template from './sw-condition-time-range.html.twig';

const defaultTimeValue = '12:00';

Component.extend('sw-condition-time-range', 'sw-condition-base', {
    template,

    computed: {
        fieldNames() {
            return ['fromTime', 'toTime'];
        },
        defaultValues() {
            return {
                fromTime: defaultTimeValue,
                toTime: defaultTimeValue
            };
        }
    },

    data() {
        return {
            datepickerConfig: {
                enableTime: true
            }
        };
    }
});
