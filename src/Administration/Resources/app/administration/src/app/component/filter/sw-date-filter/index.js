import template from './sw-date-filter.html.twig';
import './sw-date-filter.scss';

const { Component } = Shopware;
const { Criteria } = Shopware.Data;

/**
 * @private
 */
Component.register('sw-date-filter', {
    template,

    inject: ['feature'],

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
                    label: this.$tc('sw-order.filters.orderDateFilter.options.lastYear'),
                    value: -365,
                },
                {
                    label: this.$tc('sw-order.filters.orderDateFilter.options.lastQuarter'),
                    value: 'lastQuarter',
                },
                {
                    label: this.$tc('sw-order.filters.orderDateFilter.options.lastMonth'),
                    value: -30,
                },
                {
                    label: this.$tc('sw-order.filters.orderDateFilter.options.lastWeek'),
                    value: -7,
                },
                {
                    label: this.$tc('sw-order.filters.orderDateFilter.options.lastDay'),
                    value: -1,
                },
                {
                    label: this.$tc('sw-order.filters.orderDateFilter.options.custom'),
                    value: 'custom',
                    hidden: true,
                },
            ],
        };
    },

    computed: {
        dateType() {
            if (['time', 'date', 'datetime', 'datetime-local'].includes(this.filter.dateType)) {
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
                return this.$tc(`global.default.${type}`);
            }

            const label = this.filter[key];

            if (!label) {
                return null;
            }

            return label;
        },

        updateFilter(params) {
            if (!this.dateValue.from && !this.dateValue.to) {
                this.$emit('filter-reset', this.filter.name);
                return;
            }

            const { value } = this.filter;
            if (value && value.from === this.dateValue.from && value.to === this.dateValue.to) {
                return;
            }

            this.$emit('filter-update', this.filter.name, params, this.dateValue);
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

            const filterCriteria = [Criteria.range(this.filter.property, params)];

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
            const quarter = Math.floor((date.getMonth() / 3));

            const startDate = new Date(date.getFullYear(), quarter * 3 - 3, 1);
            const endDate = new Date(
                date.getFullYear(),
                startDate.getMonth() + 3,
                0,
                23,
                59,
                59,
            );

            return {
                startDate: startDate,
                endDate: endDate,
            };
        },
    },
});
