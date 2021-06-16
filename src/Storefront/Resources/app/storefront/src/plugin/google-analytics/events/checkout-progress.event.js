import AnalyticsEvent from 'src/plugin/google-analytics/analytics-event';
import LineItemHelper from 'src/plugin/google-analytics/line-item.helper';

export default class CheckoutProgressEvent extends AnalyticsEvent
{
    supports(controllerName, actionName) {
        return controllerName === 'checkout' && actionName === 'confirmpage';
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
