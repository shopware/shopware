import Plugin from 'asset/script/helper/plugin/plugin.class'
import CookieHandler from 'asset/script/helper/cookie.helper';
import Debouncer from 'asset/script/helper/debouncer.helper';
import DeviceDetection from 'asset/script/helper/device-detection.helper';

const EXPIRATION = 1;
const RESIZE_DEBOUNCE_TIME = 200;

const BUTTON_SELECTOR = '.js-cookie-permission-button';

export default class CookiePermissionPlugin extends Plugin {

    init() {
        this._button = this.el.querySelector(BUTTON_SELECTOR);

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
        const cookiePermission = CookieHandler.hasCookie('allowCookie', '1');

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
    }

    /**
     * Hides cookie bar
     */
    _hideCookieBar() {
        this.el.style.display = 'none';
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
                CookieHandler.setCookie('allowCookie', '1', EXPIRATION);
            });
        }

        window.addEventListener('resize', Debouncer.debounce(this._setBodyPadding.bind(this), RESIZE_DEBOUNCE_TIME, false), {
            capture: true,
            passive: true
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
    }

    /**
     * Removes padding-bottom from body
     */
    _removeBodyPadding() {
        document.body.style.paddingBottom = '0';
    }
}
