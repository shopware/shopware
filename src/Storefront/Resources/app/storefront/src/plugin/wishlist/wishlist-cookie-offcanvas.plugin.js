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
    };

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
        const url = window.router['frontend.wishlist.cookie.offcanvas'] || '/wishlist/cookie-offcanvas';

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
        const acceptBtn = this.el.querySelector('.js-wishlist-cookie-accept');
        if (acceptBtn) {
            acceptBtn.addEventListener('click', this._onAccept.bind(this));
        }

        const loginBtn = this.el.querySelector('.js-wishlist-login');
        if (loginBtn) {
            loginBtn.addEventListener('click', this._onLogin.bind(this));
        }

        const prefBtn = this.el.querySelector('.js-wishlist-cookie-preferences');
        if (prefBtn) {
            prefBtn.addEventListener('click', this._onPreferences.bind(this));
        }

        const cancelBtn = this.el.querySelector('.js-wishlist-cookie-offcanvas-cancel');
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
        window.location.href = window.router['frontend.account.login.page'] || '/account/login';
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

        const handler = updated => {
            if (updated[this.options.cookieName]) {
                this.$emitter.publish('WishlistCookie/onAccept');
                document.$emitter.unsubscribe('CookieConfiguration_Update', handler);
            }
        };

        configurator.openOffCanvas(() => {
            document.$emitter.subscribe('CookieConfiguration_Update', handler);
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
}
