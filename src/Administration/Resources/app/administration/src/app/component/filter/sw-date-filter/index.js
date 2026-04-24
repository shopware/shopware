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

            this.$emit(
                'filter-update',
                this.filter.name,
                [Criteria.range(this.filter.property, rangeParams)],
                emittedValue,
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
            from.setHours(0, 0, 0);

            if (timeframe === 'lastQuarter') {
                ({ startDate: from, endDate: to } = this.getPreviousQuarterDates());
            }

            const params = {
                gte: from.toISOString(),
                lte: to.toISOString(),
            };

            const filterCriteria = [
                Criteria.range(this.filter.property, params),
            ];

            this.dateValue = {
                from: params.gte,
                to: params.lte,
                timeframe: timeframe,
            };

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

            const startDate = new Date(date.getFullYear(), quarter * 3 - 3, 1, 0, 0, 0);
            const endDate = new Date(startDate.getFullYear(), startDate.getMonth() + 3, 0, 23, 59, 59);

            return {
                startDate: startDate,
                endDate: endDate,
            };
        },

        userTimeZone() {
            return Shopware?.Store?.get('session')?.currentUser?.timeZone ?? 'UTC';
        },

        /**
         * mt-datepicker in date-only mode may emit an ISO instant whose wall-clock
         * time is not midnight (e.g. it carries over the current time on the
         * picked day). Snap to the start of the picked day in the given timezone
         * and derive the end of that same day so filter bounds cover the full
         * intended calendar day as the list's date formatter displays it.
         */
        dayBoundsInTz(iso, tz) {
            const instant = new Date(iso);
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
            const minute = read('minute');
            const second = read('second');
            const ms = instant.getUTCMilliseconds();

            const offsetIntoDayMs = ((hour * 60 + minute) * 60 + second) * 1000 + ms;
            const startMs = instant.getTime() - offsetIntoDayMs;
            const oneDayMs = 24 * 60 * 60 * 1000;

            return {
                gte: new Date(startMs).toISOString(),
                lte: new Date(startMs + oneDayMs - 1).toISOString(),
            };
        },
    },
};
