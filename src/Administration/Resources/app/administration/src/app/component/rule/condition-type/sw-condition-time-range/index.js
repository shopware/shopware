import template from './sw-condition-time-range.html.twig';
import './sw-condition-time-range.scss';

/**
 * @sw-package fundamentals@after-sales
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    data() {
        return {
            datepickerConfig: {
                enableTime: true,
                dateFormat: 'H:i',
            },
        };
    },

    computed: {
        fromTime: {
            get() {
                this.ensureValueExist();

                return this.condition.value.fromTime ?? null;
            },
            set(fromTime) {
                this.ensureValueExist();

                this.condition.value = {
                    ...this.condition.value,
                    fromTime,
                };
            },
        },

        toTime: {
            get() {
                this.ensureValueExist();

                return this.condition.value.toTime ?? null;
            },
            set(toTime) {
                this.ensureValueExist();

                this.condition.value = {
                    ...this.condition.value,
                    toTime,
                };
            },
        },

        timezone: {
            get() {
                this.ensureValueExist();

                return this.condition.value.timezone;
            },
            set(timezone) {
                this.ensureValueExist();

                this.condition.value = {
                    ...this.condition.value,
                    timezone,
                };
            },
        },

        timezoneOptions() {
            return Shopware.Service('timezoneService').getTimezoneOptions();
        },
    },
};
