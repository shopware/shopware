import Flatpickr from 'flatpickr';
import 'flatpickr/dist/l10n';
import { zonedTimeToUtc, utcToZonedTime } from 'date-fns-tz';
import template from './sw-datepicker.html.twig';
import 'flatpickr/dist/flatpickr.css';
import './sw-datepicker.scss';

const { Mixin } = Shopware;

/**
 * @package admin
 *
 * @deprecated tag:v6.6.0 - Will be private
 * @public
 * @description Datepicker wrapper for date inputs. For all configuration options visit:
 * <a href="https://flatpickr.js.org/options/">https://flatpickr.js.org/options/</a>.
 * Be careful when changing the config object. To add a parameter to the config at runtime use:
 * <a href="https://vuejs.org/v2/api/#Vue-set">https://vuejs.org/v2/api/#Vue-set</a>.
 *
 * @status ready
 * @example-type static
 * @component-example
 * <sw-datepicker
 *      dateType="date"
 *      label="SW-Field Date"
 *      size="default"
 *      placeholder="Enter date..."
 *      value="12.10.2019">
 * </sw-datepicker>
 */
const allEvents = [
    'onChange',
    'onClose',
    'onDestroy',
    'onMonthChange',
    'onOpen',
    'onYearChange',
    'onValueUpdate',
    'onDayCreate',
    'onParseConfig',
    'onReady',
    'onPreCalendarPosition',
    'onKeyDown',
];

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,
    inheritAttrs: false,

    mixins: [
        Mixin.getByName('sw-form-field'),
        Mixin.getByName('remove-api-error'),
    ],

    props: {
        value: {
            type: String,
            required: false,
            default: null,
        },

        config: {
            type: Object,
            default() {
                return {};
            },
        },

        dateType: {
            type: String,
            default: 'date',
            validValues: ['time', 'date', 'datetime'],
            validator(value) {
                return ['time', 'date', 'datetime'].includes(value);
            },
        },

        placeholderText: {
            type: String,
            default: '',
            required: false,
        },

        required: {
            type: Boolean,
            default: false,
            required: false,
        },

        disabled: {
            type: Boolean,
            default: false,
            required: false,
        },

        hideHint: {
            type: Boolean,
            default: false,
            required: false,
        },
    },

    data() {
        return {
            flatpickrInstance: null,
            isDatepickerOpen: false,
            defaultConfig: {},
        };
    },

    computed: {
        flatpickrInputRef() {
            return this.$refs.flatpickrInput;
        },

        locale() {
            return Shopware.State.getters.adminLocaleLanguage || 'en';
        },

        currentFlatpickrConfig() {
            if (this.flatpickrInstance === null) {
                return {};
            }

            return this.flatpickrInstance.config;
        },

        placeholder() {
            if (this.placeholderText.length > 0) {
                return this.placeholderText;
            }

            if (this.flatpickrInstance === null) {
                return this.defaultConfig.altFormat;
            }

            return this.flatpickrInstance.config.altFormat;
        },

        suffixName() {
            if (this.noCalendar) {
                return 'regular-clock';
            }

            return 'regular-calendar';
        },

        noCalendar() {
            return this.dateType === 'time';
        },

        enableTime() {
            return this.noCalendar || this.dateType === 'datetime';
        },

        additionalEventListeners() {
            const listeners = {};

            /**
             * Do not pass "change" or "input" event listeners to the form elements
             * because the component implements its own listeners for this event types.
             * The callback methods will emit the corresponding event to the parent.
             */
            Object.keys(this.$listeners).forEach((key) => {
                if (!['change', 'input'].includes(key)) {
                    listeners[key] = this.$listeners[key];
                }
            });

            return listeners;
        },

        userTimeZone() {
            return Shopware?.State?.get('session')?.currentUser?.timeZone ?? 'UTC';
        },

        timezoneFormattedValue: {
            get() {
                if (!this.value) {
                    return null;
                }

                if (['time', 'date'].includes(this.dateType)) {
                    return this.value;
                }

                // convert from UTC timezone to user timezone (represented as UTC)
                const userTimezoneDate = utcToZonedTime(this.value, this.userTimeZone);

                // get the time converted to the user timezone
                return userTimezoneDate.toISOString();
            },
            set(newValue) {
                if (newValue === null) {
                    this.$emit('input', null);
                    return;
                }

                if (['time', 'date'].includes(this.dateType)) {
                    this.$emit('input', newValue);
                    return;
                }

                // convert from user timezone (represented as UTC) to UTC timezone
                const utcDate = zonedTimeToUtc(new Date(newValue), this.userTimeZone);

                // emit the UTC time so that the v-model value always work in UTC time (which is needed for the server)
                this.$emit('input', utcDate.toISOString());
            },
        },

        showTimeZoneHint() {
            const validMode = [
                'datetime',
                'datetime-local',
            ].includes(this.dateType);

            return validMode && !this.hideHint;
        },
    },

    watch: {
        config: {
            deep: true,
            handler() {
                this.updateFlatpickrInstance();
            },
        },

        dateType() {
            this.createConfig();
            this.updateFlatpickrInstance();
        },

        locale: {
            immediate: true,
            handler() {
                this.defaultConfig.locale = this.locale;
                this.updateFlatpickrInstance(this.config);
            },
        },

        /**
         * Watch for changes from parent component and update DOM
         *
         * @param newValue
         */
        timezoneFormattedValue(newValue) {
            this.setDatepickerValue(newValue);
        },

        disabled(isDisabled) {
            this.flatpickrInstance._input.disabled = isDisabled;
        },
    },

    created() {
        this.createdComponent();
    },

    mounted() {
        this.mountedComponent();
    },

    /**
     * Free up memory
     */
    beforeDestroy() {
        this.beforeDestroyComponent();
    },

    methods: {
        createdComponent() {
            this.createConfig();
        },

        mountedComponent() {
            if (this.flatpickrInstance === null) {
                return;
            }

            this.updateFlatpickrInstance();
        },

        /**
         * Free up memory
         */
        beforeDestroyComponent() {
            if (this.flatpickrInstance !== null) {
                this.flatpickrInstance.destroy();
                this.flatpickrInstance = null;
            }
        },

        /**
         * Update with the new value.
         *
         * @param value
         */
        setDatepickerValue(value) {
            // Make sure we have a flatpickr instance
            if (this.flatpickrInstance !== null) {
                // Notify flatpickr instance that there is a change in value
                this.flatpickrInstance.setDate(value, false);
            }
        },

        /**
         * Merge the newConfig parameter with the defaultConfig and other options.
         *
         * @param newConfig
         * @returns {any}
         */
        getMergedConfig(newConfig) {
            if (newConfig.mode !== undefined) {
                console.warn(
                    '[sw-datepicker] The only allowed mode is the default \'single\' mode ' +
                    '(the specified mode will be ignored!). ' +
                    'The modes \'multiple\' or \'range\' are currently not supported',
                );
            }

            return {

                ...this.defaultConfig,
                enableTime: this.enableTime,
                noCalendar: this.noCalendar,
                ...newConfig,
                mode: 'single',
            };
        },

        /**
         * Update the flatpickr instance with a new config.
         */
        updateFlatpickrInstance() {
            if (this.flatpickrInstance === null) {
                return;
            }

            const mergedConfig = this.getMergedConfig(this.config);

            if (mergedConfig.enableTime !== undefined
                    && mergedConfig.enableTime !== this.currentFlatpickrConfig.enableTime) {
                // The instance must be recreated for some config options to take effect like 'enableTime' changes.
                // See https://github.com/flatpickr/flatpickr/issues/1108 for details.
                this.createFlatpickrInstance(this.config);
                return;
            }
            // Workaround: Don't allow to pass hooks to configs again otherwise
            // previously registered hooks will stop working
            // Notice: we are looping through all events
            // This also means that new callbacks can not passed once component has been initialized
            allEvents.forEach((hook) => {
                delete mergedConfig[hook];
            });

            // Update the flatpickr config.
            this.flatpickrInstance.set(mergedConfig);

            // Workaround: Allow to change locale dynamically
            ['locale', 'showMonths'].forEach((name) => {
                if (typeof mergedConfig[name] !== 'undefined') {
                    this.flatpickrInstance.set(name, mergedConfig[name]);
                }
            });
        },

        /**
         * Create the flatpickr instance. If already one exists it will be recreated.
         */
        createFlatpickrInstance() {
            if (this.flatpickrInstance !== null) {
                this.flatpickrInstance.destroy();
                this.flatpickrInstance = null;
            }

            const mergedConfig = this.getMergedConfig(this.config);

            // Set event hooks in config.
            this.getEventNames().forEach(({ kebabCase, camelCase }) => {
                mergedConfig[camelCase] = (...args) => {
                    this.$emit(kebabCase, ...args);
                };
            });

            // Init flatpickr only if it is not already loaded.
            this.flatpickrInstance = new Flatpickr(this.flatpickrInputRef, mergedConfig);
            this.flatpickrInstance.config.onOpen.push(() => {
                this.isDatepickerOpen = true;
            });

            this.flatpickrInstance.config.onClose.push(() => {
                this.isDatepickerOpen = false;
            });

            this.flatpickrInstance.config.onChange.push((...args) => {
                this.emitValue(args[1]);
            });

            // Set the right datepicker value from the property.
            this.setDatepickerValue(this.timezoneFormattedValue);
        },

        /**
         * Convert the events for the date picker to another format:
         * from: 'on-month-change' to: { camelCase: 'onMonthChange', kebabCase: 'on-month-change' }
         * So this can be used as a parameter to flatpickr to specify which events will be thrown
         * and also emit the right event from vue.
         *
         * @returns {Array}
         */
        getEventNames() {
            const events = [];
            Object.keys(this.additionalEventListeners).forEach((event) => {
                events.push({
                    kebabCase: event,
                    camelCase: this.kebabToCamel(event),
                });
            });

            return events;
        },

        /**
         * Opens the datepicker.
         */
        openDatepicker() {
            this.$nextTick(() => {
                this.flatpickrInstance.open();
            });
        },

        /**
         * Get a camel case ("camelCase") string from a kebab case ("kebab-case") string.
         *
         * @param string
         * @returns {*}
         */
        kebabToCamel(string) {
            return string.replace(/-([a-z])/g, (m, g1) => {
                return g1.toUpperCase();
            });
        },

        unsetValue() {
            this.$nextTick(() => {
                this.emitValue(null);
            });
        },

        emitValue(value) {
            // Prevent emitting an empty date, to reset a date, null should be emitted
            if (value === '') {
                value = null;
            }

            // Prevent emit if value is already up to date
            if (value === this.timezoneFormattedValue) {
                return;
            }

            this.timezoneFormattedValue = value;
        },

        createConfig() {
            let dateFormat = 'Y-m-dTH:i:S';
            let altFormat = 'Y-m-d H:i';

            if (this.dateType === 'time') {
                dateFormat = 'H:i:S';
                altFormat = 'H:i';
            }

            if (this.dateType === 'date') {
                altFormat = 'Y-m-d';
            }

            this.defaultConfig = {
                time_24hr: true,
                locale: this.locale,
                dateFormat,
                altInput: true,
                altFormat,
                allowInput: true,
            };
        },
    },
};
