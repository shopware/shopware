import AnalyticsEvent from 'src/plugin/google-analytics/analytics-event';
import DomAccessHelper from 'src/helper/dom-access.helper';
import LineItemHelper from 'src/plugin/google-analytics/line-item.helper';

export default class PurchaseEvent extends AnalyticsEvent
{
    supports(controllerName, actionName) {
        return controllerName === 'checkout' && actionName === 'finishpage' && window.trackOrders;
    }

    execute() {
        if (!this.active) {
            return;
        }

        const orderNumberElement = DomAccessHelper.querySelector(document, '.finish-ordernumber');

        if (!orderNumberElement) {
            return;
        }

        const orderNumber = DomAccessHelper.getDataAttribute(orderNumberElement, 'order-number');
        if (!orderNumber) {
            console.warn('Cannot determine order number - Skip order tracking');

            return;
        }

        gtag('event', 'purchase', { ...{
            'transaction_id': orderNumber,
            'items':  LineItemHelper.getLineItems(),
        }, ...LineItemHelper.getAdditionalProperties() });
    }
}
