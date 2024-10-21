import Plugin from 'src/plugin-system/plugin.class';
import HttpClient from 'src/service/http-client.service';
import ElementLoadingIndicatorUtil from 'src/utility/loading-indicator/element-loading-indicator.util';

/**
 * @package checkout
 */
export default class GuestWishlistPagePlugin extends Plugin {
    init() {
        ElementLoadingIndicatorUtil.create(this.el);

        this.httpClient = new HttpClient();
        this._getWishlistStorage();

        this._loadProductListForGuest();
    }

    /**
     * @private
     */
    _getWishlistStorage() {
        const wishlistBasketElement = document.querySelector('#wishlist-basket');

        if (!wishlistBasketElement) {
            return;
        }

        this._wishlistStorage = window.PluginManager.getPluginInstanceFromElement(wishlistBasketElement, 'WishlistStorage');
        this._wishlistStorage.load();
    }

    /**
     * @private
     */
    _loadProductListForGuest() {
        const productIds = Object.entries(this._wishlistStorage.getProducts())
            .map(([productId, dateTime]) => ({productId, dateTime: new Date(dateTime).getTime()}))
            .sort((a, b) => b.dateTime - a.dateTime)
            .map(item => item.productId);

        this.httpClient.post(this.options.pageletRouter.path, JSON.stringify({
            productIds,
        }), response => {
            this.el.innerHTML = response;
            const forms = this.el.querySelectorAll('form.product-wishlist-form');

            if (!forms || forms.length !== productIds.length) {
                this._cleanInvalidGuestProductIds(productIds, forms);
            }

            if (forms && forms.length > 0) {
                forms.forEach(form => {
                    this._removeGuestProductFormHandler(form);
                });
            }

            ElementLoadingIndicatorUtil.remove(this.el);
            window.PluginManager.initializePlugins();
        });
    }

    /**
     * @private
     */
    _removeGuestProductFormHandler(form) {
        form.addEventListener('submit', event => {
            event.preventDefault();
            const actionUrlParts = form.getAttribute('action').split('/');
            const productId = actionUrlParts[actionUrlParts.length - 1];

            if (productId) {
                const parentEl = form.closest('.cms-listing-col');
                this._wishlistStorage.remove(productId);
                parentEl.remove();

                if (this._wishlistStorage.getCurrentCounter() === 0) {
                    this._loadProductListForGuest();
                }
            }
        });
    }

    /**
     * @private
     */
    _cleanInvalidGuestProductIds(guestProductIds, forms) {
        const validProductIds = [];

        forms.forEach(form => {
            const actionUrlParts = form.getAttribute('action').split('/');
            const productId = actionUrlParts[actionUrlParts.length - 1];

            validProductIds.push(productId);
        });

        guestProductIds.forEach(guestProductId => {
            if (validProductIds.indexOf(guestProductId) === -1) {
                this._wishlistStorage.remove(guestProductId);
            }
        });
    }
}
