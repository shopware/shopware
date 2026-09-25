import AnalyticsEvent from 'src/plugin/google-analytics/analytics-event';
import CheckoutStepHelper from 'src/plugin/google-analytics/checkout-step.helper';
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
     *
     * The auto-submit reload runs this route again, so the event is reported once per payment
     * method of a checkout: a reload that keeps the method stays silent, selecting a different
     * one reports it. Reporting every load would push the count above `begin_checkout`, while
     * reporting only the first load would report the preselected method and never the chosen one.
     */
    execute() {
        if (!this.active) {
            return;
        }

        const paymentType = this._getPaymentType();
        if (CheckoutStepHelper.hasReported('add_payment_info', paymentType)) {
            return;
        }

        const lineItems = LineItemHelper.getLineItems();
        if (lineItems.length === 0) {
            return;
        }

        const additionalProperties = LineItemHelper.getAdditionalProperties();

        this.pushEvent('add_payment_info', {
            'currency': additionalProperties.currency,
            'value': additionalProperties.value,
            'coupon': additionalProperties.coupon,
            'payment_type': paymentType,
            'items': lineItems,
        });

        CheckoutStepHelper.markReported('add_payment_info', paymentType);
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
