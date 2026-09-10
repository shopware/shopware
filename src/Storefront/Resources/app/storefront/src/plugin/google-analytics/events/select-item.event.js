import AnalyticsEvent from 'src/plugin/google-analytics/analytics-event';
import ListAttributionHelper from 'src/plugin/google-analytics/list-attribution.helper';

export default class SelectItemEvent extends AnalyticsEvent
{
    /**
     * Product boxes are rendered on listings, search results, sliders, cross selling and the
     * wishlist, so the event is not bound to a route.
     *
     * @returns {boolean}
     */
    supports() {
        return true;
    }

    execute() {
        // The listing plugin replaces its whole subtree on every filter, sort and pagination, so a
        // handler bound to a product box would be lost. A delegated listener survives.
        this._boundOnClick = this._onClick.bind(this);

        document.addEventListener('click', this._boundOnClick);
    }

    _onClick(event) {
        if (!this.active) {
            return;
        }

        // adding to the cart or to the wishlist is not a selection
        if (event.target.closest('form, button')) {
            return;
        }

        const productBox = event.target.closest('.product-box');
        if (!productBox?.dataset.productInformation) {
            return;
        }

        let information;
        try {
            information = JSON.parse(productBox.dataset.productInformation);
        } catch {
            return;
        }

        const itemId = information.sku ?? information.id;
        if (!itemId) {
            return;
        }

        const list = ListAttributionHelper.getListFromElement(productBox);

        // the detail page reports the same list, so both events describe one journey
        ListAttributionHelper.remember(itemId, list);

        this.pushEvent('select_item', {
            ...list,
            'items': [{
                'item_id': itemId,
                'item_name': information.name,
                'item_brand': information.brand,
                'item_variant': information.variant,
                'price': information.price,
                'index': this._getIndex(productBox),
            }],
        });
    }

    /**
     * The position of the product within its own list, counted from zero.
     *
     * @param {HTMLElement} productBox
     * @returns {number|undefined}
     * @private
     */
    _getIndex(productBox) {
        const list = productBox.closest('[data-list-id]');
        if (!list) {
            return undefined;
        }

        const index = [...list.querySelectorAll('.product-box')].indexOf(productBox);

        return index === -1 ? undefined : index;
    }
}
