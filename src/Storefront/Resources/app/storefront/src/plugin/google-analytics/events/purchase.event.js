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

    /**
     *  @deprecated tag:v6.5.0 - Unused function will be removed as the `execute` function now pulls the uuid from the template
     */
    generateUuid() {
        return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(replace) {
            const random = Math.random() * 16 | 0;
            const value = replace === 'x' ? random : (random & 0x3 | 0x8);

            return value.toString(16);
        });
    }
}
