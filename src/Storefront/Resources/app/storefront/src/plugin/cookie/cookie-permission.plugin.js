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
    };

    init() {
        this._button = this.el.querySelector(this.options.buttonSelector);

        if (!this._isPreferenceSet()) {
            this._setBodyPadding();
            this._registerEvents();
            this._setFocusTrap();
        }

        this._registerShowAndHideCookieBarEvents();
    }

    /**
     * Sets a focus trap for the cookie bar
     * @private
     * @returns {void}
     */
    _setFocusTrap() {
        window.focusHandler.setFocus(this.el, { preventScroll: true });

        this._onHandleTabKey = this._handleTabKey.bind(this);
        this.el.addEventListener('keydown', this._onHandleTabKey);
    }

    /**
     * Removes the focus trap for the cookie bar
     * @private
     * @returns {void}
     */
    _removeFocusTrap() {
        if (!this._onHandleTabKey) {
            return;
        }

        this.el.removeEventListener('keydown', this._onHandleTabKey);
    }

    /**
     * Handles the tab key for the cookie bar
     * @private
     * @param {KeyboardEvent} event
     * @returns {void}
     */
    _handleTabKey(event) {
        const focusable = window.focusHandler.getFocusableElements(this.el);
        const first = focusable[0];
        const last = focusable[focusable.length - 1];

        if (event.key === 'Tab') {
            if (event.shiftKey && document.activeElement === first) {
                event.preventDefault(); last.focus();
            } else if (!event.shiftKey && document.activeElement === last) {
                event.preventDefault(); first.focus();
            }
        }
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
        this._setFocusTrap();
    }

    /**
     * Event handler for custom hideCookieBar event
     * Hides the cookie bar when triggered by other plugins
     * @private
     */
    _handleHideCookieBarEvent() {
        this._removeBodyPadding();
        this._hideCookieBar();
        this._removeFocusTrap();
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
        this._removeFocusTrap();
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
