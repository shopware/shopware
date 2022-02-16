import Feature from 'src/helper/feature.helper';
/* Enable tooltips everywhere
 * https://getbootstrap.com/docs/4.3/components/tooltips/#example-enable-tooltips-everywhere
 */

/**
 * @deprecated tag:v6.5.0 - The bootstrap `data-toggle` attribute will be renamed to `data-bs-toggle`
 * @see https://getbootstrap.com/docs/5.0/migration/#javascript
 * @type {string}
 */
const TOOLTIP_SELECTOR = Feature.isActive('V6_5_0_0') ? '[data-bs-toggle="tooltip"]' : '[data-toggle="tooltip"]';

export default class TooltipUtil {

    constructor() {
        /** @deprecated tag:v6.5.0 - Bootstrap v5 uses native elements to init Tooltip plugin */
        if (Feature.isActive('V6_5_0_0')) {
            const tooltipTriggerList = [].slice.call(document.querySelectorAll(TOOLTIP_SELECTOR))
            tooltipTriggerList.forEach(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
        } else {
            $(TOOLTIP_SELECTOR).tooltip();
        }
    }
}
