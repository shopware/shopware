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

        const productId = removeButton.getAttribute('data-product-id');
        if (!productId) {
            return;
        }

        // Find the product data from the hidden line items container. Only product line items are
        // rendered there, so a remove button without a match belongs to a discount or another non
        // product line item, which GA4 does not report as an item. Reporting it anyway would put the
        // line item id into `item_id`, where every other event reports a product number.
        const hiddenLineItem = document.querySelector(`.hidden-line-item[data-id="${productId}"]`);
        if (!hiddenLineItem) {
            return;
        }

        const additionalProperties = LineItemHelper.getAdditionalProperties();
        const categories = LineItemHelper.getCategoriesFromElement(hiddenLineItem);
        const price = hiddenLineItem.getAttribute('data-price');
        const quantity = hiddenLineItem.getAttribute('data-quantity');
        const sku = hiddenLineItem.getAttribute('data-sku');
        const value = (parseFloat(price) || 0) * (parseInt(quantity, 10) || 1);

        this.pushEvent('remove_from_cart', {
            'currency': additionalProperties.currency,
            'value': value,
            'items': [{
                'item_id': sku ?? productId,
                'item_name': hiddenLineItem.getAttribute('data-name'),
                'quantity': quantity,
                'price': price,
                'item_brand': hiddenLineItem.getAttribute('data-brand'),
                'item_variant': hiddenLineItem.getAttribute('data-variant'),
                ...categories,
            }],
        });
    }
}
