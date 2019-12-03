import Plugin from 'src/plugin-system/plugin.class';
import CookieStorage from 'src/helper/storage/cookie-storage.helper';
import Debouncer from 'src/helper/debouncer.helper';
import DeviceDetection from 'src/helper/device-detection.helper';

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
        }
    }

    /**
     * Checks if a cookie preference is already set.
     * If not, the cookie bar is displayed.
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
     */
    _showCookieBar() {
        this.el.style.display = 'block';

        this.$emitter.publish('showCookieBar');
    }

    /**
     * Hides cookie bar
     */
    _hideCookieBar() {
        this.el.style.display = 'none';

        this.$emitter.publish('hideCookieBar');
    }


    /**
     * register all needed events
     *
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
     * Event handler for the cookie bar 'deny' button
     * Sets the 'cookie-preference' cookie to hide the cookie bar
     * @private
     */
    _handleDenyButton() {
        const { cookieExpiration, cookieName } = this.options;
        this._hideCookieBar();
        this._removeBodyPadding();
        CookieStorage.setItem(cookieName, '1', cookieExpiration);

        this.$emitter.publish('onClickDenyButton');
    }

    /**
     * Calculates cookie bar height
     */
    _calculateCookieBarHeight() {
        return this.el.offsetHeight;
    }

    /**
     * Adds cookie bar height as padding-bottom on body
     */
    _setBodyPadding() {
        document.body.style.paddingBottom = this._calculateCookieBarHeight() + 'px';

        this.$emitter.publish('setBodyPadding');
    }

    /**
     * Removes padding-bottom from body
     */
    _removeBodyPadding() {
        document.body.style.paddingBottom = '0';

        this.$emitter.publish('removeBodyPadding');
    }
}
