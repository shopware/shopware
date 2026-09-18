import ElementLoadingIndicatorUtil from 'src/utility/loading-indicator/element-loading-indicator.util';

export default class ProductReviews extends ShopwareComponent {
    static options = {
        contentUrl: null,
    };

    init() {
        this.domParser = new DOMParser();
        this.activeParams = this.paramsFromUrl();
        this.debouncedReload = this.debounce(this.reload.bind(this), 200);

        // Filters, pagination and the review form are separate components (possibly outside this
        // element), so they reach us over the event bus.
        this.onFiltersChange = this.handleFiltersChange.bind(this);
        this.onPageChange = this.handlePageChange.bind(this);
        this.onReviewSubmitted = this.handleReviewSubmitted.bind(this);

        window.Shopware.on('ReviewFilters:Change', this.onFiltersChange);
        window.Shopware.on('Pagination:Change', this.onPageChange);
        window.Shopware.on('ReviewForm:Submitted', this.onReviewSubmitted);
    }

    destroy() {
        window.Shopware.off('ReviewFilters:Change', this.onFiltersChange);
        window.Shopware.off('Pagination:Change', this.onPageChange);
        window.Shopware.off('ReviewForm:Submitted', this.onReviewSubmitted);
    }

    handleFiltersChange(params) {
        // A sort or filter change resets to the first page.
        this.activeParams = { ...params };
        this.debouncedReload();
    }

    handlePageChange(page) {
        this.activeParams.p = page;
        this.debouncedReload();
    }

    handleReviewSubmitted() {
        // The customer's own review shows even while pending. Clear any sort/filter/page so it is
        // not filtered out of view; the default order is newest-first, so it lands on the first page.
        this.activeParams = {};
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

        // Swap only the inner content, keeping this element and this component instance alive — so the event-bus
        // listeners stay registered exactly once and never accumulate. The fresh pagination re-initializes itself.
        if (fresh) {
            this.el.replaceChildren(...fresh.childNodes);
        }

        ElementLoadingIndicatorUtil.remove(this.el);

        // Keep the URL and back button in sync with the applied sort, filter and page — not the elementId hint.
        const query = new URLSearchParams(this.activeParams).toString();
        window.history.pushState(null, '', `${window.location.pathname}?${query}`);
    }
}
