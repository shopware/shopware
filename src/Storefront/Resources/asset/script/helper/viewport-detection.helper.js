import Debouncer from 'asset/script/helper/debouncer.helper';

/**
 * Viewport Detection
 */
const RESIZE_DEBOUNCE_TIME = 200;

const EVENT_VIEWPORT_HAS_CHANGED = 'Viewport/hasChanged';
const EVENT_VIEWPORT_IS_XS = 'Viewport/isXS';
const EVENT_VIEWPORT_IS_SM = 'Viewport/isSM';
const EVENT_VIEWPORT_IS_MD = 'Viewport/isMD';
const EVENT_VIEWPORT_IS_LG = 'Viewport/isLG';
const EVENT_VIEWPORT_IS_XL = 'Viewport/isXL';

export default class ViewportDetection {

    /**
     * Constructor
     */
    constructor() {
        this.previousViewport = null;
        this.currentViewport = ViewportDetection.getCurrentViewport();
        this._registerEvents();
    }

    /**
     * Register events
     * @private
     */
    _registerEvents() {
        // add listener on DOMContentLoaded to initially register viewport events
        window.addEventListener('DOMContentLoaded', this._onDOMContentLoaded.bind(this));

        // add listener to the window resize events
        window.addEventListener(
            'resize',
            Debouncer.debounce(this._onResize.bind(this), RESIZE_DEBOUNCE_TIME),
            {
                capture: true,
                passive: true
            }
        );
    }

    /**
     * Dispatch the custom viewport events immediately after DOM content
     * has been loaded to allow the execution of other JS code via listening the events
     * @private
     */
    _onDOMContentLoaded() {
        this._dispatchEvents();
    }

    /**
     * Dispatch the custom viewport event after window resizing
     * to allow the execution of other JS code via listening the events
     * @private
     */
    _onResize() {
        if (this._viewportHasChanged(ViewportDetection.getCurrentViewport())) {
            this._dispatchEvents();
        }
    }

    /**
     * Dispatch custom events for every single viewport
     * @private
     */
    _dispatchEvents() {
        // dispatch event that a viewport change has taken place
        this._dispatchViewportEvent(EVENT_VIEWPORT_HAS_CHANGED);

        // dispatch specific events for each single viewport
        if (ViewportDetection.isXS()) {
            this._dispatchViewportEvent(EVENT_VIEWPORT_IS_XS);
        } else if (ViewportDetection.isSM()) {
            this._dispatchViewportEvent(EVENT_VIEWPORT_IS_SM);
        } else if (ViewportDetection.isMD()) {
            this._dispatchViewportEvent(EVENT_VIEWPORT_IS_MD);
        } else if (ViewportDetection.isLG()) {
            this._dispatchViewportEvent(EVENT_VIEWPORT_IS_LG);
        } else if (ViewportDetection.isXL()) {
            this._dispatchViewportEvent(EVENT_VIEWPORT_IS_XL);
        }
    }

    /**
     * Determine whether the the viewport has changed
     * @param newViewport
     * @returns {boolean}
     * @private
     */
    _viewportHasChanged(newViewport) {

        // determine whether the viewport has changed
        const hasChanged = newViewport !== this.currentViewport;

        if (hasChanged) {
            this.previousViewport = this.currentViewport;
            this.currentViewport = newViewport;
        }

        return hasChanged;
    }

    /**
     * Dispatch custom event with additional data
     * including the previous viewport
     * @param {string} eventName
     * @private
     */
    _dispatchViewportEvent(eventName) {
        document.dispatchEvent(new CustomEvent(eventName, {
            detail: {
                previousViewport: this.previousViewport
            }
        }));
    }

    /**
     * Determine whether the current viewport is XS
     * @returns {boolean}
     */
    static isXS() {
        return (ViewportDetection.getCurrentViewport() === 'XS');
    }

    /**
     * Determine whether the current viewport is SM
     * @returns {boolean}
     */
    static isSM() {
        return (ViewportDetection.getCurrentViewport() === 'SM');
    }

    /**
     * Determine whether the current viewport is MD
     * @returns {boolean}
     */
    static isMD() {
        return (ViewportDetection.getCurrentViewport() === 'MD');
    }

    /**
     * Determine whether the current viewport is LG
     * @returns {boolean}
     */
    static isLG() {
        return (ViewportDetection.getCurrentViewport() === 'LG');
    }

    /**
     * Determine whether the current viewport is XL
     * @returns {boolean}
     */
    static isXL() {
        return (ViewportDetection.getCurrentViewport() === 'XL');
    }

    /**
     * Determine the current viewport value set in the HTML::before element,
     * remove all quotes and convert it to uppercase
     * @returns {string}
     */
    static getCurrentViewport() {
        const viewport = window.getComputedStyle(document.documentElement, ':before').content;
        return viewport.replace(/['"]+/g, '').toUpperCase();
    }

    /**
     * Returns the Viewport Has Changed Event constants value
     * @returns {string}
     * @constructor
     */
    static EVENT_VIEWPORT_HAS_CHANGED() {
        return EVENT_VIEWPORT_HAS_CHANGED;
    }

    /**
     * Returns the Viewport XS Event constants value
     * @returns {string}
     * @constructor
     */
    static EVENT_VIEWPORT_IS_XS() {
        return EVENT_VIEWPORT_IS_XS;
    }

    /**
     * Returns the Viewport SM Event constants value
     * @returns {string}
     * @constructor
     */
    static EVENT_VIEWPORT_IS_SM() {
        return EVENT_VIEWPORT_IS_SM;
    }

    /**
     * Returns the Viewport MD Event constants value
     * @returns {string}
     * @constructor
     */
    static EVENT_VIEWPORT_IS_MD() {
        return EVENT_VIEWPORT_IS_MD;
    }

    /**
     * Returns the Viewport LG Event constants value
     * @returns {string}
     * @constructor
     */
    static EVENT_VIEWPORT_IS_LG() {
        return EVENT_VIEWPORT_IS_LG;
    }

    /**
     * Returns the Viewport XL Event constants value
     * @returns {string}
     * @constructor
     */
    static EVENT_VIEWPORT_IS_XL() {
        return EVENT_VIEWPORT_IS_XL;
    }
}
