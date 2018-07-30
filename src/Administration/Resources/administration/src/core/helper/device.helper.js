/**
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

    window.addEventListener('resize', this.onResize.bind(this));
}

/**
 * @type {Function}
 */
DeviceHelper.prototype.onResize = utils.debounce(function debouncedResize(event) {
    this.listeners.forEach((listenerObject) => {
        listenerObject.callback.call(listenerObject.scope, event);
    });
}, 100);

/**
 * @param {Function} callback
 * @param {Any} scope
 * @returns {number}
 */
DeviceHelper.prototype.resize = function deviceResize(callback, scope = window) {
    this.listeners.push({ callback, scope });
    return this.listeners.length - 1;
};

/**
 * Returns the user agent string.
 *
 * @returns {string}
 */
DeviceHelper.prototype.getUserAgent = function deviceUserAgent() {
    return window.navigator.userAgent;
};

/**
 * Returns the current viewport with in pixels.
 *
 * @returns {number}
 */
DeviceHelper.prototype.getViewportWidth = function deviceViewportWidth() {
    return window.innerWidth;
};

/**
 * Returns the current viewport height in pixels.
 *
 * @returns {number}
 */
DeviceHelper.prototype.getViewportHeight = function deviceViewportHeight() {
    return window.innerHeight;
};

/**
 * Returns the pixel ratio of the device as a number.
 *
 * @returns {number}
 */
DeviceHelper.prototype.getDevicePixelRatio = function devicePixelRatio() {
    return window.devicePixelRatio;
};

/**
 * Returns the device screen width in pixels.
 *
 * @returns {number}
 */
DeviceHelper.prototype.getScreenWidth = function deviceScreenWidth() {
    return window.screen.width;
};

/**
 * Returns the device screen height in pixels.
 *
 * @returns {number}
 */
DeviceHelper.prototype.getScreenHeight = function deviceScreenHeight() {
    return window.screen.height;
};

/**
 * Returns information about the screen orientation.
 *
 * @returns {object}
 */
DeviceHelper.prototype.getScreenOrientation = function deviceScreenOrientation() {
    return window.screen.orientation;
};

/**
 * Returns the current browser language as a string.
 *
 * @returns {string}
 */
DeviceHelper.prototype.getBrowserLanguage = function deviceBrowserLanguage() {
    return window.navigator.language;
};

/**
 * Returns the current platform (e.g. "Win32") as a string.
 *
 * @returns {string}
 */
DeviceHelper.prototype.getPlatform = function devicePlatform() {
    return window.navigator.platform;
};

export default DeviceHelper;
