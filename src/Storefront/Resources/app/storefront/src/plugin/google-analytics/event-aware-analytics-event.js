import AnalyticsEvent from 'src/plugin/google-analytics/analytics-event';

export default class EventAwareAnalyticsEvent extends AnalyticsEvent
{
    execute() {
        const events = this.getEvents();
        const pluginRegistry = window.PluginManager;

        pluginRegistry.getPluginInstances(this.getPluginName()).forEach((pluginInstance) => {
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
