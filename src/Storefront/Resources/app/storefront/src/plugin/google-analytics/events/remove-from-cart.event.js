import AnalyticsEvent from 'src/plugin/google-analytics/analytics-event';
import LineItemHelper from 'src/plugin/google-analytics/line-item.helper';

export default class RemoveFromCart extends AnalyticsEvent
{
    /* eslint-disable no-unused-vars */
    /**
     * @param {string} controllerName @deprecated tag:v6.8.0 - Will be removed, use activeRoute instead.
     * @param {string} actionName @deprecated tag:v6.8.0 - Will be removed, use activeRoute instead.
     * @param {string} activeRoute
     * @returns {boolean}
     */
    supports(controllerName, actionName, activeRoute) {
        return true;
    }
    /* eslint-enable no-unused-vars */

    execute() {
        document.addEventListener('click', this._onRemoveFromCart.bind(this));
    }

    _onRemoveFromCart(event) {
        if (!this.active) {
            return;
        }

        const removeButton = event.target.closest('.line-item-remove-button');
        if (!removeButton) {
            return;
        }

        // despite its name, the attribute holds the line item id, which a Store API client or an
        // extension can set to something other than the product id
        const lineItemId = removeButton.getAttribute('data-product-id');
        if (!lineItemId) {
            return;
        }

        // Find the product data from the hidden line items container. Only product line items are
        // rendered there, so a remove button without a match belongs to a discount or another non
        // product line item, which GA4 does not report as an item. Reporting it anyway would put the
        // line item id into `item_id`, where every other event reports a product number.
        // a theme that overrides the hidden line item without the line item id still matches by the
        // product id, which is the same for every product added through the storefront
        // The ids are compared instead of put into a selector, as a line item id from an API
        // client can contain characters a selector would have to escape.
        const hiddenLineItems = [...document.querySelectorAll('.hidden-line-item')];
        const hiddenLineItem = hiddenLineItems.find(element => element.getAttribute('data-line-item-id') === lineItemId)
            ?? hiddenLineItems.find(element => element.getAttribute('data-id') === lineItemId);
        if (!hiddenLineItem) {
            return;
        }

        const productId = hiddenLineItem.getAttribute('data-id');

        const additionalProperties = LineItemHelper.getAdditionalProperties();
        const categories = LineItemHelper.getCategoriesFromElement(hiddenLineItem);
        const price = hiddenLineItem.getAttribute('data-price');
        const quantity = hiddenLineItem.getAttribute('data-quantity');
        const sku = hiddenLineItem.getAttribute('data-sku');
        const value = LineItemHelper.getLineTotal(hiddenLineItem);

        this.pushEvent('remove_from_cart', {
            'currency': additionalProperties.currency,
            'value': value,
            'items': [{
                'item_id': sku ?? productId,
                'item_name': hiddenLineItem.getAttribute('data-name'),
                'quantity': quantity,
                'price': price,
                'discount': hiddenLineItem.getAttribute('data-discount'),
                'item_brand': hiddenLineItem.getAttribute('data-brand'),
                'item_variant': hiddenLineItem.getAttribute('data-variant'),
                ...categories,
            }],
        });
    }
}
