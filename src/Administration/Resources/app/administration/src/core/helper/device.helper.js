/**
 * @package admin
 *
 * @module core/helper/device
 */
import utils from 'src/core/service/util.service';

/**
 * The DeviceHelper provides methods to get device and browser information like the current viewport size.
 * The helper methods can be accessed with "this.$device" in every Vue component.
 *
 * @constructor
 */
function DeviceHelper() {
    this.listeners = [];

    window.addEventListener('resize', this.resize.bind(this));
}

DeviceHelper.prototype = Object.assign(DeviceHelper.prototype, {
    /**
     * Resize method which will be fired when the user resizes the browser.
     *
     * @returns {void}
     */
    resize: utils.debounce(function debouncedResize(event) {
        this.listeners.forEach((listenerObject) => {
            listenerObject.listener.call(listenerObject.scope, event);
        });
    }, 100),

    /**
     * Registers an event register for the browser "resize" event.
     *
     * @param {Function} callback
     * @param {Any} scope
     * @param {Object} component
     * @returns {number}
     */
    onResize({ listener, scope, component }) {
        if (!scope) {
            scope = window;
        }
        this.listeners.push({ listener, scope, component });
        return this.listeners.length - 1;
    },

    removeResizeListener(component) {
        this.listeners = this.listeners.filter((listenerObject) => {
            return component !== listenerObject.component;
        });

        return true;
    },

    /**
     * Returns the user agent string.
     *
     * @returns {string}
     */
    getUserAgent() {
        return window.navigator.userAgent;
    },

    /**
     * Returns the current viewport with in pixels.
     * @returns {number}
     */
    getViewportWidth() {
        return window.innerWidth;
    },

    /**
     * Returns the current viewport height in pixels.
     *
     * @returns {number}
     */
    getViewportHeight() {
        return window.innerHeight;
    },

    /**
     * Returns the pixel ratio of the device as a number.
     *
     * @returns {number}
     */
    getDevicePixelRatio() {
        return window.devicePixelRatio;
    },

    /**
     * Returns the device screen width in pixels.
     *
     * @returns {number}
     */
    getScreenWidth() {
        return window.screen.width;
    },

    /**
     * Returns the device screen height in pixels.
     *
     * @returns {number}
     */
    getScreenHeight() {
        return window.screen.height;
    },

    /**
     * Returns information about the screen orientation.
     *
     * @returns {object}
     */
    getScreenOrientation() {
        return window.screen.orientation;
    },

    /**
     * Returns the current browser language as a string.
     *
     * @returns {string}
     */
    getBrowserLanguage() {
        return window.navigator.language;
    },

    /**
     * Returns the current platform (e.g. "Win32") as a string.
     *
     * @returns {string}
     */
    getPlatform() {
        return window.navigator.platform;
    },

    /**
     * Returns the system-key (e.g. "CTRL") as a string depending of the current operating system.
     *
     * @returns {string}
     */
    getSystemKey() {
        return this.getPlatform().indexOf('Mac') > -1 ? 'CTRL' : 'ALT';
    },
});

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default DeviceHelper;
