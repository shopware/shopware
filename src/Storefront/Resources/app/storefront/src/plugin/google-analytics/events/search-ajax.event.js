import EventAwareAnalyticsEvent from 'src/plugin/google-analytics/event-aware-analytics-event';

export default class SearchAjaxEvent extends EventAwareAnalyticsEvent
{
    /* eslint-disable no-unused-vars */
    /**
     * @param {string} controllerName @deprecated tag:v6.8.0 - Will be removed, use activeRoute instead.
     * @param {string} actionName @deprecated tag:v6.8.0 - Will be removed, use activeRoute instead.
     * @param {string} activeRoute
     * @returns {boolean}
     */
    supports(controllerName, actionName, activeRoute) {
        return true;
    }
    /* eslint-enable no-unused-vars */

    getPluginName() {
        return 'SearchWidget';
    }

    getEvents() {
        return {
            'handleInputEvent':  this._onSearch.bind(this),
        };
    }

    _onSearch(event) {
        if (!this.active) {
            return;
        }

        gtag('event', 'search', {
            'search_term': event.detail.value,
        });
    }
}
