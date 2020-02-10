import Plugin from 'src/plugin-system/plugin.class';
import flatpickr from 'flatpickr';
import Locales from 'flatpickr/dist/l10n/index';

/**
 * this plugin enables the data-picker for the storefront
 */
export default class DatePickerPlugin extends Plugin {

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
        locale: 'default'
    };

    // ToDo NEXT-5602: Remove this temporary solution until formats are linked to languages
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

    init() {
        let localeIndex = 'default';
        if (this.options.locale.substring(0, 2) !== 'en') {
            localeIndex = this.options.locale.substring(0, 2);
        }

        const options = {
            altFormat: this.getAltFormat(localeIndex),
            locale: Locales[localeIndex],
            time_24hr: Locales[localeIndex].time_24hr
        };

        flatpickr(this.el, {
            ...this.options,
            ...options
        });
    }
}
