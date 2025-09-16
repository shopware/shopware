class ListingOptions extends window.PluginBaseClass {
    static options = {
        horizontalToggleSelector: '[data-layout-horizontal]',
        appearanceSelector: '[data-listing-appearance]',
        listingCardSelector: '.product-card'
    }

    init() {
        /**
         * @type {{divider: string, default: string, free: string, grid: string}}
         * @private
         */
        this._listingVariants = {
            divider: 'is--divider',
            default: 'is--default',
            free: 'is--free',
            grid: 'is--grid',
        }

        /**
         * @type {NodeListOf<HTMLElementTagNameMap[string|*]>}
         * @private
         */
        this._listingCards = document.querySelectorAll(this.options.listingCardSelector);

        this._registerEvents();
    }

    _registerEvents() {
        this.el.querySelectorAll(this.options.horizontalToggleSelector)?.forEach((toggleEl) => {
            toggleEl.addEventListener('click', this._onToggleLayout.bind(this));
        });

        this.el.querySelector(this.options.appearanceSelector)?.addEventListener('change', this._onToggleAppearance.bind(this));
    }

    /**
     * @param event
     * @private
     */
    _onToggleLayout(event) {
        const url = new URL(window.location);
        url.searchParams.set('horizontal', event.currentTarget.getAttribute('data-layout-horizontal'))
        history.pushState(null, '', url);
        window.location.reload();
    }

    /**
     * @param event
     * @private
     */
    _onToggleAppearance(event) {
        const variant = event.target?.value;

        if (!variant) return;

        this._listingCards?.forEach((card) => {
            card.classList.remove(...Object.values(this._listingVariants));
            card.classList.add(this._listingVariants[variant]);
        });
    }
}

window.PluginManager.register('ListingOptions', ListingOptions, '[data-listing-options]');
window.PluginManager.initializePlugin('ListingOptions', {});
