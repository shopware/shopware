class ListingOptions extends window.PluginBaseClass {
    static options = {
        horizontalToggleSelector: '[data-layout]',
        appearanceSelector: '[data-listing-appearance]',
    }

    init() {
        /**
         * @private
         */
        this._listingVariants = {
            horizontal: 'is--layout-horizontal',
            default: 'is--layout-default',
        }

        /**
         * @type {NodeListOf<HTMLElementTagNameMap[string|*]>}
         * @private
         */
        this._listingCards = document.querySelectorAll('.product-card');

        /**
         * @type {Element}
         * @private
         */
        this._listGrid = document.querySelector('.product-listing__grid');

        /**
         * @type {NodeListOf<HTMLButtonElement>}
         * @private
         */
        this._layoutButtons = this.el.querySelectorAll('[data-layout]');

        this._hiddenFilterToggle = this.el.querySelector('.sw-filter-panel__expand');

        this._filtersExpanded = false;

        this._registerEvents();
    }

    _registerEvents() {
        this._layoutButtons?.forEach((toggleEl) => {
            toggleEl.addEventListener('click', this._onToggleLayout.bind(this));
        });

        this._hiddenFilterToggle.addEventListener('click', this._onToggleHiddenFilters.bind(this))
    }

    /**
     * @param event
     * @private
     */
    _onToggleLayout(event) {
        const url = new URL(window.location);
        const layout = event.currentTarget.getAttribute('data-layout');

        this._layoutButtons?.forEach((button) => {
            button.classList.remove('is--active');
        });

        event.currentTarget.classList.add('is--active');
        this._listGrid.classList.add('is--layout-transition');

        setTimeout(() => {
            this._listingCards?.forEach((card) => {
                card.classList.remove(...Object.values(this._listingVariants));
                card.classList.add(this._listingVariants[layout]);
            });

            if (layout === 'horizontal') {
                this._listGrid.classList.remove('columns-4');
                this._listGrid.classList.add('columns-1');
            } else if (layout === 'default') {
                this._listGrid.classList.remove('columns-1');
                this._listGrid.classList.add('columns-4');
            }

            this._listGrid.classList.remove('is--layout-transition');
        }, 200);

        url.searchParams.set('layout', layout);
        history.pushState(null, '', url);
    }

    _onToggleHiddenFilters(event) {
        const hiddenFilters = this.el.querySelectorAll('.sw-filter-multi-select.is--hidden');
        const buttonText = event.currentTarget.querySelector('.sw-filter-panel__expand-text')

        if (this._filtersExpanded) {
            for (const filter of hiddenFilters) {
                filter.style.display = 'none';
            }

            buttonText.innerText = 'More filters';
            this._filtersExpanded = false;

            return;
        }

        buttonText.innerText = 'Less filters';
        this._filtersExpanded = true;

        for (const filter of hiddenFilters) {
            filter.style.display = 'block';
        }
    }
}

window.PluginManager.register('ListingOptions', ListingOptions, '[data-listing-options]');
window.PluginManager.initializePlugin('ListingOptions', {});
