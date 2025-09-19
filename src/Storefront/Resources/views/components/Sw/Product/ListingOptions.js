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

        console.log('this._listGrid', this._listGrid);

        this._registerEvents();
    }

    _registerEvents() {
        this._layoutButtons?.forEach((toggleEl) => {
            toggleEl.addEventListener('click', this._onToggleLayout.bind(this));
        });
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

        this._listingCards?.forEach((card) => {
            card.classList.remove(...Object.values(this._listingVariants));
            card.classList.add(this._listingVariants[layout]);
            event.currentTarget.classList.add('is--active');
        });

        if (layout === 'horizontal') {
            this._listGrid.classList.remove('columns-4');
            this._listGrid.classList.add('columns-1');
        } else if (layout === 'default') {
            this._listGrid.classList.remove('columns-1');
            this._listGrid.classList.add('columns-4');
        }

        url.searchParams.set('layout', layout);
        history.pushState(null, '', url);
    }
}

window.PluginManager.register('ListingOptions', ListingOptions, '[data-listing-options]');
window.PluginManager.initializePlugin('ListingOptions', {});
