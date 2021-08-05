import AnalyticsEvent from 'src/plugin/google-analytics/analytics-event';
import DomAccessHelper from 'src/helper/dom-access.helper';

export default class AddToCartByNumberEvent extends AnalyticsEvent
{
    supports(controllerName, actionName) {
        return controllerName === 'checkout' && actionName === 'cartpage';
    }

    execute() {
        const addToCartForm = DomAccessHelper.querySelector(document, '.cart-add-product', false);
        if (!addToCartForm) {
            return;
        }

        addToCartForm.addEventListener('submit', this._formSubmit.bind(this));
    }

    _formSubmit(event) {
        if (!this.active) {
            return;
        }

        const input = DomAccessHelper.querySelector(event.currentTarget, '.form-control');

        gtag('event', 'add_to_cart', {
            'items': [
                {
                    'id': input.value,
                    'quantity': 1,
                },
            ],
        });
    }
}
