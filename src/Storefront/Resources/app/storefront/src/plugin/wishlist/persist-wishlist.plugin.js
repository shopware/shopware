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
        this._merge(() => this._loadProducts())
            .catch(error => this._handleRequestError(error));
    }

    add(productId, router) {
        this._fetchJson(router.path, {
            method: 'POST',
        })
            .then(response => {
                if (response === null) {
                    return;
                }

                if (response.success) {
                    super.add(productId);

                    return;
                }

                console.warn('unable to add product to wishlist');
            })
            .catch(error => this._handleRequestError(error));
    }

    remove(productId, router) {
        this._fetchJson(router.path, {
            method: 'POST',
        })
            .then(response => {
                if (response === null) {
                    return;
                }

                if (Object.prototype.hasOwnProperty.call(response, 'success')) {
                    if (response.success === false) {
                        console.warn('unable to remove product to wishlist');
                    }
                    super.remove(productId);
                }
            })
            .catch(error => this._handleRequestError(error));
    }

    /**
     * @private
     */
    async _merge(callback) {
        this.storage = Storage;
        const key = 'wishlist-' + (window.salesChannelId || '');

        const productStr = this.storage.getItem(key);

        const products = JSON.parse(productStr);

        let mergePromise = Promise.resolve();

        if (products) {
            mergePromise = this._mergeProducts(products, key, callback);
        }

        await callback();
        await mergePromise;
    }

    /**
     * @private
     */
    async _pagelet() {
        try {
            const response = await this._fetchText(this.options.pageletPath, {
                method: 'POST',
            });

            if (response === null || response === '') {
                return;
            }

            this._block = document.querySelector('.cms-listing-row');
            this._block.innerHTML = response;
        } catch (error) {
            this._handleRequestError(error);
        }
    }

    /**
     * @private
     */
    async _loadProducts() {
        try {
            const response = await this._fetchJson(this.options.listPath);

            if (response === null) {
                return;
            }

            this.products = response;

            super.load();
        } catch (error) {
            this._handleRequestError(error);
        }
    }

    /**
     * @private
     */
    async _mergeProducts(products, key, callback) {
        try {
            const response = await this._fetchText(this.options.mergePath, {
                method: 'POST',
                body: JSON.stringify({ productIds: Object.keys(products) }),
            });

            if (response === null) {
                return;
            }

            if (response === '') {
                throw new Error('Unable to merge product wishlist from anonymous user');
            }

            this.$emitter.publish('Wishlist/onProductMerged', {
                products: products,
            });

            this.storage.removeItem(key);
            this._block = document.querySelector('.flashbags');
            this._block.innerHTML = response;
            await this._pagelet();
            await callback();
        } catch (error) {
            this._handleRequestError(error);
        }
    }

    /**
     * @private
     */
    async _fetchJson(path, options = {}) {
        const response = await fetch(path, this._createRequestOptions(options));

        if (!response.ok) {
            return null;
        }

        return response.json();
    }

    /**
     * @private
     */
    async _fetchText(path, options = {}) {
        const response = await fetch(path, this._createRequestOptions(options));

        if (!response.ok) {
            return null;
        }

        return response.text();
    }

    /**
     * @private
     */
    _createRequestOptions(options = {}) {
        return {
            ...options,
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Content-Type': 'application/json',
                ...options.headers,
            },
        };
    }

    /**
     * @private
     */
    _handleRequestError(error) {
        console.warn(error);
    }
}
