import Plugin from 'src/plugin-system/plugin.class';
import DeviceDetection from 'src/helper/device-detection.helper';
import CookieStorage from 'src/helper/storage/cookie-storage.helper';
import {COOKIE_CONFIGURATION_UPDATE} from './cookie-configuration.plugin';

export default class CookieAcceptAllPlugin extends Plugin {

    static options = {
        buttonAcceptAllSelector: '.js-cookie-accept-all',
        cookiePreference: 'cookie-preference',
        cookieGroups: 'cookieGroups'
    };

    init() {

        this._button = this.el.querySelector(this.options.buttonAcceptAllSelector);
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

    _handleUpdateListener(cookieGroupNames) {

        document.$emitter.publish(COOKIE_CONFIGURATION_UPDATE, cookieGroupNames);
    }

    /**
     * Hides cookie bar
     */
    _hideCookieBar() {
        this.el.style.display = 'none';

        this.$emitter.publish('hideCookieBar');
    }

    /**
     * Handle accept all
     * @private
     */
    _handleAcceptAll() {

        const allCookies = JSON.parse(this.options.cookieGroups);
        const {cookiePreference} = this.options;

        const cookieGroupNames = [];

        /**
         * Cookies without value are passed to the updateListener
         * ( see "_handleUpdateListener" method )
         */
        allCookies.forEach(({cookie, value, expiration, entries}) => {

            if (entries) {
                entries.forEach(({cookie, value, expiration}) => {
                    this._setCookie(cookie, value, expiration, cookieGroupNames);
                });
            } else {
                this._setCookie(cookie, value, expiration, cookieGroupNames);
            }
        });

        CookieStorage.setItem(cookiePreference, '1', '30');

        this._handleUpdateListener(cookieGroupNames);
        this._hideCookieBar();
    }

    _setCookie(cookie, value, expiration, cookieGroupNames) {
        cookieGroupNames.push(cookie);

        if (cookie && value) {
            CookieStorage.setItem(cookie, value, expiration);
        }
    }
}
