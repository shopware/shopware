import { Component } from 'src/core/shopware';
import LocalStore from 'src/core/data/LocalStore';
import template from './sw-condition-day-of-week.html.twig';

Component.extend('sw-condition-day-of-week', 'sw-condition-base', {
    template,

    computed: {
        fieldNames() {
            return ['operator', 'dayOfWeek'];
        },
        defaultValues() {
            return {
                dayOfWeek: 1
            };
        },
        weekdays() {
            const weekdays = [
                {
                    label: this.$tc('global.day-of-week.monday'),
                    value: 1
                },
                {
                    label: this.$tc('global.day-of-week.tuesday'),
                    value: 2
                },
                {
                    label: this.$tc('global.day-of-week.wednesday'),
                    value: 3
                },
                {
                    label: this.$tc('global.day-of-week.thursday'),
                    value: 4
                },
                {
                    label: this.$tc('global.day-of-week.friday'),
                    value: 5
                },
                {
                    label: this.$tc('global.day-of-week.saturday'),
                    value: 6
                },
                {
                    label: this.$tc('global.day-of-week.sunday'),
                    value: 7
                }
            ];

            return new LocalStore(weekdays, 'value');
        }
    }
});
