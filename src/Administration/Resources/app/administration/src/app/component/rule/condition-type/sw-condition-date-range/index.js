import template from './sw-condition-date-range.html.twig';
import './sw-condition-date-range.scss';

/**
 * @public
 * @sw-package fundamentals@after-sales
 * @description Condition for the DateRangeRule. This component must a be child of sw-condition-tree.
 * @status prototype
 * @example-type code-only
 * @component-example
 * <sw-condition-date-range :condition="condition" :level="0"></sw-condition-date-range>
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    computed: {
        selectValues() {
            return [
                {
                    label: this.$t('global.sw-condition.condition.withTime'),
                    value: true,
                },
                {
                    label: this.$t('global.sw-condition.condition.withoutTime'),
                    value: false,
                },
            ];
        },

        useTime: {
            get() {
                this.ensureValueExist();
                if (typeof this.condition.value.useTime === 'undefined') {
                    this.condition.value = {
                        ...this.condition.value,
                        useTime: false,
                    };
                }

                return this.condition.value.useTime;
            },
            set(useTime) {
                this.ensureValueExist();
                this.condition.value = { ...this.condition.value, useTime };
                this.fromDate = this.condition.value.fromDate;
                this.toDate = this.condition.value.toDate;
            },
        },

        fromDate: {
            get() {
                this.ensureValueExist();
                return this.condition.value.fromDate ? `${this.condition.value.fromDate}.000Z` : null;
            },
            set(fromDate) {
                this.ensureValueExist();

                this.condition.value = {
                    ...this.condition.value,
                    fromDate: this.formatDate(fromDate, '00:00:00'),
                };
            },
        },

        toDate: {
            get() {
                this.ensureValueExist();
                return this.condition.value.toDate ? `${this.condition.value.toDate}.000Z` : null;
            },
            set(toDate) {
                this.ensureValueExist();

                this.condition.value = {
                    ...this.condition.value,
                    toDate: this.formatDate(toDate, '23:59:59'),
                };
            },
        },

        timezone: {
            get() {
                this.ensureValueExist();
                return this.condition.value.timezone || null;
            },
            set(timezone) {
                this.ensureValueExist();

                this.condition.value = {
                    ...this.condition.value,
                    timezone,
                };
            },
        },

        isDateTime() {
            return this.useTime ? 'datetime' : 'date';
        },

        timezoneOptions() {
            return Shopware.Service('timezoneService').getTimezoneOptions();
        },
    },

    methods: {
        formatDate(date, timeModifier) {
            if (!date) {
                return null;
            }

            if (this.isDateTime === 'datetime') {
                return date.replace('.000Z', '');
            }

            return `${date.split('T')[0]}T${timeModifier}`;
        },
    },
};
