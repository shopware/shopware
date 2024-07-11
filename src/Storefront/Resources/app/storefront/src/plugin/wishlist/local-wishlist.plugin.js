import Storage from 'src/helper/storage/storage.helper';
import BaseWishlistStoragePlugin from 'src/plugin/wishlist/base-wishlist-storage.plugin';
import CookieStorageHelper from 'src/helper/storage/cookie-storage.helper';

/**
 * @package checkout
 */
export default class WishlistLocalStoragePlugin extends BaseWishlistStoragePlugin {
    init() {
        this.cookieEnabledName = 'wishlist-enabled';

        this.storage = Storage;

        super.init();
        this._registerEvents();
    }

    load() {
        this.products = this._fetch();

        super.load();
    }

    add(productId, router) {
        if (window.useDefaultCookieConsent && !CookieStorageHelper.getItem(this.cookieEnabledName)) {
            window.location.href = router.afterLoginPath;

            this.$emitter.publish('Wishlist/onLoginRedirect');

            return;
        }

        super.add(productId);

        this._save();
    }

    remove(productId) {
        super.remove(productId);

        this._save();
    }

    /**
     * @private
     */
    _fetch() {
        if (window.useDefaultCookieConsent && !CookieStorageHelper.getItem(this.cookieEnabledName)) {
            this.storage.removeItem(this._getStorageKey());
        }

        if (this.getCurrentCounter() > 0) {
            return this.products;
        }

        const productStr = this.storage.getItem(this._getStorageKey());

        if (!productStr) {
            return {};
        }

        try {
            const products = JSON.parse(productStr);

            return products instanceof Object ? products : {};
        } catch {
            return {};
        }
    }

    /**
     * @private
     */
    _save() {
        if(this.products === null || this.getCurrentCounter() === 0) {
            this.storage.removeItem(this._getStorageKey());
        } else {
            this.storage.setItem(this._getStorageKey(), JSON.stringify(this.products));
        }
    }

    /**
     * @private
     */
    _getStorageKey() {
        return 'wishlist-' + (window.salesChannelId || '');
    }

    _registerEvents() {
        const guestLogoutPlugins = window.PluginManager.getPluginInstances('AccountGuestAbortButton');

        if (guestLogoutPlugins) {
            guestLogoutPlugins.forEach(guestLogoutButtonPlugin => {
                guestLogoutButtonPlugin.$emitter.subscribe('guest-logout', () => {
                    this.storage.removeItem(this._getStorageKey());
                });
            })
        }
    }
}
