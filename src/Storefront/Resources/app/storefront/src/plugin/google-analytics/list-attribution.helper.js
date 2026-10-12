const STORAGE_KEY = 'swGaSelectedItemList';
const HANDOVER_KEY = 'swGaSelectedItemListHandover';

// a product opened in another tab loads within seconds, a longer lived entry would attribute an
// unrelated later visit of the product
const HANDOVER_TTL = 60000;
const HANDOVER_LIMIT = 10;

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
 * A product opened in another tab is the exception. The new tab does not reliably start with a copy
 * of the session storage, so the attribution is handed over through `localStorage` for a minute.
 * `localStorage` is shared by every tab, so a handover only applies to the page that was opened
 * from the page that stored it, recognised by its referrer.
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
     * Hands the attribution over to a product opened in another tab. Several products can be opened
     * at once, also the same product several times, so every opened tab keeps its own handover
     * until a tab consumes it or it expires.
     *
     * A new tab cannot be told which of two handovers of the same product on the same page is its
     * own without changing the address of the product, so they are consumed in the order they were
     * stored, which is the order the tabs were opened and usually load in.
     *
     * @param {string} itemId the reported product number
     * @param {Object} list
     * @param {string|undefined} productId the id of the product the card shows
     */
    static handOver(itemId, list, productId = undefined) {
        if (!itemId || !list?.item_list_id) {
            return;
        }

        // a referrer never carries the fragment of the page
        const source = window.location.href.split('#')[0];
        const handovers = ListAttributionHelper._readHandovers().slice(-(HANDOVER_LIMIT - 1));

        handovers.push({ itemId, productId, list, source, expires: Date.now() + HANDOVER_TTL });

        ListAttributionHelper._writeHandovers(handovers);
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
        const matches = attribution => (!!itemId && attribution.itemId === itemId)
            || (!!attribution.productId && productIds.includes(attribution.productId));

        const stored = ListAttributionHelper._read();

        if (stored && matches(stored)) {
            ListAttributionHelper.reset();

            return stored.list;
        }

        // Only the tab opened from the page that stored the handover may take it. Another tab, the
        // opener itself, and a direct visit of the product have a different or no referrer.
        const referrer = document.referrer;
        const handovers = ListAttributionHelper._readHandovers();
        const handover = referrer
            ? handovers.find(entry => entry.source === referrer && matches(entry))
            : undefined;

        if (!handover) {
            return {};
        }

        ListAttributionHelper._writeHandovers(handovers.filter(entry => entry !== handover));

        return handover.list;
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
     * The handovers that have not expired yet. Reading and writing fail silently, as a lost
     * handover only means the product view is reported without a list.
     *
     * @returns {Object[]}
     * @private
     */
    static _readHandovers() {
        try {
            const stored = JSON.parse(window.localStorage.getItem(HANDOVER_KEY));
            const now = Date.now();

            return Array.isArray(stored)
                ? stored.filter(handover => handover?.itemId && handover.expires > now)
                : [];
        } catch (e) {
            return [];
        }
    }

    /**
     * @param {Object[]} handovers
     * @private
     */
    static _writeHandovers(handovers) {
        try {
            if (handovers.length === 0) {
                window.localStorage.removeItem(HANDOVER_KEY);

                return;
            }

            window.localStorage.setItem(HANDOVER_KEY, JSON.stringify(handovers));
        } catch (e) {
            // storage is full or disabled
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
