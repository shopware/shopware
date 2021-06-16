import AnalyticsEvent from 'src/plugin/google-analytics/analytics-event';
import DomAccessHelper from 'src/helper/dom-access.helper';
import LineItemHelper from 'src/plugin/google-analytics/line-item.helper';

export default class BeginCheckoutOnCartEvent extends AnalyticsEvent
{
    supports(controllerName, actionName) {
        return controllerName === 'checkout' && actionName === 'cartpage';
    }

    execute() {
        const beginCheckoutBtn = DomAccessHelper.querySelector(document, '.begin-checkout-btn', false);

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
