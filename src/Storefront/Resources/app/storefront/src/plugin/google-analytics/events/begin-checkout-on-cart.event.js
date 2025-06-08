import AnalyticsEvent from 'src/plugin/google-analytics/analytics-event';
import LineItemHelper from 'src/plugin/google-analytics/line-item.helper';

export default class BeginCheckoutOnCartEvent extends AnalyticsEvent
{
    supports(controllerName, actionName) {
        return controllerName === 'checkout' && actionName === 'cartpage';
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

        gtag('event', 'begin_checkout', {
            'items': LineItemHelper.getLineItems(),
        });
    }
}
