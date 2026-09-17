export default class ProductListing extends ShopwareComponent {

    static options = {
        contentUrl: null,
        pageParamName: 'p',
        layoutParamName: 'listingLayout',
        sortingParamName: 'order',
        layoutGridClasses: {
            horizontal: ['columns-1'],
            default: ['columns-xs-1', 'columns-sm-2', 'columns-md-3', 'columns-lg-4', 'columns-xl-4'],
        },
    };

    init() {
        this.activeParams = {};

        this.domParser = new DOMParser();

        // Create the debounced load function.
        this.debouncedLoad = this.debounce(async () => {
            this.el.classList.add('is--loading');

            // contentUrl (Twig path()) already carries the base path and elementId; add the runtime params.
            const url = new URL(this.options.contentUrl, window.location.origin);
            Object.entries(this.activeParams).forEach(([key, value]) => url.searchParams.set(key, value));

            const response = await fetch(url);
            const html = await response.text();
            const fresh = this.domParser
                .parseFromString(html, 'text/html')
                .querySelector(`[data-element-id="${this.el.dataset.elementId}"]`);

            // Replacing the element re-initializes it; the component system rebinds on the fresh node.
            if (fresh) {
                this.el.replaceWith(fresh);
            } else {
                this.el.classList.remove('is--loading');
            }
        }, 200);

        this.onFilterChange = this.handleFilterChange.bind(this);
        this.onPageChange = this.handlePageChange.bind(this);
        this.onLayoutChange = this.handleLayoutChange.bind(this);
        this.onSortingChange = this.handleSortingChange.bind(this);
        this.onFilterRemove = this.handleFilterRemove.bind(this);

        this.getStateFromUrl();
        this.registerEvents();
    }

    registerEvents() {
        Shopware.on('Filter:Change', this.onFilterChange);
        Shopware.on('Pagination:Change', this.onPageChange);
        Shopware.on('LayoutSwitch:Change', this.onLayoutChange);
        Shopware.on('FilterSorting:Change', this.onSortingChange);
        Shopware.on('Filter:Remove', this.onFilterRemove);
    }

    handleFilterChange({ paramName, value, activeOptions, removedOptions }) {

        if (activeOptions || removedOptions) {
            const currentOptions = this.activeParams[paramName] ? this.activeParams[paramName].split('|') : [];

            // Multiselect Filter - Remove options.
            if (removedOptions && removedOptions.length > 0) {
                for (const option of removedOptions) {
                    if (currentOptions.includes(option)) {
                        currentOptions.splice(currentOptions.indexOf(option), 1);
                    }
                }
            }

            // Multiselect Filter - Add options.
            if (activeOptions && activeOptions.length > 0) {
                for (const option of activeOptions) {
                    if (!currentOptions.includes(option)) {
                        currentOptions.push(option);
                    }
                }
            }

            value = currentOptions.join('|');
        }

        // Delete the filter parameter if it is empty.
        if (value === null ||
            value === undefined ||
            value === '' ||
            value === false ||
            value.length === 0) {
            delete this.activeParams[paramName];
        } else {
            this.activeParams[paramName] = value;
        }

        // If the filter result changes, reset the page to 1.
        this.activeParams[this.options.pageParamName] = 1;

        this.updateHistory();
        this.loadListing();
    }

    handleFilterRemove({ paramName, option }) {
        let filter = this.activeParams[paramName];

        if (!filter) {
            return;
        }

        if (option) {
            filter = filter.split('|').filter(item => item !== option);
            filter = filter.join('|');

            if (filter.length === 0) {
                delete this.activeParams[paramName];
            } else {
                this.activeParams[paramName] = filter;
            }
        } else {
            delete this.activeParams[paramName];
        }

        this.updateHistory();
        this.loadListing();
    }

    handlePageChange(page) {
        this.activeParams[this.options.pageParamName] = page;
        this.updateHistory();
        this.loadListing();
    }

    handleLayoutChange(name, layout) {
        this.activeParams[name] = layout;
        this.changeLayout(layout);
    }

    handleSortingChange(sorting) {
        this.activeParams[this.options.sortingParamName] = sorting;
        this.updateHistory();
        this.loadListing();
    }

    getStateFromUrl() {
        const urlParams = new URLSearchParams(window.location.search);
        const params = Object.fromEntries(urlParams.entries());
        this.activeParams = params;
    }

    updateHistory() {
        const url = new URL(window.location);
        const query = new URLSearchParams(this.activeParams).toString();

        history.pushState(null, '', `${url.pathname}?${query}`);
    }

    loadListing() {
        this.debouncedLoad();
    }

    changeLayout(layout) {
        const gridContainer = this.el.querySelector('.sw-product-listing__grid');
        const grid = gridContainer.querySelector('.sw-grid-container__inner');
        const productCards = grid.querySelectorAll('.sw-product-card');
        const gridClasses = this.options.layoutGridClasses;
        const layoutClasses = Object.keys(this.options.layoutGridClasses).map(layout => `is--layout-${layout}`);

        grid.classList.add('is--layout-transition');

        setTimeout(() => {
            productCards.forEach((card) => {
                card.classList.remove(...layoutClasses);
                card.classList.add(`is--layout-${layout}`);
            });

            const removeGridClasses = Object.keys(gridClasses).map(layoutName => {
                if (layoutName !== layout) {
                    return gridClasses[layoutName];
                }
            });

            grid.classList.remove(...removeGridClasses.flat());
            grid.classList.add(...gridClasses[layout]);
            grid.classList.remove('is--layout-transition');

            this.activeParams[this.options.layoutParamName] = layout;
            this.updateHistory();
        }, 200);
    }

    destroy() {
        Shopware.off('Filter:Change', this.onFilterChange);
        Shopware.off('Pagination:Change', this.onPageChange);
        Shopware.off('LayoutSwitch:Change', this.onLayoutChange);
        Shopware.off('FilterSorting:Change', this.onSortingChange);
        Shopware.off('Filter:Remove', this.onFilterRemove);
    }
}
