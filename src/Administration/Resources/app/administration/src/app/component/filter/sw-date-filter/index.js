/**
 * @sw-package framework
 */

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

            const { value } = this.filter;
            if (value && value.from === this.dateValue.from && value.to === this.dateValue.to) {
                return;
            }

            const tz = this.userTimeZone();
            const gte = this.dateValue.from ? this.dayBoundsInTz(this.dateValue.from, tz).gte : null;
            const lte = this.dateValue.to ? this.dayBoundsInTz(this.dateValue.to, tz).lte : null;

            const rangeParams = {
                ...(gte ? { gte } : {}),
                ...(lte ? { lte } : {}),
            };

            const emittedValue = {
                ...this.dateValue,
                ...(gte ? { from: gte } : {}),
                ...(lte ? { to: lte } : {}),
            };

            this.$emit('filter-update', this.filter.name, [Criteria.range(this.filter.property, rangeParams)], emittedValue);
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

            const tz = this.userTimeZone();
            const today = this.tzCalendar(tz, new Date());

            let fromCal;
            let toCal;

            if (timeframe === -1) {
                fromCal = this.calendarOf(today.year, today.month, today.day - 1);
                toCal = fromCal;
            } else if (timeframe === -7) {
                const daysSinceMonday = (today.weekday + 6) % 7;
                fromCal = this.calendarOf(today.year, today.month, today.day - daysSinceMonday - 7);
                toCal = this.calendarOf(fromCal.year, fromCal.month, fromCal.day + 6);
            } else if (timeframe === -30) {
                fromCal = this.calendarOf(today.year, today.month - 1, 1);
                toCal = this.calendarOf(today.year, today.month, 0);
            } else if (timeframe === 'lastQuarter') {
                const firstMonthOfLastQuarter = Math.floor(today.month / 3) * 3 - 3;
                fromCal = this.calendarOf(today.year, firstMonthOfLastQuarter, 1);
                toCal = this.calendarOf(fromCal.year, fromCal.month + 3, 0);
            } else {
                fromCal = this.calendarOf(today.year - 1, 0, 1);
                toCal = this.calendarOf(today.year - 1, 11, 31);
            }

            const params = {
                gte: this.startOfDayInTz(fromCal.year, fromCal.month, fromCal.day, tz).toISOString(),
                lte: this.endOfDayInTz(toCal.year, toCal.month, toCal.day, tz).toISOString(),
            };

            this.dateValue = {
                from: params.gte,
                to: params.lte,
                timeframe,
            };

            this.$emit('filter-update', this.filter.name, [Criteria.range(this.filter.property, params)], this.dateValue);
        },

        resetFilter() {
            this.dateValue = { from: null, to: null, timeframe: null };
            this.$emit('filter-reset', this.filter.name, this.dateValue);
        },

        resetTimeframe() {
            this.dateValue.timeframe = 'custom';
        },

        userTimeZone() {
            return Shopware?.Store?.get('session')?.currentUser?.timeZone ?? 'UTC';
        },

        tzCalendar(tz, instant) {
            const parts = new Intl.DateTimeFormat('en-GB', {
                timeZone: tz,
                hour12: false,
                year: 'numeric',
                month: '2-digit',
                day: '2-digit',
            }).formatToParts(instant);
            const read = (type) => parseInt(parts.find((p) => p.type === type)?.value ?? '0', 10);
            const year = read('year');
            const month = read('month') - 1;
            const day = read('day');
            const weekday = new Date(Date.UTC(year, month, day)).getUTCDay();
            return { year, month, day, weekday };
        },

        tzTimeOfDayMs(tz, instant) {
            const parts = new Intl.DateTimeFormat('en-GB', {
                timeZone: tz,
                hour12: false,
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit',
            }).formatToParts(instant);
            const read = (type) => parseInt(parts.find((p) => p.type === type)?.value ?? '0', 10);
            let hour = read('hour');
            if (hour === 24) {
                hour = 0;
            }
            return ((hour * 60 + read('minute')) * 60 + read('second')) * 1000 + instant.getUTCMilliseconds();
        },

        instantFromWallClockInTz(year, month, day, hour, minute, second, ms, tz) {
            const guess = Date.UTC(year, month, day, hour, minute, second, ms);
            const probe = new Date(guess);
            const cal = this.tzCalendar(tz, probe);
            const renderedAsUtc = Date.UTC(cal.year, cal.month, cal.day) + this.tzTimeOfDayMs(tz, probe);
            return new Date(guess - (renderedAsUtc - guess));
        },

        startOfDayInTz(year, month, day, tz) {
            return this.instantFromWallClockInTz(year, month, day, 0, 0, 0, 0, tz);
        },

        endOfDayInTz(year, month, day, tz) {
            return this.instantFromWallClockInTz(year, month, day, 23, 59, 59, 999, tz);
        },

        calendarOf(year, month, day) {
            const d = new Date(Date.UTC(year, month, day));
            return { year: d.getUTCFullYear(), month: d.getUTCMonth(), day: d.getUTCDate() };
        },

        /**
         * mt-datepicker in date-only mode may emit an ISO instant whose wall-clock
         * time is not midnight. Snap to the start of the picked day in the given
         * timezone and derive the end of that same day so filter bounds cover the
         * full intended calendar day. Wall-clock-in-tz arithmetic produces correct
         * 23h or 25h ranges across DST transitions.
         */
        dayBoundsInTz(iso, tz) {
            const { year, month, day } = this.tzCalendar(tz, new Date(iso));
            return {
                gte: this.startOfDayInTz(year, month, day, tz).toISOString(),
                lte: this.endOfDayInTz(year, month, day, tz).toISOString(),
            };
        },
    },
};
