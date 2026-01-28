import AnalyticsEvent from 'src/plugin/google-analytics/analytics-event';
import LineItemHelper from 'src/plugin/google-analytics/line-item.helper';

export default class AddToCartByNumberEvent extends AnalyticsEvent
{
    /**
     * @param {string} controllerName @deprecated tag:v6.8.0 - Will be removed, use activeRoute instead.
     * @param {string} actionName @deprecated tag:v6.8.0 - Will be removed, use activeRoute instead.
     * @param {string} activeRoute
     * @returns {boolean}
     */
    supports(controllerName, actionName, activeRoute) {
        return activeRoute === 'frontend.checkout.cart.page';
    }

    execute() {
        const addToCartForm = document.querySelector('.cart-add-product');
        if (!addToCartForm) {
            return;
        }

        addToCartForm.addEventListener('submit', this._formSubmit.bind(this));
    }

    /**
     * Note: When adding by product number, we only have the product number available.
     * Full product data (name, brand, price, categories) is not available at this point.
     */
    _formSubmit(event) {
        if (!this.active) {
            return;
        }

        const input = event.currentTarget.querySelector('.form-control');
        const additionalProperties = LineItemHelper.getAdditionalProperties();

        gtag('event', 'add_to_cart', {
            'currency': additionalProperties.currency,
            'items': [
                {
                    'id': input.value,
                    'quantity': 1,
                },
            ],
        });
    }
}
