import Plugin from 'src/plugin-system/plugin.class';
import DomAccess from 'src/helper/dom-access.helper';

/**
 * @package checkout
 */
export default class AddToWishlistPlugin extends Plugin {
    static options = {
        texts: {
            add: 'Add to wishlist',
            remove: 'Remove from wishlist',
        },
    }

    init() {
        this.classList = {
            isLoading: 'product-wishlist-loading',
            addedState: 'product-wishlist-added',
            notAddedState: 'product-wishlist-not-added',
        };
        this.textsElement = DomAccess.querySelector(this.el, '.product-wishlist-btn-content', false);

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

        this._wishlistStorage.$emitter.subscribe('Wishlist/onLoginRedirect', this.initStateClasses.bind(this));
    }

    initStateClasses() {
        if (this._wishlistStorage.has(this.options.productId)) {
            this._addActiveState();
        } else {
            this._removeActiveState();
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
        } else {
            this._wishlistStorage.add(this.options.productId, this.options.router.add);
        }
    }

    /**
     * @private
     */
    _addActiveState() {
        this.el.classList.remove(this.classList.notAddedState);
        this.el.classList.add(this.classList.addedState);

        this.el.setAttribute('title', this.options.texts.remove);

        if (this.textsElement) {
            this.textsElement.innerHTML = this.options.texts.remove;
        }
    }

    /**
     * @private
     */
    _removeActiveState() {
        this.el.classList.remove(this.classList.addedState);
        this.el.classList.add(this.classList.notAddedState);

        this.el.setAttribute('title', this.options.texts.add);

        if (this.textsElement) {
            this.textsElement.innerHTML = this.options.texts.add;
        }
    }
}
