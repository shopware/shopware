import Plugin from 'src/plugin-system/plugin.class';
import DateFormatHelper from 'src/helper/date.helper';

/**
 * this plugin formats date and converts it to the local timezone
 * if the data attribute date-format is set
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
