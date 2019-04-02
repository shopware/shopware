import Plugin from "../../helper/plugin/plugin.class";
import DeviceDetection from "../../helper/device-detection.helper";

const DEBOUNCE_TIME = 125;

const ACTIVE_CLASS = 'is-open';
const CONTAINER_SELECTOR = '[data-menu-flyout="true"]';
const ClOSE_SELECTOR = '[data-close-menu-flyout="true"]';
const FLYOUT_SELECTOR = id => (id) ? `[data-menu-flyout-id="${id}"]` : '[data-menu-flyout-id]';
const TRIGGER_SELECTOR = id => (id) ? `[data-menu-flyout-trigger="${id}"]` : '[data-menu-flyout-trigger]';

/**
 * This Plugin handles the
 * Subcategory display of the main navigation.
 */
export default class FlyoutMenuPlugin extends Plugin {

    init() {
        this._debouncer = null;
        this.el = document.querySelector(CONTAINER_SELECTOR);
        this.triggerEls = this.el.querySelectorAll(TRIGGER_SELECTOR());
        this.closeEls = this.el.querySelectorAll(ClOSE_SELECTOR);
        this.flyoutEls = this.el.querySelectorAll(FLYOUT_SELECTOR());

        this._registerEvents();
    }

    /**
     * registers all needed events
     *
     * @private
     */
    _registerEvents() {
        const clickEvent = (DeviceDetection.isTouchDevice()) ? 'touchstart' : 'click';
        const openEvent = (DeviceDetection.isTouchDevice()) ? 'touchstart' : 'mouseenter';
        const closeEvent = (DeviceDetection.isTouchDevice()) ? 'touchstart' : 'mouseleave';

        // register opening triggers
        this.triggerEls.forEach(el => {
            const flyoutId = el.dataset.menuFlyoutTrigger;
            el.addEventListener(openEvent, this._openFlyoutById.bind(this, flyoutId, el));
            el.addEventListener(closeEvent, () => this._debounce(this._closeAllFlyouts));
        });

        // register closing triggers
        this.closeEls.forEach(el => {
            el.addEventListener(clickEvent, this._closeAllFlyouts.bind(this));
        });

        // register non touch events for open flyouts
        if (!DeviceDetection.isTouchDevice()) {
            this.flyoutEls.forEach(el => {
                el.addEventListener('mousemove', () => this._clearDebounce());
                el.addEventListener('mouseleave', () => this._debounce(this._closeAllFlyouts));
            });
        }
    }

    /**
     * opens a single flyout
     *
     * @param {Element} flyoutEl
     * @param {Element} triggerEl
     * @private
     */
    _openFlyout(flyoutEl, triggerEl) {
        if (!this._isOpen(triggerEl)) {
            this._closeAllFlyouts();
            flyoutEl.classList.add(ACTIVE_CLASS);
            triggerEl.classList.add(ACTIVE_CLASS);
        }
    }

    /**
     * closes a single flyout
     *
     * @param {Element} flyoutEl
     * @param {Element} triggerEl
     * @private
     */
    _closeFlyout(flyoutEl, triggerEl) {
        if (this._isOpen(triggerEl)) {
            flyoutEl.classList.remove(ACTIVE_CLASS);
            triggerEl.classList.remove(ACTIVE_CLASS);
        }
    }

    /**
     * opens a flyout
     *
     * @param {String} flyoutId
     * @param {Element} triggerEl
     * @param {Event} event
     *
     * @private
     */
    _openFlyoutById(flyoutId, triggerEl, event) {
        const flyoutEl = this.el.querySelector(FLYOUT_SELECTOR(flyoutId));
        if (flyoutEl) {
            this._debounce(this._openFlyout, flyoutEl, triggerEl);
        }

        if (!this._isOpen(triggerEl)) {
            FlyoutMenuPlugin._stopEvent(event);
        }
    }

    /**
     * collect all flyouts
     * and close them
     *
     * @private
     */
    _closeAllFlyouts() {
        const flyoutEls = this.el.querySelectorAll(FLYOUT_SELECTOR());

        flyoutEls.forEach((el) => {
            const triggerEl = this._retrieveTriggerEl(el);
            this._closeFlyout(el, triggerEl);
        });
    }

    /**
     *
     * @param {Element} el
     * @return {Element}
     * @private
     */
    _retrieveTriggerEl(el) {
        const flyoutId = el.dataset.menuFlyoutId;
        return this.el.querySelector(TRIGGER_SELECTOR(flyoutId));
    }

    /**
     * returns if the element is opened or not
     *
     * @param {Element} el
     *
     * @return {boolean}
     * @private
     */
    _isOpen(el) {
        return el.classList.contains(ACTIVE_CLASS);
    }

    /**
     *
     * function to debounce menu
     * openings/closings
     *
     * @param {function} fn
     * @param {array} args
     *
     * @return {Function}
     * @private
     */
    _debounce(fn, ...args) {
        this._clearDebounce();
        this._debouncer = setTimeout(fn.bind(this, ...args), DEBOUNCE_TIME);
    }

    /**
     * clears the debounce timer
     *
     * @private
     */
    _clearDebounce() {
        clearTimeout(this._debouncer);
    }

    /**
     * prevents the passed event
     *
     * @param {Event} event
     * @private
     */
    static _stopEvent(event) {
        if (event && event.cancelable) {
            event.preventDefault();
            event.stopImmediatePropagation();
        }
    }

}

