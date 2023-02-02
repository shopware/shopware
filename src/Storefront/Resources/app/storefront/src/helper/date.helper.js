export default class DateFormatHelper {
    static format(val, options = {}) {
        if (val === null) {
            return '';
        }

        const dateObj = new Date(val);
        // eslint-disable-next-line
        if (isNaN(dateObj)) {
            return '';
        }

        const langCode = navigator.language;
        const defaultOptions = {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
        };
        options = { ...defaultOptions, ...options };

        const dateTimeFormatter = new Intl.DateTimeFormat(langCode, options);

        return dateTimeFormatter.format(dateObj);
    }
}
