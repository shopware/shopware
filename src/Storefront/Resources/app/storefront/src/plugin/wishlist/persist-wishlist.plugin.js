import BaseWishlistStoragePlugin from 'src/plugin/wishlist/base-wishlist-storage.plugin';
import Storage from 'src/helper/storage/storage.helper';
import DomAccessHelper from 'src/helper/dom-access.helper';
import HttpClient from 'src/service/http-client.service';

/**
 * @package checkout
 */
export default class WishlistPersistStoragePlugin extends BaseWishlistStoragePlugin {
    init() {
        super.init();
        this.httpClient = new HttpClient();
        this.httpClient.setErrorHandlingInternal(true);
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
        this.httpClient.post(router.path, null, response => {
            const res = JSON.parse(response);

            if (res.success) {
                super.add(productId);

                return;
            }

            console.warn('unable to add product to wishlist');
        });
    }

    remove(productId, router) {
        this.httpClient.post(router.path, null, response => {
            const res = JSON.parse(response);
            // even if the call returns false, the item should be removed from storage because it may be already deleted
            if (Object.prototype.hasOwnProperty.call(res, 'success')) {
                if (res.success === false) {
                    console.warn('unable to remove product to wishlist');
                }
                super.remove(productId);

                return;
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
            this.httpClient.post(this.options.mergePath, JSON.stringify({
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
        this.httpClient.post(this.options.pageletPath, '', response => {
            if (!response) {
                return;
            }

            this._block = DomAccessHelper.querySelector(document, '.cms-listing-row');
            this._block.innerHTML = response;
        });
    }
}
