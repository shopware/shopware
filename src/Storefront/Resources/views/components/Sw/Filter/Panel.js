({ Shopware, ShopwareComponent } = window);

export default class FilterPanel extends ShopwareComponent {

    static options = {
        /**
         * Defines how much filters are visible by default.
         */
        visibleFilterCount: 3,
    }

    init() {
        this.expandButton = this.el.querySelector('.sw-filter-panel__expand');

        if (!this.expandButton) {
            return;
        }

        this.expandText = this.expandButton.querySelector('.sw-filter-panel__expand-text');
        this.collapseText = this.expandButton.querySelector('.sw-filter-panel__collapse-text');
        this.filterItems = this.el.querySelectorAll('.sw-filter-item');

        this.filtersExpanded = false;

        this.registerEvents();
        this.collapseFilters();
    }

    registerEvents() {
        this.onToggleHiddenFiltersBound = this.onToggleHiddenFilters.bind(this);
        this.expandButton.addEventListener('click', this.onToggleHiddenFiltersBound);
    }

    onToggleHiddenFilters(event) {
        if (this.filtersExpanded) {
            this.collapseFilters();
        } else {
            this.expandFilters();
        }
    }

    expandFilters() {
        this.filtersExpanded = true;

        for (const filter of this.filterItems) {
            filter.classList.remove('is--hidden');
        }

        this.expandText.style.display = 'none';
        this.collapseText.style.display = 'inline-block';
    }

    collapseFilters() {
        this.filtersExpanded = false;

        for (const [index, filter] of this.filterItems.entries()) {
            if (index >= this.options.visibleFilterCount) {
                filter.classList.add('is--hidden');
            }
        }

        this.expandText.style.display = 'inline-block';
        this.collapseText.style.display = 'none';
    }

    destroy() {
        if (!this.expandButton || !this.onToggleHiddenFiltersBound) {
            return;
        }

        this.expandButton.removeEventListener('click', this.onToggleHiddenFiltersBound);
    }
}
