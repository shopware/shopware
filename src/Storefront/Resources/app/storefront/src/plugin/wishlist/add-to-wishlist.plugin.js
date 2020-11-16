import Plugin from 'src/plugin-system/plugin.class';

export default class AddToWishlistPlugin extends Plugin {
    init() {
        this.classList = {
            isLoading: 'product-wishlist-loading',
            addedState: 'product-wishlist-added',
            notAddedState: 'product-wishlist-not-added'
        };

        this._getWishlistStorage();

        if (!this._wishlistStorage) {
            throw new Error('No wishlist storage found');
        }

        this._registerEvents();
    }

    /**
     * @returns WishlistWidgetPlugin
     * @private
     */
    _getWishlistStorage() {
        const wishlistBasketElement = document.querySelector('#wishlist-basket');

        if (!wishlistBasketElement) {
            this.el.style.display = 'none';

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
