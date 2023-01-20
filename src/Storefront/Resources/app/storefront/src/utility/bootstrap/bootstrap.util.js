const TOOLTIP_SELECTOR = '[data-bs-toggle="tooltip"]';
const POPOVER_SELECTOR = '[data-bs-toggle="popover"]';

/**
 * @package storefront
 */
export default class BootstrapUtil {

    /**
     * Initialize Tooltip plugin everywhere
     * @see https://getbootstrap.com/docs/5.2/components/tooltips/#enable-tooltips
     */
    static initTooltip() {
        return new bootstrap.Tooltip(document.body, {
            selector: TOOLTIP_SELECTOR,
        });
    }

    /**
     * Initialize Popover plugin everywhere
     * @see https://getbootstrap.com/docs/5.2/components/popovers/#enable-popovers
     */
    static initPopover() {
        new bootstrap.Popover(document.querySelector('html'), {
            selector: POPOVER_SELECTOR,
            trigger: 'focus',
        });
    }

    static initBootstrapPlugins() {
        this.initTooltip();
        this.initPopover();
    }
}
