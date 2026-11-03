import { definePublicComponent } from 'src/core/public-component';

/**
 * @sw-package framework
 *
 * This file is the public API, also for extensions. The files in `./src` are private.
 * The lists hold everything the component offered before it became a mini-module,
 * because Component.override could reach all of it. Shrink them only through the deprecation process.
 */
/** @public */
export default definePublicComponent({
    name: 'sw-datepicker-deprecated',
    load: () => import('./src/sw-datepicker-deprecated'),
    public: {
        props: [
            'value',
            'config',
            'dateType',
            'placeholder',
            'required',
            'disabled',
            'hideHint',
        ],
        emits: [
            'update:value',
            'inheritance-restore',
            'inheritance-remove',
        ],
        slots: [
            'label',
        ],
        blocks: [
            'sw_datepicker',
        ],
        computed: [
            'locale',
            'currentFlatpickrConfig',
            'placeholderText',
            'suffixName',
            'noCalendar',
            'enableTime',
            'additionalAttrs',
            'userTimeZone',
            'timezoneFormattedValue',
            'showTimeZoneHint',
            'timeZoneHint',
            'is24HourFormat',
        ],
        methods: [
            'createdComponent',
            'mountedComponent',
            'beforeDestroyComponent',
            'setDatepickerValue',
            'getMergedConfig',
            'updateFlatpickrInstance',
            'createFlatpickrInstance',
            'getEventNames',
            'openDatepicker',
            'kebabToCamel',
            'unsetValue',
            'emitValue',
            'createConfig',
            'getDateStringFormat',
        ],
    },
});
