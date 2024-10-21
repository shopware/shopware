import Debouncer from 'src/helper/debouncer.helper';

/**
 * Viewport Detection
 */
const RESIZE_DEBOUNCE_TIME = 200;

/**
 * @package storefront
 */
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
                passive: true,
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

            // dispatch event that a viewport change has taken place
            this._dispatchViewportEvent('Viewport/hasChanged');
        }
    }

    /**
     * Dispatch custom events for every single viewport
     * @private
     */
    _dispatchEvents() {
        // dispatch specific events for each single viewport
        if (ViewportDetection.isXS()) {
            this._dispatchViewportEvent('Viewport/isXS');
        } else if (ViewportDetection.isSM()) {
            this._dispatchViewportEvent('Viewport/isSM');
        } else if (ViewportDetection.isMD()) {
            this._dispatchViewportEvent('Viewport/isMD');
        } else if (ViewportDetection.isLG()) {
            this._dispatchViewportEvent('Viewport/isLG');
        } else if (ViewportDetection.isXL()) {
            this._dispatchViewportEvent('Viewport/isXL');
        } else if (ViewportDetection.isXXL()) {
            this._dispatchViewportEvent('Viewport/isXXL');
        }
    }

    /**
     * Determine whether the viewport has changed
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
     * Dispatch event with additional data
     * including the previous viewport
     * @param {string} eventName
     * @private
     */
    _dispatchViewportEvent(eventName) {
        document.$emitter.publish(eventName, {
            previousViewport: this.previousViewport,
        });
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
     * Determine whether the current viewport is XXL
     * @returns {boolean}
     */
    static isXXL() {
        return (ViewportDetection.getCurrentViewport() === 'XXL');
    }

    /**
     * Determine the current viewport value set in the HTML::before element,
     * remove all quotes and convert it to uppercase
     * @returns {string}
     */
    static getCurrentViewport() {
        const viewport = window.getComputedStyle(document.documentElement).getPropertyValue('--sw-current-breakpoint');
        return viewport.replace(/['"]+/g, '').toUpperCase();
    }
}
