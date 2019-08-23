import template from './sw-condition-time-range.html.twig';

const { Component } = Shopware;
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
                enableTime: true,
                dateFormat: 'H:i',
                altFormat: 'H:i'
            }
        };
    }
});
