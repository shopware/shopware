import Plugin from 'src/plugin-system/plugin.class';
import CookieStorage from 'src/helper/storage/cookie-storage.helper';

/**
 * Ensures only a single age verification modal is opened per page view, even when the
 * element is placed on a layout more than once.
 *
 * @type {boolean}
 */
let modalAlreadyOpened = false;

export default class AgeVerificationPlugin extends Plugin {
    static options = {
        /**
         * Name of the cookie that stores the confirmation.
         */
        cookieName: 'age-verified',

        /**
         * Lifetime of the confirmation cookie in days.
         */
        cookieLifetime: 30,

        /**
         * URL the visitor is redirected to when declining. Falls back to the previous page when empty.
         */
        declineUrl: '',

        confirmButtonSelector: '.js-age-verification-confirm',

        declineButtonSelector: '.js-age-verification-decline',
    };

    init() {
        if (this._isConfirmed() || modalAlreadyOpened) {
            return;
        }

        modalAlreadyOpened = true;

        this._modal = new window.bootstrap.Modal(this.el, {
            backdrop: 'static',
            keyboard: false,
        });

        this._registerEvents();
        this._modal.show();
    }

    _isConfirmed() {
        return CookieStorage.getItem(this.options.cookieName) === '1';
    }

    _registerEvents() {
        const confirmButton = this.el.querySelector(this.options.confirmButtonSelector);
        const declineButton = this.el.querySelector(this.options.declineButtonSelector);

        if (confirmButton) {
            confirmButton.addEventListener('click', this._onConfirm.bind(this));
        }

        if (declineButton) {
            declineButton.addEventListener('click', this._onDecline.bind(this));
        }
    }

    _onConfirm() {
        CookieStorage.setItem(this.options.cookieName, '1', this.options.cookieLifetime);
        this._modal.hide();
    }

    _onDecline() {
        if (this.options.declineUrl) {
            this._redirect(this.options.declineUrl);

            return;
        }

        window.history.back();
    }

    /**
     * Thin wrapper so tests can spy on navigation without mocking window.location.
     *
     * @param {string} url
     */
    _redirect(url) {
        window.location.assign(url);
    }
}
