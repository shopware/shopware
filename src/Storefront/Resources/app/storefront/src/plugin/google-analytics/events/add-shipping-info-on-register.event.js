import EventAwareAnalyticsEvent from 'src/plugin/google-analytics/event-aware-analytics-event';
import CheckoutStepHelper from 'src/plugin/google-analytics/checkout-step.helper';
import LineItemHelper from 'src/plugin/google-analytics/line-item.helper';

/**
 * A new customer enters the shipping address on the checkout register page, which is where GA4 expects
 * `add_shipping_info`. It is reported with the preselected shipping method once the registration is
 * submitted. The confirm page reports the same event only when it shows a different method, so a
 * customer who keeps the method is counted once, and a returning customer, who skips the register
 * page, is reported on the confirm page as before.
 */
export default class AddShippingInfoOnRegisterEvent extends EventAwareAnalyticsEvent
{
    /**
     * @param {string} controllerName @deprecated tag:v6.8.0 - Will be removed, use activeRoute instead.
     * @param {string} actionName @deprecated tag:v6.8.0 - Will be removed, use activeRoute instead.
     * @param {string} activeRoute
     * @returns {boolean}
     */
    supports(controllerName, actionName, activeRoute) {
        return activeRoute === 'frontend.checkout.register.page';
    }

    /**
     * @return string
     */
    getPluginName() {
        return 'FormValidation';
    }

    getEvents() {
        return {
            'beforeSubmit': this._onFormSubmit.bind(this),
        };
    }

    _onFormSubmit(event) {
        if (!this.active) {
            return;
        }

        if (!event.target.classList.contains('register-form') || !event.detail.validity) {
            return;
        }

        // only rendered when the cart needs shipping, a cart of digital products has no method
        const shippingTier = document.querySelector('[data-analytics-shipping-tier]')?.getAttribute('data-analytics-shipping-tier');
        if (!shippingTier || CheckoutStepHelper.hasReported('add_shipping_info', shippingTier)) {
            return;
        }

        const lineItems = LineItemHelper.getLineItems();
        if (lineItems.length === 0) {
            return;
        }

        const additionalProperties = LineItemHelper.getAdditionalProperties();

        this.pushEvent('add_shipping_info', {
            'currency': additionalProperties.currency,
            'value': additionalProperties.value,
            'coupon': additionalProperties.coupon,
            'shipping_tier': shippingTier,
            'items': lineItems,
        });

        CheckoutStepHelper.markReported('add_shipping_info', shippingTier);
    }
}
