import template from './sw-condition-time-range.html.twig';
import './sw-condition-time-range.scss';

const { Component } = Shopware;
const { mapPropertyErrors } = Component.getComponentHelper();
const defaultTimeValue = '12:00';

Component.extend('sw-condition-time-range', 'sw-condition-base', {
    template,

    data() {
        return {
            datepickerConfig: {
                enableTime: true,
                dateFormat: 'H:i',
                altFormat: 'H:i'
            }
        };
    },

    computed: {
        fromTime: {
            get() {
                this.ensureValueExist();
                if (!this.condition.value.fromTime) {
                    this.condition.value.fromTime = defaultTimeValue;
                }

                return this.condition.value.fromTime;
            },
            set(fromTime) {
                this.ensureValueExist();
                this.condition.value.fromTime = fromTime;
            }
        },
        toTime: {
            get() {
                this.ensureValueExist();
                if (!this.condition.value.toTime) {
                    this.condition.value.toTime = defaultTimeValue;
                }

                return this.condition.value.toTime;
            },
            set(toTime) {
                this.ensureValueExist();
                this.condition.value.toTime = toTime;
            }
        },

        ...mapPropertyErrors('condition', ['value.fromTime', 'value.toTime']),

        currentError() {
            return this.conditionValueFromTimeError || this.conditionValueToTimeError;
        }
    }
});
