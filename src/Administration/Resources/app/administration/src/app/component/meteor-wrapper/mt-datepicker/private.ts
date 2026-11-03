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
    name: 'mt-datepicker',
    load: () => import('./src/mt-datepicker'),
    public: {
        props: [
            'locale',
            'format',
            'timeZone',
            'dateType',
            'is24',
        ],
        slots: [
            'name',
        ],
        computed: [
            'userLocale',
            'userTimeZone',
            'is24HourFormat',
            'formatterOptions',
            'datePickerFormat',
        ],
        methods: [
            'escapeDateFnsFormatLiteral',
            'customFormat',
            'formatDate',
            'getLocaleDatePattern',
            'getLocaleTimePeriods',
            'parseDateString',
            'constructDateWithTimezone',
            'handleStringFormat',
        ],
    },
});
