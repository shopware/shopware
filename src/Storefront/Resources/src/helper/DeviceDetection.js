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
}