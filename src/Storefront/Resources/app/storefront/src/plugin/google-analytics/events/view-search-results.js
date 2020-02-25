import AnalyticsEvent from 'src/plugin/google-analytics/analytics-event';
import DomAccessHelper from 'src/helper/dom-access.helper';

export default class ViewSearchResults extends AnalyticsEvent
{
    supports(controllerName, actionName) {
        return controllerName === 'search' && actionName === 'search';
    }

    execute() {
        const searchInput = DomAccessHelper.querySelector(document, '.header-search-input');

        gtag('event', 'view_search_results', {
            'search_term': searchInput.value
        });
    }
}
