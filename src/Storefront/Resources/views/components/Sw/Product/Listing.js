({ Shopware, ShopwareComponent } = window);

class ProductListing extends ShopwareComponent {

    static selector = '[data-component="ProductListing"]';

    static options = {
        pageParamName: 'p',
        layoutParamName: 'layout',
        sortingParamName: 'order',
        layoutGridClasses: {
            horizontal: ['columns-1'],
            default: ['columns-sm-2', 'columns-lg-2', 'columns-xl-3', 'columns-4'],
        },
    };

    init() {
        this.activeParams = {};

        // Create the debounced load function.
        this.debouncedLoad = this.debounce(() => {
            const url = new URL(window.location);
            const query = new URLSearchParams(this.activeParams).toString();

            window.location.href = `${url.pathname}${query.length > 0 ? '?' : ''}${query}`;
        }, 1000);

        this.getStateFromUrl();
        this.registerEvents();
    }

    registerEvents() {
        Shopware.on('Filter:Change', this.handleFilterChange.bind(this));
        Shopware.on('Pagination:Change', this.handlePageChange.bind(this));
        Shopware.on('LayoutSwitch:Change', this.handleLayoutChange.bind(this));
        Shopware.on('FilterSorting:Change', this.handleSortingChange.bind(this));
        Shopware.on('Filter:Remove', this.handleFilterRemove.bind(this));
    }

    handleFilterChange({ paramName, value, activeOptions }) {
        // Multiselect Filters
        if (activeOptions && Array.isArray(activeOptions)) {
            value = activeOptions.join('|');
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
        const grid = this.el.querySelector('.product-listing__grid');
        const productCards = grid.querySelectorAll('.product-card');
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
}

Shopware.registerComponent('ProductListing', ProductListing);