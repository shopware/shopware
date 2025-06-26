import Plugin from 'src/plugin-system/plugin.class';
import CookieStorageHelper from 'src/helper/storage/cookie-storage.helper';
import AjaxOffCanvas from 'src/plugin/offcanvas/ajax-offcanvas.plugin';

/**
 * @package checkout
 */
export default class WishlistCookieOffcanvasPlugin extends Plugin {
    static options = {
        cookieName: 'wishlist-enabled',
        cookieLifetime: 30,
        acceptBtnSelector: '.js-wishlist-cookie-accept',
        loginBtnSelector: '.js-wishlist-login',
        prefBtnSelector: '.js-wishlist-cookie-preferences',
        cancelBtnSelector: '.js-wishlist-cookie-offcanvas-cancel',
    };

    static lastTriggerElement = null;

    init() {
        this._registerEvents();
    }

    /**
     * @returns {boolean}
     */
    static hasConsent() {
        if (!CookieStorageHelper.isSupported()) {
            return false;
        }
        return CookieStorageHelper.getItem(this.options.cookieName) === '1';
    }

    /**
     * @param {string|number} productId
     * @param {Function} onConsent
     */
    static requestConsent(productId, onConsent) {
        const url = window.router['frontend.wishlist.cookie.offcanvas'];

        WishlistCookieOffcanvasPlugin.lastTriggerElement = document.activeElement;
        AjaxOffCanvas.open(url, false, () => {
            window.PluginManager.initializePlugins();

            const offcanvas = document.querySelector('.offcanvas');
            if (!offcanvas) {
                return;
            }

            const plugin = window.PluginManager
                .getPluginInstanceFromElement(offcanvas, 'WishlistCookieOffcanvas');

            if (plugin) {
                plugin.options.productId = productId;
                plugin.$emitter.subscribe('WishlistCookie/onAccept', onConsent);
            }
        }, 'left');
    }

    /**
     * @private
     */
    _registerEvents() {
        const acceptBtn = this.el.querySelector(this.options.acceptBtnSelector);
        if (acceptBtn) {
            acceptBtn.addEventListener('click', this._onAccept.bind(this));
        }

        const loginBtn = this.el.querySelector(this.options.loginBtnSelector);
        if (loginBtn) {
            loginBtn.addEventListener('click', this._onLogin.bind(this));
        }

        const prefBtn = this.el.querySelector(this.options.prefBtnSelector);
        if (prefBtn) {
            prefBtn.addEventListener('click', this._onPreferences.bind(this));
        }

        const cancelBtn = this.el.querySelector(this.options.cancelBtnSelector);
        if (cancelBtn) {
            cancelBtn.addEventListener('click', this._onCancel.bind(this));
        }
    }

    /**
     * @private
     */
    _onAccept() {
        CookieStorageHelper.setItem(
            this.options.cookieName,
            '1',
            this.options.cookieLifetime
        );
        this._closeOffcanvas();
        this.$emitter.publish('WishlistCookie/onAccept', { productId: this.options.productId });
    }

    /**
     * @private
     */
    _onLogin() {
        this._closeOffcanvas();
        window.location.href = window.router['frontend.account.login.page'];
        this.$emitter.publish('Wishlist/onLoginRedirect');
    }

    /**
     * @private
     */
    _onPreferences(event) {
        event.preventDefault();
        this._closeOffcanvas();

        const configurator = window.PluginManager.getPluginInstances('CookieConfiguration')[0];
        if (!configurator) {
            return;
        }

        configurator.openOffCanvas(() => {
            const offcanvasElement = document.querySelector('.offcanvas');
            if (!offcanvasElement) {
                return;
            }

            offcanvasElement.addEventListener('hidden.bs.offcanvas',
                this._restoreFocus.bind(this),
                { once: true }
            );
        });
    }

    /**
     * @private
     */
    _onCancel() {
        this._closeOffcanvas();
    }

    _closeOffcanvas() {
        AjaxOffCanvas.close();

        // remove all offcanvas-backdrop nodes
        document.querySelectorAll('.offcanvas-backdrop').forEach(el => el.remove());
    }

    _restoreFocus() {
        const btn = WishlistCookieOffcanvasPlugin.lastTriggerElement;
        if (btn && btn.focus) {
            btn.focus();
        }
    }
}
