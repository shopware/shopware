import BaseWishlistStoragePlugin from 'src/plugin/wishlist/base-wishlist-storage.plugin';
import Storage from 'src/helper/storage/storage.helper';
/** @deprecated tag:v6.8.0 - HttpClient is deprecated. Use native fetch API instead. */
import HttpClient from 'src/service/http-client.service';

/**
 * @package checkout
 */
export default class WishlistPersistStoragePlugin extends BaseWishlistStoragePlugin {
    init() {
        super.init();
        /** @deprecated tag:v6.8.0 - HttpClient is deprecated. Use native fetch API instead. */
        this.httpClient = new HttpClient();
        this.httpClient.setErrorHandlingInternal(true);
    }

    load() {
        this._merge(() => {
            fetch(this.options.listPath, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Content-Type': 'application/json',
                },
            })
                .then(response => response.json())
                .then(response => {
                    this.products = response;

                    super.load();
                });
        });
    }

    add(productId, router) {
        fetch(router.path, {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Content-Type': 'application/json',
            },
        })
            .then(response => response.json())
            .then(response => {
                if (response.success) {
                    super.add(productId);

                    return;
                }

                console.warn('unable to add product to wishlist');
            });
    }

    remove(productId, router) {
        fetch(router.path, {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Content-Type': 'application/json',
            },
        })
            .then(response => response.json())
            .then(response => {
                if (Object.prototype.hasOwnProperty.call(response, 'success')) {
                    if (response.success === false) {
                        console.warn('unable to remove product to wishlist');
                    }
                    super.remove(productId);
                }
            });
    }

    /**
     * @private
     */
    _merge(callback) {
        this.storage = Storage;
        const key = 'wishlist-' + (window.salesChannelId || '');

        const productStr = this.storage.getItem(key);

        const products = JSON.parse(productStr);

        if (products) {
            fetch(this.options.mergePath, {
                method: 'POST',
                body: JSON.stringify({ 'productIds': Object.keys(products) }),
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Content-Type': 'application/json',
                },
            })
                .then(response => response.text())
                .then(response => {
                    if (!response) {
                        throw new Error('Unable to merge product wishlist from anonymous user');
                    }

                    this.$emitter.publish('Wishlist/onProductMerged', {
                        products: products,
                    });

                    this.storage.removeItem(key);
                    this._block = document.querySelector('.flashbags');
                    this._block.innerHTML = response;
                    this._pagelet();
                    callback();
                });
        }
        callback();
    }

    /**
     * @private
     */
    _pagelet() {
        fetch(this.options.pageletPath, {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Content-Type': 'application/json',
            },
        })
            .then(response => response.text())
            .then(response => {
                if (!response) {
                    return;
                }

                this._block = document.querySelector('.cms-listing-row');
                this._block.innerHTML = response;
            });
    }
}
