export default class ProductReviews extends ShopwareComponent {
    static options = {
        contentUrl: null,
    };

    init() {
        this.domParser = new DOMParser();
        this.container = this.el.querySelector('.sw-product-reviews__results');
        this.activeParams = this.paramsFromUrl();

        // Filters are a separate element (possibly outside this one), so they reach us over the event bus.
        this.onFiltersChange = this.onFiltersChange.bind(this);
        window.Shopware.on('ReviewFilters:Change', this.onFiltersChange);

        this.bindPagination();
    }

    destroy() {
        window.Shopware.off('ReviewFilters:Change', this.onFiltersChange);
        this.unbindPagination();
    }

    bindPagination() {
        this.onPaginationClick = this.onPaginationClick.bind(this);
        this.pageLinks = this.el.querySelectorAll('.sw-pagination__link');
        this.pageLinks.forEach((el) => el.addEventListener('click', this.onPaginationClick));
    }

    unbindPagination() {
        this.pageLinks?.forEach((el) => el.removeEventListener('click', this.onPaginationClick));
    }

    onFiltersChange(params) {
        // A sort or filter change resets to the first page.
        this.activeParams = { ...params };
        this.reload();
    }

    onPaginationClick(event) {
        event.preventDefault();
        this.activeParams.p = event.currentTarget.getAttribute('data-page');
        this.reload();
    }

    paramsFromUrl() {
        const params = {};
        new URLSearchParams(window.location.search).forEach((value, key) => {
            params[key] = value;
        });

        return params;
    }

    async reload() {
        if (!this.options.contentUrl || !this.container) {
            return;
        }

        this.container.classList.add('is--loading');

        // The content route re-renders the product layout with the review params applied, unlike the
        // HTTP-cached product page. contentUrl comes from Twig path(), so it already carries the base path.
        const query = new URLSearchParams(this.activeParams).toString();
        const url = `${this.options.contentUrl}?${query}`;

        const response = await fetch(url);
        const html = await response.text();
        const fresh = this.domParser.parseFromString(html, 'text/html').querySelector('.sw-product-reviews__results');

        if (fresh) {
            this.unbindPagination();
            this.container.replaceWith(fresh);
            this.container = fresh;
            this.bindPagination();
        } else {
            this.container.classList.remove('is--loading');
        }

        // Keep the URL and back button in sync with the applied sort, filter and page.
        window.history.pushState(null, '', `${window.location.pathname}?${query}`);
    }
}
