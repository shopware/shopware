import template from './sw-condition-day-of-week.html.twig';

const { Component } = Shopware;
const { mapPropertyErrors } = Component.getComponentHelper();

/**
 * @public
 * @description Condition for the WeekDayRule. This component must a be child of sw-condition-tree.
 * @status prototype
 * @example-type code-only
 * @component-example
 * <sw-condition-day-of-week :condition="condition" :level="0"></sw-condition-day-of-week>
 */
Component.extend('sw-condition-day-of-week', 'sw-condition-base', {
    template,

    computed: {
        operators() {
            return this.conditionDataProviderService.getOperatorSet('string');
        },

        dayOfWeek: {
            get() {
                this.ensureValueExist();
                return this.condition.value.dayOfWeek;
            },
            set(dayOfWeek) {
                this.ensureValueExist();
                this.condition.value = { ...this.condition.value, dayOfWeek };
            },
        },

        weekdays() {
            return [
                {
                    label: this.$tc('global.day-of-week.monday'),
                    value: 1,
                },
                {
                    label: this.$tc('global.day-of-week.tuesday'),
                    value: 2,
                },
                {
                    label: this.$tc('global.day-of-week.wednesday'),
                    value: 3,
                },
                {
                    label: this.$tc('global.day-of-week.thursday'),
                    value: 4,
                },
                {
                    label: this.$tc('global.day-of-week.friday'),
                    value: 5,
                },
                {
                    label: this.$tc('global.day-of-week.saturday'),
                    value: 6,
                },
                {
                    label: this.$tc('global.day-of-week.sunday'),
                    value: 7,
                },
            ];
        },

        ...mapPropertyErrors('condition', ['value.operator', 'value.dayOfWeek']),

        currentError() {
            return this.conditionValueOperatorError || this.conditionValueDayOfWeekError;
        },
    },
});
