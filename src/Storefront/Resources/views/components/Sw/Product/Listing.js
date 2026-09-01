export default class ProductListing extends ShopwareComponent {

    static options = {
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
        this.activeListingId = window.activeNavigationId;
        this.elementId = this.el.getAttribute('data-element-id') || null;

        this.domParser = new DOMParser();

        // Create the debounced load function.
        this.debouncedLoad = this.debounce(async () => {
            const productGrid = this.el.querySelector('.sw-product-listing__grid');
            const pagination = this.el.querySelector('.sw-product-listing__pagination');
            const counter = this.el.querySelector('.sw-product-listing__counter');
            productGrid.classList.add('is--loading');

            const location = new URL(window.location);
            const params = { ...this.activeParams };
            const query = new URLSearchParams(params).toString();
            const url = `${location.protocol}//${location.host}/content/category/${this.activeListingId}?${query}`;

            const response = await fetch(url);
            const html = await response.text();
            const doc = this.domParser.parseFromString(html, 'text/html');
            const grid = doc.querySelector('.sw-product-listing__grid');
            const pagi = doc.querySelector('.sw-product-listing__pagination');
            const freshCounter = doc.querySelector('.sw-product-listing__counter');

            productGrid.replaceWith(grid);
            pagination.replaceWith(pagi);

            // Hidden below the lg breakpoint, so it is not always rendered.
            if (counter && freshCounter) {
                counter.replaceWith(freshCounter);
            }
            productGrid.classList.remove('is--loading');
        }, 200);

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
        const grid = this.el.querySelector('.sw-product-listing__grid');
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
        Shopware.off('Filter:Change', this.handleFilterChange.bind(this));
        Shopware.off('Pagination:Change', this.handlePageChange.bind(this));
        Shopware.off('LayoutSwitch:Change', this.handleLayoutChange.bind(this));
        Shopware.off('FilterSorting:Change', this.handleSortingChange.bind(this));
        Shopware.off('Filter:Remove', this.handleFilterRemove.bind(this));
    }
}
