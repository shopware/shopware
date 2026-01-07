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

        const additionalProperties = LineItemHelper.getAdditionalProperties();

        // Find the product data from the hidden line items container
        const hiddenLineItem = document.querySelector(`.hidden-line-item[data-id="${productId}"]`);
        if (!hiddenLineItem) {
            // Fallback: send event with just the product ID
            gtag('event', 'remove_from_cart', {
                'currency': additionalProperties.currency,
                'items': [{ 'id': productId }],
            });
            return;
        }

        const categories = LineItemHelper.getCategoriesFromElement(hiddenLineItem);
        const price = hiddenLineItem.getAttribute('data-price');
        const quantity = hiddenLineItem.getAttribute('data-quantity');
        const value = (parseFloat(price) || 0) * (parseInt(quantity, 10) || 1);

        gtag('event', 'remove_from_cart', {
            'currency': additionalProperties.currency,
            'value': value.toFixed(2),
            'items': [{
                'id': productId,
                'name': hiddenLineItem.getAttribute('data-name'),
                'quantity': quantity,
                'price': price,
                'brand': hiddenLineItem.getAttribute('data-brand'),
                ...categories,
            }],
        });
    }
}
