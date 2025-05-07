import AnalyticsEvent from 'src/plugin/google-analytics/analytics-event';
import LineItemHelper from 'src/plugin/google-analytics/line-item.helper';

export default class CheckoutProgressEvent extends AnalyticsEvent
{
    supports(controllerName, actionName, activeRoute) {
        return activeRoute === 'frontend.checkout.cart.page';
    }

    execute() {
        if (!this.active) {
            return;
        }

        gtag('event', 'checkout_progress', {
            'items': LineItemHelper.getLineItems(),
        });
    }
}
