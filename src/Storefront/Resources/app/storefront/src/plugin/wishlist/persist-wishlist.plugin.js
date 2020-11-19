import HttpClient from 'src/service/http-client.service';
import BaseWishlistStoragePlugin from 'src/plugin/wishlist/base-wishlist-storage.plugin';

export default class WishlistPersistStoragePlugin extends BaseWishlistStoragePlugin {
    init() {
        this.products = {};
        this.httpClient = new HttpClient();

        super.init();
    }

    load() {
        this.httpClient.get(this.options.listPath, response => {
            this.products = JSON.parse(response);

            super.load();
        });
    }

    add(productId, router) {
        this.httpClient.post(router.path, JSON.stringify({
            _csrf_token: router.token
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
            _csrf_token: router.token
        }), response => {
            const res = JSON.parse(response);

            if (res.success) {
                super.remove(productId);

                return;
            }

            throw new Error('Unable to remove product from wishlist');
        });
    }
}
