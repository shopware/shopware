import Plugin from 'src/plugin-system/plugin.class';
import DeviceDetection from 'src/helper/device-detection.helper';
import CookieStorage from 'src/helper/storage/cookie-storage.helper';

export default class CookieAcceptAllPlugin extends Plugin {

    static options = {
        buttonAcceptAllSelector: '.js-cookie-accept-all',
        cookieGroups: 'cookieGroups'
    };

    init() {

        this._button = this.el.querySelector(this.options.buttonAcceptAllSelector);
        this.options
        console.log(this.options.cookieGroups);
        this._registerEvents();
    }

    /**
     * Registers the events for the accept all cookies button
     * @private
     */
    _registerEvents() {

        if (this._button) {
            const submitEvent = (DeviceDetection.isTouchDevice()) ? 'touchstart' : 'click';
            this._button.addEventListener(submitEvent, this._handleAcceptAll.bind(this));
        }
    }

    /**
     * Handle accept all
     * @private
     */
    _handleAcceptAll() {
        const activeCookies = this._getCookies('active');
        const inactiveCookies = this._getCookies('inactive');
        const { cookiePreference } = this.options;

        const activeCookieNames = [];
        const inactiveCookieNames = [];

        inactiveCookies.forEach(({ cookie }) => {
            inactiveCookieNames.push(cookie);

            if (CookieStorage.getItem(cookie)) {
                CookieStorage.removeItem(cookie);
            }
        });

        /**
         * Cookies without value are passed to the updateListener
         * ( see "_handleUpdateListener" method )
         */
        activeCookies.forEach(({ cookie, value, expiration }) => {
            activeCookieNames.push(cookie);

            if (cookie && value) {
                CookieStorage.setItem(cookie, value, expiration);
            }
        });

        CookieStorage.setItem(cookiePreference, '1', '30');

        this._handleUpdateListener(activeCookieNames, inactiveCookieNames);
        this.closeOffCanvas();
    }
}
