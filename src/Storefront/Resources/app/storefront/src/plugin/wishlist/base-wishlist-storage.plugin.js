import Plugin from 'src/plugin-system/plugin.class';

/**
 * @package checkout
 */
export default class BaseWishlistStoragePlugin extends Plugin {
    init() {
        this.products = {};
    }

    load() {
        this.$emitter.publish('Wishlist/onProductsLoaded', {
            products: this.products,
        });
    }

    has(productId) {
        return !!this.products[productId];
    }

    add(productId) {
        this.products[productId] = (new Date()).toISOString();

        this.$emitter.publish('Wishlist/onProductAdded', {
            products: this.products,
            productId,
        });
    }

    remove(productId) {
        delete this.products[productId];

        this.$emitter.publish('Wishlist/onProductRemoved', {
            products: this.products,
            productId,
        });
    }

    getCurrentCounter() {
        return this.products ? Object.keys(this.products).length : 0;
    }

    getProducts() {
        return this.products;
    }
}
