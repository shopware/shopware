import AnalyticsEvent from 'src/plugin/google-analytics/analytics-event';

export default class ViewSearchResults extends AnalyticsEvent
{
    supports(activeRoute) {
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
