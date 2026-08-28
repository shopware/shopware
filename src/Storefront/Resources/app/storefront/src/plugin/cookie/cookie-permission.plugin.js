/**
 * @sw-package framework
 */

import Debouncer from 'src/helper/debouncer.helper';
import DeviceDetection from 'src/helper/device-detection.helper';
import CookieStorage from 'src/helper/storage/cookie-storage.helper';
import Plugin from 'src/plugin-system/plugin.class';

export default class CookiePermissionPlugin extends Plugin {

    static options = {

        /**
         * cookie expiration time
         * the amount of days until the cookie bar will be displayed again
         */
        cookieExpiration: 30,

        /**
         * cookie set to determine if cookies were accepted or denied
         */
        cookieName: 'cookie-preference',

        /**
         * cookie dismiss button selector
         */
        buttonSelector: '.js-cookie-permission-button',

        /**
         * resize debounce delay
         */
        resizeDebounceTime: 200,

        /**
         * When true, the cookie bar will be automatically focused when it is shown
         * @type {boolean}
         */
        autoFocus: true,
    };

    init() {
        this._button = this.el.querySelector(this.options.buttonSelector);

        if (!this._isPreferenceSet()) {
            this._setBodyPadding();
            this._registerEvents();
            this._setFocus();
        }

        this._registerShowAndHideCookieBarEvents();
    }

    /**
     * Sets an automatic focus to the cookie bar
     * @private
     * @returns {void}
     */
    _setFocus() {
        if (this._isDataPrivacyPage() || !this.options.autoFocus) {
            return;
        }

        window.focusHandler.setFocus(this.el, { preventScroll: true });
    }

    /**
     * Checks if the current page is the data privacy page.
     * When user navigates to the data privacy page from the cookie bar,
     * the cookie bar should not be focused so the page can be read first.
     * @private
     * @returns {boolean}
     */
    _isDataPrivacyPage() {
        const dataPrivacyLink = this.el.querySelector('.cookie-permission-content a');

        if (!dataPrivacyLink?.href) {
            return false;
        }

        const normalize = (url) => {
            return url.origin + url.pathname.replace(/\/$/, '').toLowerCase();
        };

        const currentUrl = new URL(this._getCurrentLocation());
        const dataPrivacyUrl = new URL(dataPrivacyLink.href);

        return normalize(currentUrl) === normalize(dataPrivacyUrl);
    }

    /**
     * Thin wrapper to ger the current location of the page.
     * (non-configurable in JSDOM v26).
     * @private
     * @returns {string}
     */
    _getCurrentLocation() {
        return window.location.href;
    }

    /**
     * Checks if a cookie preference is already set.
     * If not, the cookie bar is displayed.
     * @private
     */
    _isPreferenceSet() {
        const cookiePermission = CookieStorage.getItem(this.options.cookieName);

        if (!cookiePermission) {
            this._showCookieBar();
            return false;
        }

        return true;
    }

    /**
     * Shows cookie bar
     * @private
     */
    _showCookieBar() {
        this.el.style.display = 'block';

        this.$emitter.publish('showCookieBar');
    }

    /**
     * Hides cookie bar
     * @private
     */
    _hideCookieBar() {
        this.el.style.display = 'none';

        this.$emitter.publish('hideCookieBar');
    }


    /**
     * register all needed events
     * @private
     */
    _registerEvents() {

        if (this._button) {
            const submitEvent = (DeviceDetection.isTouchDevice()) ? 'touchstart' : 'click';
            this._button.addEventListener(submitEvent, this._handleDenyButton.bind(this));
        }

        window.addEventListener('resize', Debouncer.debounce(this._setBodyPadding.bind(this), this.options.resizeDebounceTime), {
            capture: true,
            passive: true,
        });
    }

    /**
     * Register events for showing and hiding the cookie bar
     * This is needed because the cookie bar is shown and hidden by other plugins
     * without checking if the cookie preference is already set
     * @private
     */
    _registerShowAndHideCookieBarEvents() {
        document.addEventListener('showCookieBar', this._handleShowCookieBarEvent.bind(this));
        document.addEventListener('hideCookieBar', this._handleHideCookieBarEvent.bind(this));
    }

    /**
     * Event handler for custom showCookieBar event
     * Shows the cookie bar when triggered by other plugins
     * @private
     */
    _handleShowCookieBarEvent() {
        this._setBodyPadding();
        this._showCookieBar();
        this._setFocus();
    }

    /**
     * Event handler for custom hideCookieBar event
     * Hides the cookie bar when triggered by other plugins
     * @private
     */
    _handleHideCookieBarEvent() {
        this._removeBodyPadding();
        this._hideCookieBar();
    }

    /**
     * Event handler for the cookie bar 'deny' button
     * Sets the 'cookie-preference' cookie to hide the cookie bar
     * @private
     */
    _handleDenyButton(event) {
        event.preventDefault();

        const { cookieExpiration, cookieName } = this.options;
        this._hideCookieBar();
        this._removeBodyPadding();
        CookieStorage.setItem(cookieName, '1', cookieExpiration);

        this.$emitter.publish('onClickDenyButton');
    }

    /**
     * Calculates cookie bar height
     * @private
     */
    _calculateCookieBarHeight() {
        return this.el.offsetHeight;
    }

    /**
     * Adds cookie bar height as padding-bottom on body
     * @private
     */
    _setBodyPadding() {
        document.body.style.paddingBottom = `${this._calculateCookieBarHeight()}px`;

        this.$emitter.publish('setBodyPadding');
    }

    /**
     * Removes padding-bottom from body
     * @private
     */
    _removeBodyPadding() {
        document.body.style.paddingBottom = '0';

        this.$emitter.publish('removeBodyPadding');
    }
}
