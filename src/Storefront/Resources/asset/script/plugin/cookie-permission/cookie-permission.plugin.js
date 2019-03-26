import CookieHandler from '../../helper/cookie.helper';
import Debouncer from '../../helper/debouncer.helper';
import Plugin from '../../helper/plugin.helper';
import DeviceDetection from '../../helper/device-detection.helper';

const BUTTON_ID = 'cookieButton';
const CONTAINER_ID = 'cookieContainer';
const EXPIRATION = 1;
const RESIZE_DEBOUNCE_TIME = 200;

export default class CookiePermission extends Plugin {

    /**
     *  Constructor
     */
    constructor() {
        super();

        this._checkCookie();
    }

    /**
     * Checks if there is already a cookie permission set
     * Hides cookie bar if cookie permission is already set
     * If there is no cookie permission set it initializes the cookie bar
     */
    _checkCookie() {
        let cookiePermission = CookieHandler.hasCookie('allowCookie', '1');

        if (cookiePermission) {
            this._hideCookieBar();
            return;
        }
        this._setBodyPadding();
        this._registerResizeEvent();
        this._registerHideEvent();
    }

    /**
     * Hides cookie bar by setting display to none
     */
    _hideCookieBar() {
        document.getElementById(CONTAINER_ID).style.display = 'none';
    }

    /**
     * Hides cookie bar if the user clicks the accept cookie button
     */
    _registerHideEvent() {
        let event = (DeviceDetection.isTouchDevice()) ? 'touchstart' : 'click';

        document.getElementById(BUTTON_ID).addEventListener(event, () => {
            this._hideCookieBar();
            this._removeBodyPadding();
            CookieHandler.setCookie('allowCookie', '1', EXPIRATION);
        });
    }

    /**
     * Calculates cookie bar height
     */
    _calculateCookieBarHeight() {
        return document.getElementById(CONTAINER_ID).offsetHeight;
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

    /**
     * Recalculates body padding-bottom on browser resize
     */
    _registerResizeEvent() {
        window.addEventListener(
            'resize',
            Debouncer.debounce(this._setBodyPadding.bind(this), RESIZE_DEBOUNCE_TIME, false),
            {
                capture: true,
                passive: true
            }
        );
    }
}
