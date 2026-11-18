import template from './sw-condition-weekday.html.twig';
import './sw-condition-weekday.scss';

/**
 * @sw-package fundamentals@after-sales
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    computed: {
        operators() {
            return this.conditionDataProviderService.getOperatorSet('string');
        },

        dayOfWeekOptions() {
            return [
                1,
                2,
                3,
                4,
                5,
                6,
                7,
            ].map((day) => ({
                value: day,
                label: this.$t(`global.sw-condition-generic.dayOfWeek.dayOfWeek.options.${day}`),
            }));
        },

        dayOfWeek: {
            get() {
                this.ensureValueExist();

                return this.condition.value.dayOfWeek ?? null;
            },
            set(dayOfWeek) {
                this.ensureValueExist();

                this.condition.value = {
                    ...this.condition.value,
                    dayOfWeek,
                };
            },
        },

        timezone: {
            get() {
                this.ensureValueExist();

                return this.condition.value.timezone ?? null;
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
