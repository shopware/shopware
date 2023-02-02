import Plugin from 'src/plugin-system/plugin.class';
import DomAccess from 'src/helper/dom-access.helper';

/**
 * @package checkout
 */
export default class AddToWishlistPlugin extends Plugin {
    init() {
        this.classList = {
            isLoading: 'product-wishlist-loading',
            addedState: 'product-wishlist-added',
            notAddedState: 'product-wishlist-not-added',
        };

        this._getWishlistStorage();

        if (!this._wishlistStorage) {
            this.el.style.display = 'none';
            console.warn('No wishlist storage found');
        }

        this._registerEvents();
        this.initStateClasses();
    }

    /**
     * @returns WishlistWidgetPlugin
     * @private
     */
    _getWishlistStorage() {
        const wishlistBasketElement = DomAccess.querySelector(document, '#wishlist-basket', false);

        if (!wishlistBasketElement) {
            return;
        }

        this._wishlistStorage = window.PluginManager.getPluginInstanceFromElement(wishlistBasketElement, 'WishlistStorage');
    }

    /**
     * @private
     */
    _registerEvents() {
        this.el.addEventListener('click', this._onClick.bind(this));
    }

    initStateClasses() {
        if (this._wishlistStorage.has(this.options.productId)) {
            this._addActiveStateClasses();
        } else {
            this._removeActiveStateClasses();
        }

        this.el.classList.remove(this.classList.isLoading);
    }

    /**
     * @private
     */
    _onClick(event) {
        event.preventDefault();

        if (this.el.classList.contains(this.classList.isLoading)) {
            return;
        }

        this.el.classList.add(this.classList.isLoading);

        if (this._wishlistStorage.has(this.options.productId)) {
            this._wishlistStorage.remove(this.options.productId, this.options.router.remove);

            this._removeActiveStateClasses();
        } else {
            this._wishlistStorage.add(this.options.productId, this.options.router.add);

            this._addActiveStateClasses();
        }
    }

    /**
     * @private
     */
    _addActiveStateClasses() {
        this.el.classList.remove(this.classList.notAddedState);
        this.el.classList.add(this.classList.addedState);
    }

    /**
     * @private
     */
    _removeActiveStateClasses() {
        this.el.classList.remove(this.classList.addedState);
        this.el.classList.add(this.classList.notAddedState);
    }
}
