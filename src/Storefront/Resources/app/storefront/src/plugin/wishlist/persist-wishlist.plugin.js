import HttpClient from 'src/service/http-client.service';
import BaseWishlistStoragePlugin from 'src/plugin/wishlist/base-wishlist-storage.plugin';
import Storage from 'src/helper/storage/storage.helper';
import DomAccessHelper from 'src/helper/dom-access.helper';

export default class WishlistPersistStoragePlugin extends BaseWishlistStoragePlugin {
    init() {
        super.init();
        this.httpClient = new HttpClient();
    }

    load() {
        this._merge(() => {
            this.httpClient.get(this.options.listPath, response => {
                this.products = JSON.parse(response);

                super.load();
            });
        });
    }

    add(productId, router) {
        this.httpClient.post(router.path, JSON.stringify({
            _csrf_token: router.token,
        }), response => {
            const res = JSON.parse(response);

            if (res.success) {
                super.add(productId);

                return;
            }

            throw new Error('Unable to add product to wishlist');
        });
    }

    remove(productId, router) {
        this.httpClient.post(router.path, JSON.stringify({
            _csrf_token: router.token,
        }), response => {
            const res = JSON.parse(response);

            if (res.success) {
                super.remove(productId);

                return;
            }

            throw new Error('Unable to remove product from wishlist');
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
            this.httpClient.post(this.options.mergePath, JSON.stringify({
                _csrf_token: this.options.tokenMergePath,
                'productIds' : Object.keys(products),
            }), response => {
                if (!response) {
                    throw new Error('Unable to merge product wishlist from anonymous user');
                }

                this.$emitter.publish('Wishlist/onProductMerged', {
                    products: products,
                });

                this.storage.removeItem(key);
                this._block = DomAccessHelper.querySelector(document, '.flashbags');
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
        this.httpClient.post(this.options.pageletPath, JSON.stringify({
            _csrf_token: this.options.tokenPageletPath,
        }), response => {
            if (!response) {
                return;
            }

            this._block = DomAccessHelper.querySelector(document, '.cms-listing-row');
            this._block.innerHTML = response;
        });
    }
}
