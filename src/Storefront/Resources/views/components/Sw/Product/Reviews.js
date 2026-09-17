import ElementLoadingIndicatorUtil from 'src/utility/loading-indicator/element-loading-indicator.util';

export default class ProductReviews extends ShopwareComponent {
    static options = {
        contentUrl: null,
    };

    init() {
        this.domParser = new DOMParser();
        this.activeParams = this.paramsFromUrl();
        this.debouncedReload = this.debounce(this.reload.bind(this), 200);

        // Filters are a separate element (possibly outside this one), so they reach us over the event bus.
        this.onFiltersChange = this.onFiltersChange.bind(this);
        window.Shopware.on('ReviewFilters:Change', this.onFiltersChange);

        this.onPaginationClick = this.onPaginationClick.bind(this);
        this.el.querySelectorAll('.sw-pagination__link').forEach((el) => el.addEventListener('click', this.onPaginationClick));
    }

    destroy() {
        window.Shopware.off('ReviewFilters:Change', this.onFiltersChange);
        this.el.querySelectorAll('.sw-pagination__link').forEach((el) => el.removeEventListener('click', this.onPaginationClick));
    }

    onFiltersChange(params) {
        // A sort or filter change resets to the first page.
        this.activeParams = { ...params };
        this.debouncedReload();
    }

    onPaginationClick(event) {
        event.preventDefault();
        this.activeParams.p = event.currentTarget.getAttribute('data-page');
        this.debouncedReload();
    }

    paramsFromUrl() {
        const params = {};
        new URLSearchParams(window.location.search).forEach((value, key) => {
            params[key] = value;
        });

        return params;
    }

    async reload() {
        if (!this.options.contentUrl) {
            return;
        }

        ElementLoadingIndicatorUtil.create(this.el);

        // contentUrl (Twig path()) already carries the base path and elementId; add the runtime params.
        const url = new URL(this.options.contentUrl, window.location.origin);
        Object.entries(this.activeParams).forEach(([key, value]) => url.searchParams.set(key, value));

        const response = await fetch(url);
        const html = await response.text();
        const fresh = this.domParser
            .parseFromString(html, 'text/html')
            .querySelector(`[data-element-id="${this.el.dataset.elementId}"]`);

        // Replacing the element re-initializes it (and drops the loader with the old node); the component system
        // rebinds on the fresh node.
        if (fresh) {
            this.el.replaceWith(fresh);
        } else {
            ElementLoadingIndicatorUtil.remove(this.el);
        }

        // Keep the URL and back button in sync with the applied sort, filter and page — not the elementId hint.
        const query = new URLSearchParams(this.activeParams).toString();
        window.history.pushState(null, '', `${window.location.pathname}?${query}`);
    }
}
