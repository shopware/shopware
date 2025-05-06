import AnalyticsEvent from 'src/plugin/google-analytics/analytics-event';

export default class AddToCartByNumberEvent extends AnalyticsEvent
{
    supports(activeRoute) {
        return activeRoute === 'frontend.checkout.cart.page';
    }

    execute() {
        const addToCartForm = document.querySelector('.cart-add-product');
        if (!addToCartForm) {
            return;
        }

        addToCartForm.addEventListener('submit', this._formSubmit.bind(this));
    }

    _formSubmit(event) {
        if (!this.active) {
            return;
        }

        const input = event.currentTarget.querySelector('.form-control');

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
