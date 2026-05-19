import AnalyticsEvent from 'src/plugin/google-analytics/analytics-event';

export default class ViewSearchResults extends AnalyticsEvent
{
    /**
     * @param {string} controllerName @deprecated tag:v6.8.0 - Will be removed, use activeRoute instead.
     * @param {string} actionName @deprecated tag:v6.8.0 - Will be removed, use activeRoute instead.
     * @param {string} activeRoute
     * @returns {boolean}
     */
    supports(controllerName, actionName, activeRoute) {
        return activeRoute === 'frontend.search.page';
    }

    execute() {
        if (!this.active) {
            return;
        }

        const searchInput = document.querySelector('.header-search-input');

        gtag('event', 'view_search_results', {
            'search_term': searchInput.value,
        });
    }
}
