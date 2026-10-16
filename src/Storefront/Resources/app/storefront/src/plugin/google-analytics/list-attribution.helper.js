import Storage from 'src/helper/storage/storage.helper';

const STORAGE_KEY = 'swGaSelectedItemLists';

// A product is usually opened within seconds of its selection. A longer lived attribution would
// assign an unrelated later visit of the product to the list.
const TTL = 30 * 60 * 1000;

// several products can be opened in other tabs at once, each keeps its attribution until it loads
const LIMIT = 20;

/**
 * Carries the list a product was selected from to the product detail page.
 *
 * GA4 attributes a product to the list it was presented in, so `view_item` has to report the same
 * `item_list_id` and `item_list_name` as the `select_item` that led to it. Both events happen on
 * different pages, possibly in different tabs, so the attribution is kept in the shared `Storage`
 * helper, which prefers `localStorage` and is available to every tab. Each attribution expires and
 * is consumed by the first detail page of the product.
 */
export default class ListAttributionHelper
{
    /**
     * Reads the list an element belongs to.
     *
     * A list the page identified with `data-list-id` wins. Products of any other shopping experience
     * element, including those of extensions, are attributed to the element itself.
     *
     * @param {HTMLElement|null} element any element inside the list
     * @returns {{item_list_id?: string, item_list_name?: string}}
     */
    static getListFromElement(element) {
        const list = element?.closest('[data-list-id]');

        if (list) {
            return {
                item_list_id: list.getAttribute('data-list-id') || undefined,
                item_list_name: list.getAttribute('data-list-name') || undefined,
            };
        }

        const cmsElementId = element?.closest('[data-cms-element-id]')?.getAttribute('data-cms-element-id');
        if (cmsElementId) {
            return { item_list_id: `cms:${cmsElementId}` };
        }

        return {};
    }

    /**
     * The element that holds the products of the list an element belongs to.
     *
     * @param {HTMLElement|null} element
     * @returns {HTMLElement|null}
     */
    static getListElement(element) {
        return element?.closest('[data-list-id]') ?? element?.closest('[data-cms-element-id]') ?? null;
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
     * @param {string|undefined} productId the id of the product the element shows
     */
    static remember(itemId, list, productId = undefined) {
        if (!itemId || !list?.item_list_id) {
            return;
        }

        // the latest selection of a product replaces an earlier one from another list
        const entries = ListAttributionHelper._read()
            .filter(entry => entry.itemId !== itemId)
            .slice(-(LIMIT - 1));

        entries.push({ itemId, productId, list, expires: Date.now() + TTL });

        ListAttributionHelper._write(entries);
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
        const entries = ListAttributionHelper._read();
        const match = [...entries].reverse().find(entry => (!!itemId && entry.itemId === itemId)
            || (!!entry.productId && productIds.includes(entry.productId)));

        if (!match) {
            return {};
        }

        ListAttributionHelper._write(entries.filter(entry => entry !== match));

        return match.list ?? {};
    }

    /**
     * The attributions that have not expired yet. A lost attribution only means the product view
     * is reported without a list, so reading never fails.
     *
     * @returns {Object[]}
     * @private
     */
    static _read() {
        try {
            const stored = JSON.parse(Storage.getItem(STORAGE_KEY));
            const now = Date.now();

            return Array.isArray(stored)
                ? stored.filter(entry => entry?.itemId && entry.expires > now)
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
