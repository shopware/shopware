import Plugin from 'src/plugin-system/plugin.class';
import DeviceDetection from 'src/helper/device-detection.helper';
import DomAccess from 'src/helper/dom-access.helper';
import Iterator from 'src/helper/iterator.helper';
import Feature from 'src/helper/feature.helper';


/**
 * This Plugin handles the
 * Subcategory display of the main navigation.
 *
 * @package storefront
 * @deprecated tag:v6.7.0 - Will be removed, see NavbarPlugin for the new implementation
 */
export default class FlyoutMenuPlugin extends Plugin {

    static options = {
        /**
         * Hover debounce delay.
         */
        debounceTime: 125,

        /**
         * Class to add when the flyout is active.
         */
        activeCls: 'is-open',

        /**
         * Class to remove when flyout is shown.
         */
        hiddenCls: 'hidden',

        /**
         * Selector for the close buttons.
         */
        closeSelector: '.js-close-flyout-menu',

        /**
         * Selector id for the main navigation.
         */
        mainNavigationId: 'mainNavigation',

        /**
         * Id attribute for the flyout.
         * Should be the same as 'triggerDataAttribute'
         */
        flyoutIdDataAttribute: 'data-flyout-menu-id',

        /**
         * Trigger attribute for the opening elements.
         * Should be the same as 'flyoutIdDataAttribute'
         */
        triggerDataAttribute: 'data-flyout-menu-trigger',
    };

    init() {
        this._debouncer = null;
        this._triggerEls = this.el.querySelectorAll(`[${this.options.triggerDataAttribute}]`);
        this._closeEls = this.el.querySelectorAll(this.options.closeSelector);
        this._flyoutEls = this.el.querySelectorAll(`[${this.options.flyoutIdDataAttribute}]`);
        this._hasOpenedFlyouts = false;
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

        document.addEventListener('keydown', (event) => {
            if (this._hasOpenedFlyouts === true && (event.code === 'Escape' || event.keyCode === 27)) {
                this._debounce(this._closeAllFlyouts);
            }
        });

        const mainContent = document.getElementsByTagName('main')[0];
        if (mainContent) {
            mainContent.addEventListener('focusin', () => {
                this._debounce(this._closeAllFlyouts);
            });
        }

        // register opening triggers
        Iterator.iterate(this._triggerEls, el => {
            const flyoutId = DomAccess.getDataAttribute(el, this.options.triggerDataAttribute);
            el.addEventListener(openEvent, this._openFlyoutById.bind(this, flyoutId, el));
            el.addEventListener('keydown', (event) => {
                if (event.code === 'Enter' || event.keyCode === 13) {
                    event.preventDefault();
                    event.stopPropagation();
                    if (!this._hasOpenedFlyouts) {
                        this._openFlyoutById(flyoutId, el, event);
                        this._hasOpenedFlyouts = true;
                    } else {
                        this._closeAllFlyouts();
                        this._hasOpenedFlyouts = false;
                    }
                }
            });
            el.addEventListener(closeEvent, () => this._debounce(this._closeAllFlyouts));
        });

        // register closing triggers
        Iterator.iterate(this._closeEls, el => {
            el.addEventListener(clickEvent, this._closeAllFlyouts.bind(this));
        });

        // register non touch events for open flyouts
        if (!DeviceDetection.isTouchDevice()) {
            Iterator.iterate(this._flyoutEls, el => {
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
            flyoutEl.classList.add(this.options.activeCls);
            triggerEl.classList.add(this.options.activeCls);
            this._hasOpenedFlyouts = true;
        }

        this.$emitter.publish('openFlyout');
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
            flyoutEl.classList.remove(this.options.activeCls);
            if (Feature.isActive('ACCESSIBILITY_TWEAKS')) {
                flyoutEl.classList.add(this.options.hiddenCls);
                flyoutEl.style.removeProperty('top');
            }
            triggerEl.classList.remove(this.options.activeCls);
            this._hasOpenedFlyouts = false;
        }

        this.$emitter.publish('closeFlyout');
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
        const flyoutEl = this.el.querySelector(`[${this.options.flyoutIdDataAttribute}='${flyoutId}']`);

        if (flyoutEl) {
            if (Feature.isActive('ACCESSIBILITY_TWEAKS')) {
                // padding is needed to see the complete focus style (otherwise it will be cut off)
                const padding = parseInt(getComputedStyle(triggerEl).getPropertyValue('padding-bottom'), 10);
                flyoutEl.style.top = `${this.el.offsetHeight + padding}px`;
                flyoutEl.classList.remove(this.options.hiddenCls);
            }
            this._debounce(this._openFlyout, flyoutEl, triggerEl);
        }

        if (!this._isOpen(triggerEl)) {
            FlyoutMenuPlugin._stopEvent(event);
        }

        this.$emitter.publish('openFlyoutById');
    }

    /**
     * collect all flyouts
     * and close them
     *
     * @private
     */
    _closeAllFlyouts() {
        const flyoutEls = this.el.querySelectorAll(`[${this.options.flyoutIdDataAttribute}]`);

        Iterator.iterate(flyoutEls, el => {
            const triggerEl = this._retrieveTriggerEl(el);
            this._closeFlyout(el, triggerEl);
        });

        this.$emitter.publish('closeAllFlyouts');
    }

    /**
     *
     * @param {Element} el
     * @returns {Element}
     * @private
     */
    _retrieveTriggerEl(el) {
        const flyoutId = DomAccess.getDataAttribute(el, this.options.flyoutIdDataAttribute, false);
        return this.el.querySelector(`[${this.options.triggerDataAttribute}='${flyoutId}']`);
    }

    /**
     * returns if the element is opened or not
     *
     * @param {Element} el
     *
     * @returns {boolean}
     * @private
     */
    _isOpen(el) {
        return el.classList.contains(this.options.activeCls);
    }

    /**
     *
     * function to debounce menu
     * openings/closings
     *
     * @param {function} fn
     * @param {array} args
     *
     * @returns {Function}
     * @private
     */
    _debounce(fn, ...args) {
        this._clearDebounce();
        this._debouncer = setTimeout(fn.bind(this, ...args), this.options.debounceTime);
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

