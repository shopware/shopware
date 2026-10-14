import Storage from 'src/helper/storage/storage.helper';

const STORAGE_KEY = 'swGaProductCategories';

// A cart lives for days, so the categories of a product added today are still needed by a
// checkout that follows later. The order removes them.
const TTL = 30 * 24 * 60 * 60 * 1000;

const LIMIT = 100;

/**
 * Keeps the categories requested for a product, so the cart and checkout events report them.
 *
 * A product added on a page whose breadcrumb does not describe it, such as the homepage, the
 * search or a product slider, has no categories in its line item payload. Its categories are only
 * requested when it is added. The cart and checkout must not load anything for analytics, so they
 * fill the missing categories of a line item from this storage instead. The shared `Storage`
 * helper prefers `localStorage`, so every tab of the shopper finds them.
 */
export default class ProductCategoryHelper
{
    /**
     * @param {string} productId
     * @param {Object} categories the GA4 category properties
     */
    static remember(productId, categories) {
        if (!productId || !categories || Object.keys(categories).length === 0) {
            return;
        }

        const entries = ProductCategoryHelper._read()
            .filter(entry => entry.productId !== productId)
            .slice(-(LIMIT - 1));

        entries.push({ productId, categories, expires: Date.now() + TTL });

        ProductCategoryHelper._write(entries);
    }

    /**
     * @param {string|null} productId
     * @returns {Object} the GA4 category properties, empty when none are known
     */
    static get(productId) {
        if (!productId) {
            return {};
        }

        return ProductCategoryHelper._read().find(entry => entry.productId === productId)?.categories ?? {};
    }

    /**
     * @param {string[]} productIds
     */
    static forget(productIds) {
        const entries = ProductCategoryHelper._read();
        const remaining = entries.filter(entry => !productIds.includes(entry.productId));

        if (remaining.length !== entries.length) {
            ProductCategoryHelper._write(remaining);
        }
    }

    /**
     * The entries that have not expired yet. Missing categories only mean an item is reported
     * without them, so reading never fails.
     *
     * @returns {Object[]}
     * @private
     */
    static _read() {
        try {
            const stored = JSON.parse(Storage.getItem(STORAGE_KEY));
            const now = Date.now();

            return Array.isArray(stored)
                ? stored.filter(entry => entry?.productId && entry.categories && entry.expires > now)
                : [];
        } catch {
            return [];
        }
    }

    /**
     * @param {Object[]} entries
     * @private
     */
    static _write(entries) {
        try {
            if (entries.length === 0) {
                Storage.removeItem(STORAGE_KEY);

                return;
            }

            Storage.setItem(STORAGE_KEY, JSON.stringify(entries));
        } catch {
            // the storage is full or disabled
        }
    }
}
