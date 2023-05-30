/**
 * @package storefront
 */
export default class DateFormatHelper {
    /**
     * Note that val is parsed by the JavaScript Date object, which might
     * yield, in the case of a string, different interpretations of the
     * input value, based on the browser language, i.e. British vs US date
     * formats, which might yield different results, c.f.:
     * https://developer.mozilla.org/en-US/docs/Web/JavaScript/Reference/Global_Objects/Date
     * So if passing val as a string, if you want to be save use the
     * ISO 8601 format  (YYYY-MM-DDTHH:mm:ss.sssZ)
     *
     * @param  {string|Date} val
     */
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
