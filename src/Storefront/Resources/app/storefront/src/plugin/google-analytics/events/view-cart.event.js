import AnalyticsEvent from 'src/plugin/google-analytics/analytics-event';
import LineItemHelper from 'src/plugin/google-analytics/line-item.helper';

export default class ViewCartEvent extends AnalyticsEvent
{
    supports(controllerName, actionName, activeRoute) {
        // Always support if on cart page
        if (activeRoute === 'frontend.checkout.cart.page') {
            return true;
        }

        // Also support on any page if offcanvas tracking is enabled
        return this._isOffcanvasTrackingEnabled();
    }

    execute() {
        if (!this.active) {
            return;
        }

        // Fire immediately on cart page
        if (window.activeRoute === 'frontend.checkout.cart.page') {
            this._fireViewCartEvent();
        }

        // Register offcanvas listener if tracking is enabled
        // This tracks all offcanvas cart openings (both from cart button clicks and add-to-cart)
        // For accurate funnel tracking, disable "Open offcanvas after add to cart" in cart settings
        if (this._isOffcanvasTrackingEnabled()) {
            this._registerOffcanvasListener();
        }
    }

    _isOffcanvasTrackingEnabled() {
        return window.trackOffcanvasCart === '1';
    }

    _registerOffcanvasListener() {
        const pluginRegistry = window.PluginManager;
        const plugin = pluginRegistry.getPlugin('OffCanvasCart', false);

        if (!plugin) {
            return;
        }

        const instances = plugin.get('instances');
        if (!instances || instances.length === 0) {
            return;
        }

        instances.forEach((pluginInstance) => {
            pluginInstance.$emitter.subscribe('offCanvasOpened', this._onOffCanvasOpened.bind(this));
        });
    }

    _onOffCanvasOpened() {
        if (!this.active) {
            return;
        }

        this._fireViewCartEvent();
    }

    _fireViewCartEvent() {
        const lineItems = LineItemHelper.getLineItems();
        if (lineItems.length === 0) {
            return;
        }

        const additionalProperties = LineItemHelper.getAdditionalProperties();

        gtag('event', 'view_cart', {
            'currency': additionalProperties.currency,
            'value': additionalProperties.value,
            'items': lineItems,
        });
    }
}
