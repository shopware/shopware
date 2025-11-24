import AnalyticsEvent from 'src/plugin/google-analytics/analytics-event';
import LineItemHelper from 'src/plugin/google-analytics/line-item.helper';

export default class ViewCartEvent extends AnalyticsEvent
{
    supports(controllerName, actionName, activeRoute) {
        return activeRoute === 'frontend.checkout.cart.page';
    }

    execute() {
        if (!this.active) {
            return;
        }

        const lineItems = LineItemHelper.getLineItems();
        if (lineItems.length === 0) {
            return;
        }

        const additionalProperties = LineItemHelper.getAdditionalProperties();

        gtag('event', 'view_cart', {
            'currency': additionalProperties.currency,
            'value': additionalProperties.value,
            'items': lineItems,
        });
    }
}
