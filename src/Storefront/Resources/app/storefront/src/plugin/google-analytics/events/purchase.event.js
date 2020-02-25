import AnalyticsEvent from 'src/plugin/google-analytics/analytics-event';
import DomAccessHelper from 'src/helper/dom-access.helper';
import LineItemHelper from 'src/plugin/google-analytics/line-item.helper';

export default class PurchaseEvent extends AnalyticsEvent
{
    supports(controllerName, actionName) {
        return controllerName === 'checkout' && actionName === 'confirmpage' && window.trackOrders;
    }

    execute() {
        const tosInput = DomAccessHelper.querySelector(document, '#tos');

        DomAccessHelper.querySelector(document, '#confirmFormSubmit').addEventListener('click', () => {
            if (!tosInput.checked) {
                return;
            }

            gtag('event', 'purchase', {
                'transaction_id': window.contextToken,
                'items':  LineItemHelper.getLineItems()
            });
        });
    }
}
