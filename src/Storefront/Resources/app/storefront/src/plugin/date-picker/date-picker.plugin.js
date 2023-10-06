import Plugin from 'src/plugin-system/plugin.class';
import flatpickr from 'flatpickr';
import Locales from 'flatpickr/dist/l10n/index';
import DomAccess from 'src/helper/dom-access.helper';

/**
 * Controls the date picker component
 *
 * @class
 */
export default class DatePickerPlugin extends Plugin {

    /**
     * Plugin options
     *
     * @type {Object}
     */
    static options = {
        dateFormat: 'Y-m-dTH:i:S+00:00',
        altFormat: 'j. F Y, H:i',
        altInput: true,
        time_24hr: true,
        enableTime: true,
        noCalendar: false,
        weekNumbers: true,
        allowInput: true,
        minDate: null,
        maxDate: null,
        locale: 'default',
        selectors: {
            openButton: null,
            closeButton: null,
            clearButton: null,
        },
    };

    /**
     * Initializes the flatpickr component and calls the registration of EventListeners
     *
     * @returns {void}
     */
    init() {
        this.inputElement = this.el;

        // workaround because the min/maxDate always need to be a full date string, even in time only mode.
        if (this.options.enableTime && this.options.noCalendar) {
            if (this.options.minDate) {
                this.options.minDate = this.convertTimeToTodayDateString(this.options.minDate);
            }

            if (this.options.maxDate) {
                this.options.maxDate = this.convertTimeToTodayDateString(this.options.maxDate);
            }
        }

        this.flatpickrElement = flatpickr(this.inputElement, {
            ...this.options,
            ...this.generateFlatpickrOptions(),
        });

        this.registerEventListeners();
    }

    /**
     * Registers the EventListeners
     *
     * @returns {void}
     */
    registerEventListeners() {
        if (this.options.selectors.openButton !== null) {
            this.openButton = DomAccess.querySelector(document, this.options.selectors.openButton);
            this.openButton.addEventListener('click', this.onOpenButtonClick.bind(this));
        }

        if (this.options.selectors.closeButton !== null) {
            this.closeButton = DomAccess.querySelector(document, this.options.selectors.closeButton);
            this.closeButton.addEventListener('click', this.onCloseButtonClick.bind(this));
        }

        if (this.options.selectors.clearButton !== null) {
            this.clearButton = DomAccess.querySelector(document, this.options.selectors.clearButton);
            this.clearButton.addEventListener('click', this.onClearButtonClick.bind(this));
            this.inputElement.addEventListener('change', this.onInputChange.bind(this));
        }
    }

    /**
     * Opens the selection modal of the flatpickr instance
     *
     * @returns {void}
     */
    onOpenButtonClick() {
        this.flatpickrElement.open();
    }

    /**
     * Closes the selection modal of the flatpickr instance
     *
     * @returns {void}
     */
    onCloseButtonClick() {
        this.flatpickrElement.close();
    }

    /**
     * Clears the input of the flatpickr instance
     *
     * @returns {void}
     */
    onClearButtonClick() {
        this.flatpickrElement.clear();
    }

    /**
     * Disables the clearButton if input is empty and vice versa
     *
     * @returns {void}
     */
    onInputChange() {
        this.clearButton.disabled = this.inputElement.value.length <= 0;
    }

    /**
     * Constructs the options object needed to create the flatpickr component
     *
     * @returns {Object}
     */
    generateFlatpickrOptions() {
        let localeIndex = 'default';
        if (this.options.locale.substring(0, 2) !== 'en') {
            localeIndex = this.options.locale.substring(0, 2);
        }

        return {
            altFormat: this.getAltFormat(localeIndex),
            locale: Locales[localeIndex],
            time_24hr: Locales[localeIndex].time_24hr,
        };
    }

    /**
     * Returns the format string used for display and database friendly saving purposes
     *
     * @ToDo NEXT-5602 - Remove this temporary solution until formats are linked to languages
     *
     * @returns {String}
     */
    getAltFormat(localeIndex) {
        let dateFormat;
        let timeFormat;

        switch (localeIndex) {
            case 'de':
                dateFormat = 'd. F Y';
                timeFormat = 'H:i';
                break;
            case 'en':
            case 'default':
                dateFormat = 'F J, Y';
                timeFormat = 'h:i K';
                break;
            default:
                dateFormat = 'Y-m-d';
                timeFormat = 'H:i';
                break;
        }

        if (this.options.enableTime) {
            return timeFormat;
        }

        return dateFormat;
    }

    /**
     * Converts time string to full datetime string for compatibility reasons
     *
     * @returns {String}
     */
    convertTimeToTodayDateString(timeString) {
        if (timeString.includes('T')) {
            return timeString;
        }

        const today = (new Date()).toISOString();
        const dateString = today.split('T')[0];

        return `${dateString}T${timeString}`;
    }
}
