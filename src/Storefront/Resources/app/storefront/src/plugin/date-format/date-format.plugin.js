import Plugin from 'src/plugin-system/plugin.class';
import DateFormatHelper from 'src/helper/date.helper';

/**
 * this plugin formats date and converts it to the local timezone
 * if the data attribute date-format is set
 *
 * This plugin utilizes the JavaScript Date object to parse the given
 * value, so take different locales of the browser into account,
 * which might yield different results, c.f.:
 * https://developer.mozilla.org/en-US/docs/Web/JavaScript/Reference/Global_Objects/Date
 *
 * @package storefront
 */
export default class DateFormat extends Plugin {
    init() {
        let formatOpts = this.el.getAttribute('data-date-format');
        if (formatOpts.length > 0) {
            formatOpts = JSON.parse(formatOpts);
        }
        this.el.innerHTML = DateFormatHelper.format(this.el.innerHTML.trim(), formatOpts);
    }
}
