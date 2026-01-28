import AnalyticsEvent from 'src/plugin/google-analytics/analytics-event';
import LineItemHelper from 'src/plugin/google-analytics/line-item.helper';

export default class AddPaymentInfoEvent extends AnalyticsEvent
{
    /**
     * @param {string} controllerName @deprecated tag:v6.8.0 - Will be removed, use activeRoute instead.
     * @param {string} actionName @deprecated tag:v6.8.0 - Will be removed, use activeRoute instead.
     * @param {string} activeRoute
     * @returns {boolean}
     */
    supports(controllerName, actionName, activeRoute) {
        return activeRoute === 'frontend.checkout.confirm.page';
    }

    /**
     * Fires on page load only.
     * We intentionally don't listen for change events because the payment form uses
     * auto-submit (data-form-auto-submit), which reloads the page after selection.
     * Listening to both change and page load would result in duplicate events.
     */
    execute() {
        if (!this.active) {
            return;
        }

        const lineItems = LineItemHelper.getLineItems();
        if (lineItems.length === 0) {
            return;
        }

        const paymentType = this._getPaymentType();
        const additionalProperties = LineItemHelper.getAdditionalProperties();

        gtag('event', 'add_payment_info', {
            'currency': additionalProperties.currency,
            'value': additionalProperties.value,
            'payment_type': paymentType,
            'items': lineItems,
        });
    }

    /**
     * Gets the currently selected payment method name
     * @returns {string}
     * @private
     */
    _getPaymentType() {
        const checkedPayment = document.querySelector('.payment-method-input:checked');
        if (!checkedPayment) {
            return '';
        }

        const label = checkedPayment.closest('.payment-method-radio')?.querySelector('.payment-method-description strong');
        if (!label) {
            return '';
        }

        return label.textContent?.trim() || '';
    }
}
