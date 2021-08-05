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

        DomAccessHelper.querySelector(document, '#confirmFormSubmit').addEventListener('click', this._onConfirm.bind(this, tosInput));
    }

    _onConfirm(tosInput) {
        if (!this.active) {
            return;
        }

        if (!tosInput.checked) {
            return;
        }

        gtag('event', 'purchase', { ...{
            'transaction_id': this.generateUuid(),
            'items':  LineItemHelper.getLineItems(),
        }, ...LineItemHelper.getAdditionalProperties() });
    }

    generateUuid() {
        return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(replace) {
            const random = Math.random() * 16 | 0;
            const value = replace === 'x' ? random : (random & 0x3 | 0x8);

            return value.toString(16);
        });
    }
}
