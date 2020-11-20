import Plugin from 'src/plugin-system/plugin.class';

export default class WishlistWidgetPlugin extends Plugin {
    init() {
        this._getWishlistStorage();

        if (!this._wishlistStorage) {
            throw new Error('No wishlist storage found');
        }

        this._renderCounter();
        this._registerEvents();

        this._wishlistStorage.load();
    }

    /**
     * @returns WishlistWidgetPlugin
     * @private
     */
    _getWishlistStorage() {
        this._wishlistStorage = window.PluginManager.getPluginInstanceFromElement(this.el, 'WishlistStorage');
    }

    /**
     * @private
     */
    _renderCounter() {
        this.el.innerHTML = this._wishlistStorage.getCurrentCounter() || '';
    }

    /**
     * @private
     */
    _registerEvents() {
        this.$emitter.subscribe('Wishlist/onProductsLoaded', () => {
            this._renderCounter();

            window.PluginManager.getPluginInstances('AddToWishlist').forEach((pluginInstance) => {
                pluginInstance.initStateClasses();
            });
        });

        this.$emitter.subscribe('Wishlist/onProductRemoved', (event) => {
            this._renderCounter();

            this._stopWishlistLoading(event.detail.productId);
        });

        this.$emitter.subscribe('Wishlist/onProductAdded', (event) => {
            this._renderCounter();

            this._stopWishlistLoading(event.detail.productId);
        });
    }

    /**
     * @private
     */
    _stopWishlistLoading(productId) {
        const buttonEl = document.querySelector('.product-wishlist-' + productId);

        if (buttonEl) {
            buttonEl.classList.remove('product-wishlist-loading');
        }
    }
}
