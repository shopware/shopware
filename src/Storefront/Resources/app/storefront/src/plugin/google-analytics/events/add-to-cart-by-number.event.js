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
        const addToCartForm = document.querySelector('.cart-add-product-container form');
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

        // The listener sits on the form, so the lookup only sees this form's fields. The field name
        // is what the add by number controller reads, which makes it a more stable hook than a
        // styling class a theme may change.
        const input = event.currentTarget.querySelector('input[name="number"]');
        if (!input?.value) {
            return;
        }

        const additionalProperties = LineItemHelper.getAdditionalProperties();

        this.pushEvent('add_to_cart', {
            'currency': additionalProperties.currency,
            'items': [
                {
                    'item_id': input.value,
                    'quantity': 1,
                },
            ],
        });
    }
}
