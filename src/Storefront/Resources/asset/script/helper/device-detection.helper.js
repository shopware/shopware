export default class DeviceDetection {

    /**
     * Returns whether the current device is a touch device
     * @returns {boolean}
     */
    static isTouchDevice() {
        return ("ontouchstart" in document.documentElement);
    }

    /**
     * Returns whether the current userAgent is an IOS device
     * @returns {boolean}
     */
    static isIOSDevice() {
        return (DeviceDetection.isIPhoneDevice() || DeviceDetection.isIPadDevice());
    }

    /**
     * Returns if we're dealing with a native Windows browser
     * @returns {boolean}
     */
    static isNativeWindowsBrowser() {
        return (DeviceDetection.isIEBrowser() || DeviceDetection.isEdgeBrowser());
    }

    /**
     * Returns whether the current userAgent is an iPhone device
     * @returns {boolean}
     */
    static isIPhoneDevice() {
        let userAgent = navigator.userAgent;
        return !!(userAgent.match(/iPhone/i));
    }

    /**
     * Returns whether the current userAgent is an iPad device
     * @returns {boolean}
     */
    static isIPadDevice() {
        let userAgent = navigator.userAgent;
        return !!(userAgent.match(/iPad/i));
    }

    /**
     * Returns if we're dealing with the Internet Explorer.
     * @returns {boolean}
     */
    static isIEBrowser() {
        let userAgent = navigator.userAgent.toLowerCase();
        return userAgent.indexOf('msie') !== -1 || !!navigator.userAgent.match(/Trident.*rv[ :]*11\./);
    }

    /**
     * Returns if we're dealing with the Windows Edge browser.
     * @returns {boolean}
     */
    static isEdgeBrowser() {
        let userAgent = navigator.userAgent.toLowerCase();
        return userAgent.indexOf('edge') !== -1;
    }
}
