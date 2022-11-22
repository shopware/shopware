import Feature from 'src/helper/feature.helper';

/**
 * @deprecated tag:v6.5.0 - The bootstrap `data-toggle` attribute will be renamed to `data-bs-toggle`
 * @see https://getbootstrap.com/docs/5.0/migration/#javascript
 * @type {string}
 */
const BS_TOGGLE_ATTR = Feature.isActive('v6.5.0.0') ? 'data-bs-toggle' : 'data-toggle';

const TOOLTIP_SELECTOR = `[${BS_TOGGLE_ATTR}="tooltip"]`;
const POPOVER_SELECTOR = `[${BS_TOGGLE_ATTR}="popover"]`;

/**
 * @package storefront
 */
export default class BootstrapUtil {

    /**
     * Initialize Tooltip plugin everywhere
     * @see https://getbootstrap.com/docs/4.3/components/tooltips/#example-enable-tooltips-everywhere
     */
    static initTooltip() {
        /** @deprecated tag:v6.5.0 - Bootstrap v5 uses native elements to init Tooltip plugin */
        if (Feature.isActive('v6.5.0.0')) {
            return new bootstrap.Tooltip(document.body, {
                selector: TOOLTIP_SELECTOR,
            });
        } else {
            $('body').tooltip({
                selector: TOOLTIP_SELECTOR,
            });
        }
    }

    /**
     * Initialize Popover plugin everywhere
     * @see https://getbootstrap.com/docs/4.3/components/popovers/#example-enable-popovers-everywhere
     */
    static initPopover() {
        /** @deprecated tag:v6.5.0 - Bootstrap v5 uses native elements to init Popover plugin */
        if (Feature.isActive('v6.5.0.0')) {
            new bootstrap.Popover(document.body, {
                selector: POPOVER_SELECTOR,
                trigger: 'focus',
            });
        } else {
            $('body').popover({
                selector: POPOVER_SELECTOR,
                trigger: 'focus',
            });
        }
    }

    static initBootstrapPlugins() {
        this.initTooltip();
        this.initPopover();
    }
}
