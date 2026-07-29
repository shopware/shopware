import AnalyticsEvent from 'src/plugin/google-analytics/analytics-event';
import CheckoutStepHelper from 'src/plugin/google-analytics/checkout-step.helper';
import LineItemHelper from 'src/plugin/google-analytics/line-item.helper';

export default class BeginCheckoutOnCartEvent extends AnalyticsEvent
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
        const beginCheckoutBtn = document.querySelector('.begin-checkout-btn');

        if (!beginCheckoutBtn) {
            return;
        }

        beginCheckoutBtn.addEventListener('click', this._onBeginCheckout.bind(this));
    }

    _onBeginCheckout() {
        if (!this.active) {
            return;
        }

        // a new checkout starts, so its shipping and payment steps are reported again
        CheckoutStepHelper.reset();

        const additionalProperties = LineItemHelper.getAdditionalProperties();

        this.pushEvent('begin_checkout', {
            'currency': additionalProperties.currency,
            'value': additionalProperties.value,
            'items': LineItemHelper.getLineItems(),
        });
    }
}
