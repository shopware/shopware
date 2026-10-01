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
 *
 * This class also handles dynamic content (e.g., AJAX pagination) by subscribing to the
 * Listing plugin's afterRenderResponse event and re-subscribing to new plugin instances.
 */
export default class EventAwareAnalyticsEvent extends AnalyticsEvent
{
    execute() {
        // Track subscribed elements to avoid duplicate subscriptions
        this._subscribedElements = this._subscribedElements || new WeakSet();

        this._subscribeToPluginInstances();
        this._subscribeToListingUpdates();
    }

    /**
     * Get all instances of a plugin by name.
     * Returns null if plugin doesn't exist or has no instances (expected for optional plugins).
     * @param {string} pluginName
     * @returns {Array|null}
     * @private
     */
    _getPluginInstances(pluginName) {
        try {
            const instances = window.PluginManager.getPluginInstances(pluginName);
            return instances?.length > 0 ? instances : null;
        } catch {
            return null; // Optional plugin
        }
    }

    /**
     * Subscribe to all current plugin instances that haven't been subscribed to yet.
     * @private
     */
    _subscribeToPluginInstances() {
        const instances = this._getPluginInstances(this.getPluginName());
        if (!instances) {
            return;
        }

        const events = this.getEvents();

        instances.forEach((pluginInstance) => {
            const subscriptionKey = pluginInstance.el || pluginInstance;

            if (this._subscribedElements.has(subscriptionKey)) {
                return;
            }

            Object.keys(events).forEach((eventName) => {
                pluginInstance.$emitter.subscribe(eventName, events[eventName]);
            });

            this._subscribedElements.add(subscriptionKey);
        });
    }

    /**
     * Subscribe to Listing plugin's afterRenderResponse to re-subscribe after AJAX pagination.
     * Only needed for events that don't already subscribe to Listing (like AddToCart).
     * @private
     */
    _subscribeToListingUpdates() {
        if (this.getPluginName() === 'Listing' || this._listingSubscribed) {
            return;
        }

        const instances = this._getPluginInstances('Listing');
        if (!instances) {
            return;
        }

        instances.forEach((listingInstance) => {
            listingInstance.$emitter.subscribe('Listing/afterRenderResponse', async () => {
                // Await initializePlugins() to ensure new plugin instances are ready.
                // This is safe because initializePlugins() is idempotent.
                await window.PluginManager.initializePlugins();
                this._subscribeToPluginInstances();
            });
        });

        this._listingSubscribed = true;
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
