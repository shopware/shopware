import Plugin from 'src/plugin-system/plugin.class';
import DomAccess from 'src/helper/dom-access.helper';

export default class WishlistWidgetPlugin extends Plugin {

    static options = {
        showCounter: true,
    };

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
        if (!this.options.showCounter) {
            return;
        }

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

            this._reInitWishlistButton(event.detail.productId);
        });

        this.$emitter.subscribe('Wishlist/onProductAdded', (event) => {
            this._renderCounter();

            this._reInitWishlistButton(event.detail.productId);
        });

        const listingEl = DomAccess.querySelector(document, '.cms-element-product-listing-wrapper', false);

        if (listingEl) {
            const listingPlugin = window.PluginManager.getPluginInstanceFromElement(listingEl, 'Listing');

            listingPlugin.$emitter.subscribe('Listing/afterRenderResponse', () => {
                window.PluginManager.getPluginInstances('AddToWishlist').forEach((pluginInstance) => {
                    pluginInstance.initStateClasses();
                });
            });
        }
    }

    /**
     * @private
     */
    _reInitWishlistButton(productId) {
        const buttonElements = DomAccess.querySelectorAll(document, '.product-wishlist-' + productId, false);

        if (!buttonElements) {
            return;
        }

        buttonElements.forEach((el) => {
            const plugin = window.PluginManager.getPluginInstanceFromElement(el, 'AddToWishlist');
            plugin.initStateClasses();
        });
    }
}
