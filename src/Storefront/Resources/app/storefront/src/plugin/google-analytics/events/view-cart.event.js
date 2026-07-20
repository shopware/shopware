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

        this._debounceTimeout = null;

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
            // Fire on initial offcanvas opening
            pluginInstance.$emitter.subscribe('offCanvasOpened', this._onOffCanvasCartChange.bind(this));
            // Fire on cart content updates (quantity change, product removal, promotion, shipping)
            // registerEvents fires at the very end after DOM is fully updated
            pluginInstance.$emitter.subscribe('registerEvents', this._onOffCanvasCartChange.bind(this));
        });
    }

    _onOffCanvasCartChange() {
        if (!this.active) {
            return;
        }

        // Debounce to avoid duplicate events when multiple events fire in quick succession
        // (e.g., offCanvasOpened and registerEvents both fire during cart updates)
        if (this._debounceTimeout) {
            clearTimeout(this._debounceTimeout);
        }

        this._debounceTimeout = setTimeout(() => {
            this._fireViewCartEvent();
            this._debounceTimeout = null;
        }, 50);
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
