import AnalyticsEvent from 'src/plugin/google-analytics/analytics-event';
import LineItemHelper from 'src/plugin/google-analytics/line-item.helper';

/**
 * @deprecated tag:v6.8.0 - Will be removed without replacement.
 * The `checkout_progress` event was a Universal Analytics (GA3) event that has been deprecated in GA4.
 * It was incorrectly fired on the cart page, which is now covered by the `view_cart` event.
 * In GA4, the checkout funnel should use: view_cart → begin_checkout → add_shipping_info → add_payment_info → purchase.
 * This event is no longer registered by default since v6.7.6.0.
 */
export default class CheckoutProgressEvent extends AnalyticsEvent
{
    /**
     * @param {string} controllerName @deprecated tag:v6.8.0 - Will be removed, use activeRoute instead.
     * @param {string} actionName @deprecated tag:v6.8.0 - Will be removed, use activeRoute instead.
     * @param {string} activeRoute
     * @returns {boolean}
     */
    supports(controllerName, actionName, activeRoute) {
        return activeRoute === 'frontend.checkout.cart.page';
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
