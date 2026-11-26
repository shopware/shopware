import EventAwareAnalyticsEvent from 'src/plugin/google-analytics/event-aware-analytics-event';

export default class SearchAjaxEvent extends EventAwareAnalyticsEvent
{
    /**
     * @returns {boolean}
     */
    supports() {
        return true;
    }

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

        this.pushEvent('search', {
            'search_term': event.detail.value,
        }, { ecommerce: false });
    }
}
