/**
 * @sw-package framework
 */

import { zonedTimeToUtc } from 'date-fns-tz';
import template from './sw-date-filter.html.twig';
import './sw-date-filter.scss';

const { Criteria } = Shopware.Data;

/**
 * @private
 */
export default {
    template,

    inject: ['feature'],

    emits: [
        'filter-reset',
        'filter-update',
    ],

    props: {
        filter: {
            type: Object,
            required: true,
        },

        active: {
            type: Boolean,
            required: true,
        },
    },

    data() {
        return {
            dateValue: {
                from: null,
                to: null,
                timeframe: null,
            },
            timeframeOptions: [
                {
                    label: this.$t('sw-order.filters.orderDateFilter.options.lastYear'),
                    value: -365,
                },
                {
                    label: this.$t('sw-order.filters.orderDateFilter.options.lastQuarter'),
                    value: 'lastQuarter',
                },
                {
                    label: this.$t('sw-order.filters.orderDateFilter.options.lastMonth'),
                    value: -30,
                },
                {
                    label: this.$t('sw-order.filters.orderDateFilter.options.lastWeek'),
                    value: -7,
                },
                {
                    label: this.$t('sw-order.filters.orderDateFilter.options.lastDay'),
                    value: -1,
                },
                {
                    label: this.$t('sw-order.filters.orderDateFilter.options.custom'),
                    value: 'custom',
                    hidden: true,
                },
            ],
        };
    },

    computed: {
        dateType() {
            if (
                [
                    'time',
                    'date',
                    'datetime',
                    'datetime-local',
                ].includes(this.filter.dateType)
            ) {
                return this.filter.dateType;
            }

            return 'date';
        },

        isDateTimeType() {
            return this.dateType === 'datetime' || this.dateType === 'datetime-local';
        },

        showDivider() {
            return !this.isDateTimeType && !this.filter.showTimeframe;
        },

        userTimeZone() {
            return Shopware.Store.get('session').currentUser?.timeZone ?? 'UTC';
        },
    },

    watch: {
        'filter.value': {
            handler() {
                if (this.filter.value) {
                    this.dateValue = { ...this.filter.value };
                }
            },
        },
    },

    methods: {
        fromToFieldLabel(type) {
            const key = `${type}FieldLabel`;

            if (!this.filter.hasOwnProperty(key)) {
                return this.$t(`global.default.${type}`);
            }

            const label = this.filter[key];

            if (!label) {
                return null;
            }

            return label;
        },

        updateFilter() {
            if (!this.dateValue.from && !this.dateValue.to) {
                this.$emit('filter-reset', this.filter.name);
                return;
            }

            const normalizedDateValue = this.getNormalizedDateValue(this.dateValue);

            const { value } = this.filter;
            if (value && value.from === normalizedDateValue.from && value.to === normalizedDateValue.to) {
                return;
            }

            const params = {
                ...(normalizedDateValue.from ? { gte: normalizedDateValue.from } : {}),
                ...(normalizedDateValue.to ? { lte: normalizedDateValue.to } : {}),
            };

            this.$emit(
                'filter-update',
                this.filter.name,
                [Criteria.range(this.filter.property, params)],
                normalizedDateValue,
            );
        },

        onTimeframeSelect(timeframe) {
            if (!timeframe) {
                return;
            }

            if (!this.timeframeOptions.some((t) => t.value === timeframe)) {
                console.error(`Timeframe ${timeframe} is not allowed for sw-date-filter component`);
                return;
            }

            this.resetFilter();

            let from = new Date();
            let to = new Date();

            from.setDate(from.getDate() + timeframe);

            if (timeframe === 'lastQuarter') {
                ({ startDate: from, endDate: to } = this.getPreviousQuarterDates());
            }

            const normalizedDateValue = this.getNormalizedDateValue({
                from: from.toISOString(),
                to: to.toISOString(),
                timeframe: timeframe,
            });

            const params = {
                gte: normalizedDateValue.from,
                lte: normalizedDateValue.to,
            };

            const filterCriteria = [
                Criteria.range(this.filter.property, params),
            ];

            this.dateValue = normalizedDateValue;

            this.$emit('filter-update', this.filter.name, filterCriteria, this.dateValue);
        },

        resetFilter() {
            this.dateValue = { from: null, to: null, timeframe: null };
            this.$emit('filter-reset', this.filter.name, this.dateValue);
        },

        resetTimeframe() {
            this.dateValue.timeframe = 'custom';
        },

        getPreviousQuarterDates() {
            const date = new Date();
            const quarter = Math.floor(date.getMonth() / 3);

            const startDate = new Date(date.getFullYear(), quarter * 3 - 3, 1);
            const endDate = new Date(startDate.getFullYear(), startDate.getMonth() + 3, 0);

            return {
                startDate: startDate,
                endDate: endDate,
            };
        },

        getNormalizedDateValue(dateValue) {
            return {
                from: dateValue.from ? this.getUserTimeZoneDateBoundary(dateValue.from, '00:00:00.000') : null,
                to: dateValue.to ? this.getUserTimeZoneDateBoundary(dateValue.to, '23:59:59.000') : null,
                timeframe: dateValue.timeframe,
            };
        },

        getUserTimeZoneDateBoundary(value, time) {
            const date = new Date(value);

            if (Number.isNaN(date.getTime())) {
                return value;
            }

            const localDate = this.getUserTimeZoneDate(date, value);

            return zonedTimeToUtc(`${localDate}T${time}`, this.userTimeZone).toISOString();
        },

        getUserTimeZoneDate(date, value) {
            if (typeof value === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(value)) {
                return value;
            }

            const formatter = new Intl.DateTimeFormat('en-CA', {
                timeZone: this.userTimeZone,
                year: 'numeric',
                month: '2-digit',
                day: '2-digit',
            });

            const parts = formatter.formatToParts(date);
            const year = parts.find((part) => part.type === 'year').value;
            const month = parts.find((part) => part.type === 'month').value;
            const day = parts.find((part) => part.type === 'day').value;

            return `${year}-${month}-${day}`;
        },
    },
};
