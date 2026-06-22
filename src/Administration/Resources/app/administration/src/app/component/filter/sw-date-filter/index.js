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
                from: this.formatDateParts(from),
                to: this.formatDateParts(to),
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
                const endDate = this.getTodayInUserTimezone();
                const startDate = this.createDateParts(endDate.year, endDate.month, endDate.date + timeframe);

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
                    return this.getTodayDates();
            }
        },

        getTodayDates() {
            const day = this.getTodayInUserTimezone();

            return { startDate: day, endDate: day };
        },

        getYesterdayDates() {
            const today = this.getTodayInUserTimezone();
            const yesterday = this.createDateParts(today.year, today.month, today.date - 1);

            return { startDate: yesterday, endDate: yesterday };
        },

        getCurrentCalendarWeekDates() {
            const today = this.getTodayInUserTimezone();
            // ISO week: Monday = 0 ... Sunday = 6
            const isoDayIndex = (today.day + 6) % 7;
            const startDate = this.createDateParts(today.year, today.month, today.date - isoDayIndex);

            return { startDate: startDate, endDate: today };
        },

        getCurrentCalendarMonthDates() {
            const today = this.getTodayInUserTimezone();
            const startDate = this.createDateParts(today.year, today.month, 1);

            return { startDate: startDate, endDate: today };
        },

        getCurrentQuarterDates() {
            const today = this.getTodayInUserTimezone();
            const quarter = Math.floor(today.month / 3);
            const startDate = this.createDateParts(today.year, quarter * 3, 1);

            return { startDate: startDate, endDate: today };
        },

        getLastNMonthsDates(months) {
            const today = this.getTodayInUserTimezone();
            const targetMonth = today.month - months;
            let startDate = this.createDateParts(today.year, targetMonth, today.date);
            const expectedMonth = this.createDateParts(today.year, targetMonth, 1).month;

            // Clamp to the last day of the target month if JS overflowed
            // (e.g., asking for May 31 - 3 months yields Feb 31 -> Mar 3).
            if (startDate.month !== expectedMonth) {
                startDate = this.createDateParts(today.year, targetMonth + 1, 0);
            }

            return { startDate: startDate, endDate: today };
        },

        getCurrentYearDates() {
            const today = this.getTodayInUserTimezone();
            const startDate = this.createDateParts(today.year, 0, 1);

            return { startDate: startDate, endDate: today };
        },

        getPreviousYearDates() {
            const today = this.getTodayInUserTimezone();
            const startDate = this.createDateParts(today.year - 1, 0, 1);
            const endDate = this.createDateParts(today.year - 1, 11, 31);

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
            const today = this.getTodayInUserTimezone();
            const startDate = this.createDateParts(today.year, today.month - 1, 1);
            const endDate = this.createDateParts(today.year, today.month, 0);

            return {
                startDate: startDate,
                endDate: endDate,
            };
        },

        getPreviousCalendarWeekDates() {
            const today = this.getTodayInUserTimezone();
            // ISO week: Monday = 0 ... Sunday = 6
            const isoDayIndex = (today.day + 6) % 7;
            const startDate = this.createDateParts(today.year, today.month, today.date - isoDayIndex - 7);
            const endDate = this.createDateParts(today.year, today.month, today.date - isoDayIndex - 1);

            return {
                startDate: startDate,
                endDate: endDate,
            };
        },

        getPreviousQuarterDates() {
            const today = this.getTodayInUserTimezone();
            const quarter = Math.floor(today.month / 3);

            const startDate = this.createDateParts(today.year, quarter * 3 - 3, 1);
            const endDate = this.createDateParts(startDate.year, startDate.month + 3, 0);

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

        getTodayInUserTimezone() {
            const formatter = new Intl.DateTimeFormat('en-CA', {
                timeZone: this.userTimeZone,
                year: 'numeric',
                month: '2-digit',
                day: '2-digit',
            });

            const parts = formatter.formatToParts(new Date());
            const getPart = (type) => Number(parts.find((part) => part.type === type).value);

            return this.createDateParts(getPart('year'), getPart('month') - 1, getPart('day'));
        },

        /**
         * Wrapper for not using `new Date(year, month, date)` to opt out of
         * using the browsers timezone.
         *
         * Still keeps js date wrapping behavior for month/day overflow calculations.
         */
        createDateParts(year, month, date) {
            const utcDate = new Date(0);
            utcDate.setUTCFullYear(year, month, date);
            utcDate.setUTCHours(0, 0, 0, 0);

            return {
                year: utcDate.getUTCFullYear(),
                month: utcDate.getUTCMonth(),
                date: utcDate.getUTCDate(),
                day: utcDate.getUTCDay(),
            };
        },

        formatDateParts({ year, month, date }) {
            return [
                String(year).padStart(4, '0'),
                String(month + 1).padStart(2, '0'),
                String(date).padStart(2, '0'),
            ].join('-');
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
