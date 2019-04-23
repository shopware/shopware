import Flatpickr from 'flatpickr';
import 'flatpickr/dist/l10n';
import template from './sw-datepicker.html.twig';
import 'flatpickr/dist/flatpickr.css';
import './sw-datepicker.scss';

/**
 * @public
 * @description Datepicker wrapper for date inputs. For all configuration options visit:
 * <a href="https://flatpickr.js.org/options/">https://flatpickr.js.org/options/</a>.
 * Be careful when changing the config object. To add a parameter to the config at runtime use:
 * <a href="https://vuejs.org/v2/api/#Vue-set">https://vuejs.org/v2/api/#Vue-set</a>.
 *
 * <u>this.$set(this.myDatepickerConfig, 'dateFormat', 'd.m.y');</u>
 * @status ready
 * @example-type static
 * @component-example
 * <sw-datepicker
 *      dateType="date"
 *      label="SW-Field Date"
 *      size="default"
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
    'onKeyDown'
];
export default {
    name: 'sw-datepicker',
    extendsFrom: 'sw-text-field',
    template,

    props: {
        value: {
            required: true
        },

        config: {
            type: Object,
            default() {
                return {};
            }
        },

        dateType: {
            type: String,
            default: 'date',
            validValues: ['time', 'date', 'datetime', 'datetime-local'],
            validator(value) {
                return ['time', 'date', 'datetime', 'datetime-local'].includes(value);
            }
        }
    },

    data() {
        return {
            flatpickrInstance: null,
            inputValue: '',
            defaultConfig: {
                time_24hr: true,
                locale: 'en',
                dateFormat: 'Y-m-dTH:i:S+00:00',
                altInput: true,
                altFormat: 'Y-m-d H:i'
                // disableMobile: true // only render the flatpickr and no native pickers on mobile
            },
            noCalendar: (this.dateType === 'time'),
            enableTime: (this.dateType === 'datetime' || this.dateType === 'datetime-local' || this.noCalendar)
        };
    },

    computed: {
        flatpickrInputRef() {
            return this.$refs.flatpickrInput;
        },

        locale() {
            return this.$store.getters.adminLocaleLanguage;
        },

        currentFlatpickrConfig() {
            if (this.flatpickrInstance === null) {
                return {};
            }

            return this.flatpickrInstance.config;
        },
        suffixName() {
            if (this.noCalendar) {
                return 'default-time-clock';
            }

            return 'default-calendar-full';
        },

        fieldClasses() {
            return [
                `sw-field--${this.dateType}`,
                `sw-field--${this.size}`,
                {
                    'has--error': !!this.hasErrorCls,
                    'has--suffix': true,
                    'is--disabled': !!this.$props.disabled
                }];
        }
    },

    mounted() {
        if (this.flatpickrInstance === null) {
            this.createFlatpickrInstance(this.config);
        } else {
            this.updateFlatpickrInstance(this.config);
        }
    },

    /**
     * Free up memory
     */
    beforeDestroy() {
        this.flatpickrInputRef.removeEventListener('blur', this.onBlur);

        if (this.flatpickrInstance !== null) {
            this.flatpickrInstance.destroy();
            this.flatpickrInstance = null;
        }
    },

    watch: {
        config: {
            deep: true,
            handler(newConfig) {
                this.updateFlatpickrInstance(newConfig);
            }
        },

        locale: {
            immediate: true,
            handler() {
                this.defaultConfig.locale = this.locale;
                this.updateFlatpickrInstance(this.config);
            }
        },

        /**
         * Watch for changes from parent component and update DOM
         *
         * @param newValue
         */
        value: {
            handler(newValue) {
                this.setDatepickerValue(newValue);
            }
        }
    },

    methods: {
        /**
         * Update DOM if the newValue is different from the DOM value.
         *
         * @param newValue
         */
        setDatepickerValue(newValue) {
            // Prevent updates if v-model value is same as input's current value
            if (newValue === this.flatpickrInstance.input.defaultValue) {
                return;
            }

            // Make sure we have a flatpickr instance
            if (this.flatpickrInstance !== null) {
                // Notify flatpickr instance that there is a change in value
                this.flatpickrInstance.setDate(newValue, true);
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
                    'The modes \'multiple\' or \'range\' are currently not supported'
                );
            }

            return Object.assign(
                this.defaultConfig,
                {
                    enableTime: this.enableTime,
                    noCalendar: this.noCalendar
                },
                newConfig,
                {
                    mode: 'single'
                }
            );
        },

        /**
         * Update the flatpickr instance with a new config.
         *
         * @param newConfig
         */
        updateFlatpickrInstance(newConfig) {
            if (this.flatpickrInstance === null) {
                return;
            }

            const mergedConfig = this.getMergedConfig(newConfig);
            // Don't pass the original reference to the config object. Use a copy instead.
            const safeConfig = Object.assign({}, mergedConfig);

            if (safeConfig.enableTime !== undefined && safeConfig.enableTime !== this.currentFlatpickrConfig.enableTime) {
                // The instance must be recreated for some config options to take effect like 'enableTime' changes.
                // See https://github.com/flatpickr/flatpickr/issues/1108 for details.
                this.createFlatpickrInstance(newConfig);
                return;
            }
            // Workaround: Don't allow to pass hooks to configs again otherwise
            // previously registered hooks will stop working
            // Notice: we are looping through all events
            // This also means that new callbacks can not passed once component has been initialized
            allEvents.forEach((hook) => {
                delete safeConfig[hook];
            });

            // Update the flatpickr config.
            this.flatpickrInstance.set(safeConfig);

            // Workaround: Allow to change locale dynamically
            ['locale', 'showMonths'].forEach((name) => {
                if (typeof safeConfig[name] !== 'undefined') {
                    this.flatpickrInstance.set(name, safeConfig[name]);
                }
            });

            // emit a new value if the value has changed during config update
            this.$nextTick(() => {
                if (this.value !== this.flatpickrInstance.input.defaultValue) {
                    this.$emit('input', this.flatpickrInstance.input.defaultValue);
                }
            });
        },

        /**
         * Create the flatpickr instance. If already one exists it will be recreated.
         *
         * @param {Object} newConfig
         */
        createFlatpickrInstance(newConfig) {
            this.flatpickrInputRef.removeEventListener('blur', this.onBlur);

            if (this.flatpickrInstance !== null) {
                this.flatpickrInstance.destroy();
                this.flatpickrInstance = null;
            }

            const mergedConfig = this.getMergedConfig(newConfig);
            // Don't pass the original reference to the config object. Use a copy instead.
            const safeConfig = Object.assign({}, mergedConfig);

            // Set event hooks in config.
            this.getEventNames().forEach((event) => {
                safeConfig[event.camelCase] = (...args) => {
                    this.$emit(event.kebabCase, ...args);
                };
            });

            // Init flatpickr only if it is not already loaded.
            this.flatpickrInstance = new Flatpickr(this.flatpickrInputRef, safeConfig);

            // Attach blur event
            this.flatpickrInputRef.addEventListener('blur', this.onBlur);

            // Set the right datepicker value from the property.
            this.setDatepickerValue(this.value);

            // emit a new value if the value has changed during instance recreation
            this.$nextTick(() => {
                if (this.value !== this.flatpickrInstance.input.defaultValue) {
                    this.$emit('input', this.flatpickrInstance.input.defaultValue);
                }
            });
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
                    camelCase: this.kebabToCamel(event)
                });
            });

            return events;
        },

        /**
         * Opens the datepicker.
         */
        openDatepicker() {
            this.$nextTick(() => {
                this.flatpickrInputRef.focus();
                this.flatpickrInstance.open();
            });
        },

        /**
         * Watch for value changed by date-picker itself and notify parent component.
         *
         * @param event
         */
        onInput() {
            if (this.inputValue) {
                this.flatpickrInstance.input.defaultValue = this.inputValue;
            }

            this.$nextTick(() => {
                this.$emit('input', this.flatpickrInstance.input.defaultValue);
            });
        },

        /**
         * Blur event is required by many validation libraries
         *
         * @param event
         */
        onBlur() {
            this.$emit('blur', this.flatpickrInstance.input.defaultValue);
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
        }
    }
};
