import AnalyticsEvent from 'src/plugin/google-analytics/analytics-event';
import LineItemHelper from 'src/plugin/google-analytics/line-item.helper';

export default class AddShippingInfoEvent extends AnalyticsEvent
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
     * We intentionally don't listen for change events because the shipping form uses
     * auto-submit (data-form-auto-submit), which reloads the page after selection.
     * Listening to both change and page load would result in duplicate events.
     *
     * This event only fires when shipping is available (physical products).
     * For digital-only orders, no shipping form exists and the event is skipped.
     */
    execute() {
        if (!this.active) {
            return;
        }

        // Don't fire for digital-only orders where no shipping is needed
        if (!document.querySelector('.shipping-method-input')) {
            return;
        }

        const lineItems = LineItemHelper.getLineItems();
        if (lineItems.length === 0) {
            return;
        }

        const shippingTier = this._getShippingTier();
        const additionalProperties = LineItemHelper.getAdditionalProperties();

        gtag('event', 'add_shipping_info', {
            'currency': additionalProperties.currency,
            'value': additionalProperties.value,
            'shipping_tier': shippingTier,
            'items': lineItems,
        });
    }

    /**
     * Gets the currently selected shipping method name
     * @returns {string}
     * @private
     */
    _getShippingTier() {
        const checkedShipping = document.querySelector('.shipping-method-input:checked');
        if (!checkedShipping) {
            return '';
        }

        const label = checkedShipping.closest('.shipping-method-radio')?.querySelector('.shipping-method-description strong');
        if (!label) {
            return '';
        }

        return label.textContent?.trim() || '';
    }
}

