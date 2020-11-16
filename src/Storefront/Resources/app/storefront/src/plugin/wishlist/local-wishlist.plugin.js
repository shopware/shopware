import Storage from 'src/helper/storage/storage.helper';
import BaseWishlistStoragePlugin from 'src/plugin/wishlist/base-wishlist-storage.plugin';

export default class WishlistLocalStoragePlugin extends BaseWishlistStoragePlugin {
    init() {
        this.storage = Storage;
        this.key = 'wishlist-products';

        super.init();
    }

    load() {
        this.products = this._fetch();

        super.load();
    }

    add(productId) {
        super.add(productId);

        this._save();
    }

    remove(productId) {
        super.remove(productId);

        this._save();
    }

    /**
     * @private
     */
    _fetch() {
        if (this.getCurrentCounter() > 0) {
            return this.products;
        }

        const productStr = this.storage.getItem(this.key);

        if (!productStr) {
            return {};
        }

        try {
            const products = JSON.parse(productStr);

            return products instanceof Object ? products : {};
        } catch {
            return {};
        }
    }

    /**
     * @private
     */
    _save() {
        if(this.products === null || this.getCurrentCounter() === 0) {
            this.storage.removeItem(this.key);
        } else {
            this.storage.setItem(this.key, JSON.stringify(this.products));
        }
    }
}
