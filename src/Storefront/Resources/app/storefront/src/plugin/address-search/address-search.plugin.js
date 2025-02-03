import Plugin from 'src/plugin-system/plugin.class';

/**
 * @package checkout
 */
export default class AddressSearchPlugin extends Plugin {

    static options = {
        searchQuerySelector: '.address-manager-list-search',
        searchContentSelector: '.address-manager-list-wrapper',
        searchItemSelector: '.address-manager-select-address',
        searchNoItemFoundSelector: '.address-manager-no-address',
        searchEmptySateSelector: '.address-manager-empty-address',
    };

    init() {
        this._registerEvents();
        this._checkEmptyState();
    }

    /**
     * registers all needed event listeners
     *
     * @private
     */
    _registerEvents() {
        this.el.querySelector(this.options.searchQuerySelector)?.addEventListener('input', this._onSearch.bind(this));
    }

    _onSearch(event) {
        const searchQuery = event.target.value.toLowerCase();

        const items = this.el.querySelector(this.options.searchContentSelector).children;

        Array.from(items).forEach(item => {
            const text = item.querySelector(this.options.searchItemSelector).textContent.toLowerCase();

            text.includes(searchQuery)
                ? item.classList.remove('d-none')
                : item.classList.add('d-none');
        });

        this._checkEmptyState(searchQuery);
    }

    _checkEmptyState(searchQuery = '') {
        const items = this.el.querySelector(this.options.searchContentSelector).children;
        const visibleListItems = Array.from(items).filter(item => !item.classList.contains('d-none'));
        const notFound = this.el.querySelector(this.options.searchNoItemFoundSelector);
        const empty = this.el.querySelector(this.options.searchEmptySateSelector);

        if (visibleListItems.length !== 0) {
            return;
        }

        notFound.classList.add('d-none');
        empty.classList.add('d-none');

        searchQuery.length === 0
            ? empty.classList.remove('d-none')
            : notFound.classList.remove('d-none');
    }
}
