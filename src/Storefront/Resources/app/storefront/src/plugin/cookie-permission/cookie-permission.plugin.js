import Plugin from 'src/plugin-system/plugin.class';
import CookieStorage from 'src/helper/storage/cookie-storage.helper';
import Debouncer from 'src/helper/debouncer.helper';
import DeviceDetection from 'src/helper/device-detection.helper';

export default class CookiePermissionPlugin extends Plugin {

    static options = {

        /**
         * cookie expiration time
         */
        cookieExpiration: 1,

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

        if (!this._isCookieAllowed()) {
            this._setBodyPadding();
            this._registerEvents();
        }
    }

    /**
     * Checks if there is already a cookie permission set
     * Hides cookie bar if cookie permission is already set
     * If there is no cookie permission set it initializes the cookie bar
     */
    _isCookieAllowed() {
        const cookiePermission = CookieStorage.getItem('allowCookie', '1');

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
        const submitEvent = (DeviceDetection.isTouchDevice()) ? 'touchstart' : 'click';

        if (this._button) {
            this._button.addEventListener(submitEvent, () => {
                this._hideCookieBar();
                this._removeBodyPadding();
                CookieStorage.setItem('allowCookie', '1', this.options.cookieExpiration);

                this.$emitter.publish('onClickButton');
            });
        }

        window.addEventListener('resize', Debouncer.debounce(this._setBodyPadding.bind(this), this.options.resizeDebounceTime), {
            capture: true,
            passive: true,
        });
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
