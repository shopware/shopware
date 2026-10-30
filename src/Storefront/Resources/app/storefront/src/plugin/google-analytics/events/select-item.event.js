import AnalyticsEvent from 'src/plugin/google-analytics/analytics-event';
import ListAttributionHelper from 'src/plugin/google-analytics/list-attribution.helper';
import ProductPageHelper from 'src/plugin/google-analytics/product-page.helper';

export default class SelectItemEvent extends AnalyticsEvent
{
    /**
     * Product elements are rendered on listings, search results, sliders, cross selling, the
     * wishlist and by extensions, so the event is not bound to a route.
     *
     * @returns {boolean}
     */
    supports() {
        return true;
    }

    execute() {
        // The listing plugin replaces its whole subtree on every filter, sort and pagination, so a
        // handler bound to a product element would be lost. A delegated listener survives.
        this._boundOnClick = this._onClick.bind(this);

        document.addEventListener('click', this._boundOnClick);
        // a middle click opens the link in another tab and only dispatches `auxclick`
        document.addEventListener('auxclick', this._boundOnClick);
    }

    _onClick(event) {
        if (!this.active) {
            return;
        }

        if (event.type === 'auxclick' && event.button !== 1) {
            return;
        }

        // GA4 reports a selection, which is following a link to the product. A click on the padding
        // of a card, on its variant characteristics or on a badge selects nothing, and adding to the
        // cart or to the wishlist is not a selection either.
        const link = event.target.closest?.('a');
        if (!link || link.closest('form, button')) {
            return;
        }

        const productElement = link.closest(ProductPageHelper.PRODUCT_ELEMENT_SELECTOR);
        if (!productElement?.dataset.productInformation) {
            return;
        }

        let information;
        try {
            information = JSON.parse(productElement.dataset.productInformation);
        } catch {
            return;
        }

        const itemId = information.sku ?? information.id;
        if (!itemId) {
            return;
        }

        const list = ListAttributionHelper.getListFromElement(productElement);

        // The detail page reports the same list, so both events describe one journey. The storage is
        // shared by every tab, so a product opened in another tab finds it as well.
        ListAttributionHelper.remember(itemId, list, information.id);

        this.pushEvent('select_item', {
            ...list,
            'items': [{
                'item_id': itemId,
                'item_name': information.name,
                'item_brand': information.brand,
                'item_variant': information.variant,
                'price': information.price,
                'index': this._getIndex(productElement),
            }],
        });
    }

    /**
     * The position of the product within its own list, counted from zero. A paginated listing
     * reports the position within the whole list rather than within the rendered page, so the
     * second page continues where the first ended.
     *
     * @param {HTMLElement} productElement
     * @returns {number|undefined}
     * @private
     */
    _getIndex(productElement) {
        const list = ListAttributionHelper.getListElement(productElement);
        if (!list) {
            return undefined;
        }

        const index = [...list.querySelectorAll(ProductPageHelper.PRODUCT_ELEMENT_SELECTOR)].indexOf(productElement);
        if (index === -1) {
            return undefined;
        }

        return index + ListAttributionHelper.getListStart(list);
    }
}
