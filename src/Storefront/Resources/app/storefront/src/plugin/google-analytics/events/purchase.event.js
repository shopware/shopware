import AnalyticsEvent from 'src/plugin/google-analytics/analytics-event';
import LineItemHelper from 'src/plugin/google-analytics/line-item.helper';

export default class PurchaseEvent extends AnalyticsEvent
{
    /**
     * @param {string} controllerName @deprecated tag:v6.8.0 - Will be removed, use activeRoute instead.
     * @param {string} actionName @deprecated tag:v6.8.0 - Will be removed, use activeRoute instead.
     * @param {string} activeRoute
     * @returns {boolean}
     */
    supports(controllerName, actionName, activeRoute) {
        return activeRoute === 'frontend.checkout.finish.page' && window.trackOrders;
    }

    execute() {
        if (!this.active) {
            return;
        }

        const orderNumberElement = document.querySelector('.finish-ordernumber');
        if (!orderNumberElement) {
            console.warn('Cannot get order number element - Skip order tracking');

            return;
        }

        const orderNumber = orderNumberElement.getAttribute('data-order-number');
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
