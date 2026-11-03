import { definePrivateComponent } from 'src/core/public-component';

/**
 * @sw-package framework
 *
 * The component is registered by its name for the Administration only: it never reaches extensions.
 * The lists hold everything it offered before it became a mini-module,
 * because Component.override inside the Administration could reach all of it. The files in `./src` are private to this module.
 */
/** @private */
export default definePrivateComponent({
    name: 'sw-date-filter',
    load: () => import('./src/sw-date-filter'),
    public: {
        props: [
            'filter',
            'active',
        ],
        emits: [
            'filter-reset',
            'filter-update',
        ],
        blocks: [
            'sw_date_filter',
            'sw_date_filter_timeframe',
            'sw_date_filter_from_field',
            'sw_date_filter_to_field',
        ],
        computed: [
            'dateType',
            'isDateTimeType',
            'showDivider',
            'userTimeZone',
        ],
        methods: [
            'fromToFieldLabel',
            'updateFilter',
            'onTimeframeSelect',
            'aliasLegacyTimeframe',
            'getTimeframeDates',
            'getTodayDates',
            'getYesterdayDates',
            'getCurrentCalendarWeekDates',
            'getCurrentCalendarMonthDates',
            'getCurrentQuarterDates',
            'getLastNMonthsDates',
            'getCurrentYearDates',
            'getPreviousYearDates',
            'resetFilter',
            'resetTimeframe',
            'getPreviousCalendarMonthDates',
            'getPreviousCalendarWeekDates',
            'getPreviousQuarterDates',
            'getNormalizedDateValue',
            'getTodayInUserTimezone',
            'createDateParts',
            'formatDateParts',
            'getUserTimeZoneDateBoundary',
            'getUserTimeZoneDate',
        ],
    },
});
