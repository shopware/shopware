export default class DeviceDetection {

    /**
     * Returns whether the current device is a touch device
     * @returns {boolean}
     */
    static isTouchDevice() {
        return ('ontouchstart' in document.documentElement);
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
        const userAgent = navigator.userAgent;
        return !!userAgent.match(/iPhone/i);
    }

    /**
     * Returns whether the current userAgent is an iPad device
     * @returns {boolean}
     */
    static isIPadDevice() {
        const userAgent = navigator.userAgent;
        return !!userAgent.match(/iPad/i);
    }

    /**
     * Returns if we're dealing with the Internet Explorer.
     * @returns {boolean}
     */
    static isIEBrowser() {
        const userAgent = navigator.userAgent.toLowerCase();
        return userAgent.indexOf('msie') !== -1 || !!navigator.userAgent.match(/Trident.*rv:\d+\./);
    }

    /**
     * Returns if we're dealing with the Windows Edge browser.
     * @returns {boolean}
     */
    static isEdgeBrowser() {
        const userAgent = navigator.userAgent;
        return !!userAgent.match(/Edge\/\d+/i);
    }

    /**
     * Returns a list of css classes with the boolean result of all device detection functions.
     * @returns {object}
     */
    static getList() {
        return {
            'is-touch': DeviceDetection.isTouchDevice(),
            'is-ios': DeviceDetection.isIOSDevice(),
            'is-native-windows':  DeviceDetection.isNativeWindowsBrowser(),
            'is-iphone': DeviceDetection.isIPhoneDevice(),
            'is-ipad': DeviceDetection.isIPadDevice(),
            'is-ie': DeviceDetection.isIEBrowser(),
            'is-edge': DeviceDetection.isEdgeBrowser(),
        };
    }
}
