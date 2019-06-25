/* Enable tooltips everywhere
 * https://getbootstrap.com/docs/4.3/components/tooltips/#example-enable-tooltips-everywhere
 */
const TOOLTIP_SELECTOR = '[data-toggle="tooltip"]';

export default class TooltipUtil {

    constructor() {
        $(TOOLTIP_SELECTOR).tooltip();
    }
}
