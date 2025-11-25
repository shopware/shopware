import AnalyticsEvent from 'src/plugin/google-analytics/analytics-event';

/**
 * Base class for analytics events that need to subscribe to other Shopware plugin events.
 *
 * This class subscribes to events emitted by storefront plugins (e.g., AddToCart, SearchWidget)
 * and forwards them to Google Analytics. Not all plugins are available on every page:
 * - A plugin might not be registered (e.g., AddToWishlist when wishlist feature is disabled)
 * - A plugin might be registered but have no instances on the current page
 *   (e.g., AddToCart on pages without product forms)
 *
 * Both scenarios are handled gracefully by skipping event subscription silently.
 */
export default class EventAwareAnalyticsEvent extends AnalyticsEvent
{
    execute() {
        const events = this.getEvents();
        const pluginRegistry = window.PluginManager;
        const pluginName = this.getPluginName();

        // Use non-strict mode to avoid console warnings for unregistered plugins.
        // This is expected behavior when features are disabled or not present on the page.
        const plugin = pluginRegistry.getPlugin(pluginName, false);
        if (!plugin) {
            return;
        }

        // Skip if the plugin has no instances on the current page.
        // This happens when the plugin is registered globally but has no DOM elements to attach to.
        const instances = plugin.get('instances');
        if (!instances || instances.length === 0) {
            return;
        }

        instances.forEach((pluginInstance) => {
            Object.keys(events).forEach((eventName) => {
                pluginInstance.$emitter.subscribe(eventName, events[eventName]);
            });
        });
    }

    /**
     * @return {Object}
     */
    getEvents() {
        console.warn('[Google Analytics Plugin] Method \'getEvents\' was not overridden by `' + this.constructor.name + '`.');
    }

    /**
     * @return string
     */
    getPluginName() {
        console.warn('[Google Analytics Plugin] Method \'getPluginName\' was not overridden by `' + this.constructor.name + '`.');
    }
}
