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
                    label: this.$t('sw-order.filters.orderDateFilter.options.today'),
                    value: 'today',
                },
                {
                    label: this.$t('sw-order.filters.orderDateFilter.options.yesterday'),
                    value: 'yesterday',
                },
                {
                    label: this.$t('sw-order.filters.orderDateFilter.options.currentWeek'),
                    value: 'currentWeek',
                },
                {
                    label: this.$t('sw-order.filters.orderDateFilter.options.lastWeek'),
                    value: -7,
                },
                {
                    label: this.$t('sw-order.filters.orderDateFilter.options.lastCalendarWeek'),
                    value: 'lastCalendarWeek',
                },
                {
                    label: this.$t('sw-order.filters.orderDateFilter.options.currentMonth'),
                    value: 'currentMonth',
                },
                {
                    label: this.$t('sw-order.filters.orderDateFilter.options.lastMonth'),
                    value: -30,
                },
                {
                    label: this.$t('sw-order.filters.orderDateFilter.options.lastCalendarMonth'),
                    value: 'lastCalendarMonth',
                },
                {
                    label: this.$t('sw-order.filters.orderDateFilter.options.currentQuarter'),
                    value: 'currentQuarter',
                },
                {
                    label: this.$t('sw-order.filters.orderDateFilter.options.lastQuarter'),
                    value: 'lastQuarter',
                },
                {
                    label: this.$t('sw-order.filters.orderDateFilter.options.last3Months'),
                    value: 'last3Months',
                },
                {
                    label: this.$t('sw-order.filters.orderDateFilter.options.last6Months'),
                    value: 'last6Months',
                },
                {
                    label: this.$t('sw-order.filters.orderDateFilter.options.last12Months'),
                    value: 'last12Months',
                },
                {
                    label: this.$t('sw-order.filters.orderDateFilter.options.currentYear'),
                    value: 'currentYear',
                },
                {
                    label: this.$t('sw-order.filters.orderDateFilter.options.previousYear'),
                    value: 'previousYear',
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
                    this.dateValue = {
                        ...this.filter.value,
                        timeframe: this.aliasLegacyTimeframe(this.filter.value.timeframe),
                    };
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

            const resolved = this.aliasLegacyTimeframe(timeframe);

            if (!this.timeframeOptions.some((t) => t.value === resolved)) {
                console.error(`Timeframe ${timeframe} is not allowed for sw-date-filter component`);
                return;
            }

            this.resetFilter();

            const { startDate: from, endDate: to } = this.getTimeframeDates(resolved);

            const normalizedDateValue = this.getNormalizedDateValue({
                from: from.toISOString(),
                to: to.toISOString(),
                timeframe: resolved,
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

        aliasLegacyTimeframe(timeframe) {
            // Legacy values that no longer appear in timeframeOptions are mapped
            // to their closest current equivalents so saved filter states keep
            // resolving to a labelled dropdown entry.
            const aliases = {
                '-1': 'yesterday',
                '-365': 'last12Months',
            };

            return aliases[String(timeframe)] ?? timeframe;
        },

        getTimeframeDates(timeframe) {
            if (typeof timeframe === 'number') {
                const startDate = new Date();
                const endDate = new Date();
                startDate.setDate(startDate.getDate() + timeframe);

                return { startDate, endDate };
            }

            switch (timeframe) {
                case 'today':
                    return this.getTodayDates();
                case 'yesterday':
                    return this.getYesterdayDates();
                case 'currentWeek':
                    return this.getCurrentCalendarWeekDates();
                case 'lastCalendarWeek':
                    return this.getPreviousCalendarWeekDates();
                case 'currentMonth':
                    return this.getCurrentCalendarMonthDates();
                case 'lastCalendarMonth':
                    return this.getPreviousCalendarMonthDates();
                case 'currentQuarter':
                    return this.getCurrentQuarterDates();
                case 'lastQuarter':
                    return this.getPreviousQuarterDates();
                case 'last3Months':
                    return this.getLastNMonthsDates(3);
                case 'last6Months':
                    return this.getLastNMonthsDates(6);
                case 'last12Months':
                    return this.getLastNMonthsDates(12);
                case 'currentYear':
                    return this.getCurrentYearDates();
                case 'previousYear':
                    return this.getPreviousYearDates();
                default:
                    return { startDate: new Date(), endDate: new Date() };
            }
        },

        getTodayDates() {
            const today = new Date();
            const day = new Date(today.getFullYear(), today.getMonth(), today.getDate());

            return { startDate: day, endDate: day };
        },

        getYesterdayDates() {
            const today = new Date();
            const yesterday = new Date(today.getFullYear(), today.getMonth(), today.getDate() - 1);

            return { startDate: yesterday, endDate: yesterday };
        },

        getCurrentCalendarWeekDates() {
            const today = new Date();
            // ISO week: Monday = 0 ... Sunday = 6
            const isoDayIndex = (today.getDay() + 6) % 7;
            const startDate = new Date(today.getFullYear(), today.getMonth(), today.getDate() - isoDayIndex);

            return { startDate: startDate, endDate: today };
        },

        getCurrentCalendarMonthDates() {
            const today = new Date();
            const startDate = new Date(today.getFullYear(), today.getMonth(), 1);

            return { startDate: startDate, endDate: today };
        },

        getCurrentQuarterDates() {
            const today = new Date();
            const quarter = Math.floor(today.getMonth() / 3);
            const startDate = new Date(today.getFullYear(), quarter * 3, 1);

            return { startDate: startDate, endDate: today };
        },

        getLastNMonthsDates(months) {
            const today = new Date();
            const targetMonth = today.getMonth() - months;
            const startDate = new Date(today.getFullYear(), targetMonth, today.getDate());
            const expectedMonth = ((targetMonth % 12) + 12) % 12;

            // Clamp to the last day of the target month if JS overflowed
            // (e.g., asking for May 31 - 3 months yields Feb 31 -> Mar 3).
            if (startDate.getMonth() !== expectedMonth) {
                startDate.setDate(0);
            }

            return { startDate: startDate, endDate: today };
        },

        getCurrentYearDates() {
            const today = new Date();
            const startDate = new Date(today.getFullYear(), 0, 1);

            return { startDate: startDate, endDate: today };
        },

        getPreviousYearDates() {
            const today = new Date();
            const startDate = new Date(today.getFullYear() - 1, 0, 1);
            const endDate = new Date(today.getFullYear() - 1, 11, 31);

            return { startDate: startDate, endDate: endDate };
        },

        resetFilter() {
            this.dateValue = { from: null, to: null, timeframe: null };
            this.$emit('filter-reset', this.filter.name, this.dateValue);
        },

        resetTimeframe() {
            this.dateValue.timeframe = 'custom';
        },

        getPreviousCalendarMonthDates() {
            const date = new Date();
            const startDate = new Date(date.getFullYear(), date.getMonth() - 1, 1);
            const endDate = new Date(date.getFullYear(), date.getMonth(), 0);

            return {
                startDate: startDate,
                endDate: endDate,
            };
        },

        getPreviousCalendarWeekDates() {
            const date = new Date();
            // ISO week: Monday = 0 ... Sunday = 6
            const isoDayIndex = (date.getDay() + 6) % 7;
            const startDate = new Date(date.getFullYear(), date.getMonth(), date.getDate() - isoDayIndex - 7);
            const endDate = new Date(date.getFullYear(), date.getMonth(), date.getDate() - isoDayIndex - 1);

            return {
                startDate: startDate,
                endDate: endDate,
            };
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
