const STORAGE_KEY = 'swGaSelectedItemList';

let memoryAttribution = null;
let storageSupported = null;

/**
 * Mirrors the support probe of `src/helper/storage/storage.helper.js`. Accessing
 * `sessionStorage` throws in private browsing modes and when storage is disabled.
 *
 * @returns {boolean}
 */
function isStorageSupported() {
    if (storageSupported !== null) {
        return storageSupported;
    }

    try {
        const testKey = `${STORAGE_KEY}__test`;
        window.sessionStorage.setItem(testKey, '1');
        window.sessionStorage.removeItem(testKey);
        storageSupported = true;
    } catch (e) {
        storageSupported = false;
    }

    return storageSupported;
}

/**
 * Carries the list a product was selected from to the product detail page.
 *
 * GA4 attributes a product to the list it was presented in, so `view_item` has to report the same
 * `item_list_id` and `item_list_name` as the `select_item` that led to it. The two events happen on
 * different pages, so the attribution is stored for the session and consumed once.
 *
 * Session storage is used deliberately: the shared `Storage` helper prefers `localStorage`, which
 * would keep an attribution alive across browser sessions and attribute unrelated later visits.
 */
export default class ListAttributionHelper
{
    /**
     * Reads the list a product box belongs to, if the page identified one.
     *
     * @param {HTMLElement|null} element any element inside the list
     * @returns {{item_list_id: string|undefined, item_list_name: string|undefined}}
     */
    static getListFromElement(element) {
        const list = element?.closest('[data-list-id]');

        if (!list) {
            return {};
        }

        return {
            item_list_id: list.getAttribute('data-list-id') || undefined,
            item_list_name: list.getAttribute('data-list-name') || undefined,
        };
    }

    /**
     * The position of the first product the list rendered. A paginated listing renders one page of
     * a longer list, so an index counted from zero within the page would restart on every page.
     *
     * @param {HTMLElement|null} list
     * @returns {number}
     */
    static getListStart(list) {
        // the listing carries it below the list element, inside the markup AJAX pagination swaps
        const element = list?.hasAttribute('data-list-start') ? list : list?.querySelector('[data-list-start]');
        const start = Number(element?.getAttribute('data-list-start'));

        return Number.isInteger(start) && start > 0 ? start : 0;
    }

    /**
     * @param {string} itemId the reported product number
     * @param {Object} list
     * @param {string|undefined} productId the id of the product the card shows
     */
    static remember(itemId, list, productId = undefined) {
        if (!itemId || !list?.item_list_id) {
            return;
        }

        ListAttributionHelper._write({ itemId, productId, list });
    }

    /**
     * Returns the stored attribution for a product and forgets it, so a later direct visit of the
     * same product is not attributed to a list again.
     *
     * A listing that displays the parent of a variant product stores the parent, while its detail
     * page resolves to a variant with another product number. The detail page therefore also
     * matches by its own id and its parent id.
     *
     * @param {string|undefined} itemId the product number the detail page reports
     * @param {string[]} productIds the id and the parent id of the product on the detail page
     * @returns {Object}
     */
    static consume(itemId, productIds = []) {
        const stored = ListAttributionHelper._read();

        if (!stored) {
            return {};
        }

        const matchesItem = !!itemId && stored.itemId === itemId;
        const matchesProduct = !!stored.productId && productIds.includes(stored.productId);

        if (!matchesItem && !matchesProduct) {
            return {};
        }

        ListAttributionHelper.reset();

        return stored.list;
    }

    static reset() {
        ListAttributionHelper._write(null);
    }

    /**
     * @returns {Object|null}
     * @private
     */
    static _read() {
        if (!isStorageSupported()) {
            return memoryAttribution;
        }

        try {
            const stored = JSON.parse(window.sessionStorage.getItem(STORAGE_KEY));

            return stored?.itemId ? stored : null;
        } catch (e) {
            return null;
        }
    }

    /**
     * @param {Object|null} attribution
     * @private
     */
    static _write(attribution) {
        if (!isStorageSupported()) {
            memoryAttribution = attribution;

            return;
        }

        if (attribution === null) {
            window.sessionStorage.removeItem(STORAGE_KEY);

            return;
        }

        window.sessionStorage.setItem(STORAGE_KEY, JSON.stringify(attribution));
    }
}
