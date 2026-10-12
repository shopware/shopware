/*
 * @sw-package inventory
 */

import Plugin from 'src/plugin-system/plugin.class';
import ListingPaginationPlugin from 'src/plugin/listing/listing-pagination.plugin';
/** @deprecated tag:v6.8.0 - HttpClient is deprecated. Use native fetch API instead. */
import HttpClient from 'src/service/http-client.service';
import ElementReplaceHelper from 'src/helper/element-replace.helper';
import Debouncer from 'src/helper/debouncer.helper';

export default class ListingPlugin extends Plugin {

    static options = {
        dataUrl: '',
        filterUrl: '',
        params: {},
        filterPanelSelector: '.filter-panel',
        cmsProductListingSelector: '.cms-element-product-listing',
        cmsProductListingWrapperSelector: '.cms-element-product-listing-wrapper',
        cmsProductListingResultsSelector: '.js-listing-wrapper',
        activeFilterContainerSelector: '.filter-panel-active-container',
        activeFilterLabelClasses: 'filter-active btn',
        activeFilterLabelSelector: '.filter-active',
        activeFilterLabelPreviewClass: 'filter-active-preview',
        resetAllFilterButtonClasses: 'filter-reset-all btn btn-outline-danger',
        resetAllFilterButtonSelector: '.filter-reset-all',
        loadingIndicatorClass: 'is-loading',
        loadingElementLoaderClass: 'has-element-loader',
        ariaLiveSelector: '.filter-panel-aria-live',
        ariaLiveUpdates: true,
        disableEmptyFilter: false,
        snippets: {
            resetAllButtonText: 'Reset all',
            resetAllFiltersAriaLabel: 'Reset all filters',
            removeFilterAriaLabel: 'Remove filter',
        },
        //if the window should be scrolled to top of to the listingWrapper element
        scrollTopListingWrapper: true,
        // how much px the scrolling should be offset
        scrollOffset: 15,
        // Skip popstate handling for hash-only changes (fixes anchor links)
        // Set to false to disable this fix for testing
        ignoreHashOnlyPopstate: true,
    };

    init() {
        this._registry = [];

        /** @deprecated tag:v6.8.0 - HttpClient is deprecated. Use native fetch API instead. */
        this.httpClient = new HttpClient();

        this._urlFilterParams = Object.fromEntries(new URLSearchParams(window.location.search).entries());

        this._filterPanel = document.querySelector(this.options.filterPanelSelector);
        this._filterPanelActive = !!this._filterPanel;

        // Init functionality for the filter panel
        if (this._filterPanelActive) {
            this._showResetAll = false;
            this.activeFilterContainer = document.querySelector(this.options.activeFilterContainerSelector,
            );
            this.ariaLiveContainer = document.querySelector(this.options.ariaLiveSelector);
        }

        this._cmsProductListingWrapper = document.querySelector(this.options.cmsProductListingWrapperSelector);
        this._cmsProductListingWrapperActive = !!this._cmsProductListingWrapper;

        this._allFiltersInitializedDebounce = Debouncer.debounce(this.sendDisabledFiltersRequest.bind(this), 100);

        // Track current path for hash-only popstate detection
        this._lastPathWithoutHash = this._getPathWithoutHash();

        this._registerEvents();
    }

    /**
     * @public
     */
    refreshRegistry() {
        // get only the registrations which are visible in the document
        const visibleRegistrations = this._registry.filter((entry) => document.body.contains(entry.el));

        // reinitialize the listing plugin
        this.init();

        // replace empty registry with existing visible registries
        this._registry = visibleRegistrations;

        // initialize plugins which are not registered yet
        window.PluginManager.initializePlugins();
    }

    /**
     * @param pushHistory
     * @param overrideParams
     * @public
     */
    changeListing(pushHistory = true, overrideParams = {}) {
        this._buildRequest(pushHistory, overrideParams);

        if (this._filterPanelActive) {
            this._buildLabels();
        }
    }

    /**
     * @param filterItem
     * @public
     */
    registerFilter(filterItem) {
        this._registry.push(filterItem);

        this._setFilterState(filterItem);

        if (this.options.disableEmptyFilter) {
            this._allFiltersInitializedDebounce();
        }
    }

    _setFilterState(filterItem) {
        if (Object.keys(this._urlFilterParams).length > 0 && typeof filterItem.setValuesFromUrl === 'function' ) {
            const stateChanged = filterItem.setValuesFromUrl(this._urlFilterParams);

            // Return if state of filter has not changed or filter panel is not active
            if (!stateChanged || !this._filterPanelActive) return;

            this._showResetAll = true;
            this._buildLabels();
        }
    }

    /**
     * @param filterItem
     * @public
     */
    deregisterFilter(filterItem) {
        this._registry = this._registry.filter((item) => {
            return (item !== filterItem);
        });
    }

    /**
     * Calls a method on a registered filter plugin without letting a broken third-party
     * plugin break the entire listing update. Returns `fallback` if the method is missing
     * or throws.
     *
     * @private
     */
    _callFilterPlugin(filterPlugin, method, fallback, ...args) {
        if (typeof filterPlugin[method] !== 'function') {
            return fallback;
        }

        try {
            return filterPlugin[method](...args);
        } catch (error) {
            console.warn(`Listing filter plugin threw from ${method}(); skipping.`, error);

            return fallback;
        }
    }

    /**
     * The built-in pagination plugin is authoritative for the single-valued `p` parameter.
     * It is therefore merged last, so the last-value-wins rule in `_mapFilters()` resolves to
     * its page regardless of the order in which third-party filters happened to register.
     *
     * @private
     */
    _getPrioritisedRegistry() {
        const others = [];
        const pagination = [];

        this._registry.forEach((filterPlugin) => {
            (filterPlugin instanceof ListingPaginationPlugin ? pagination : others).push(filterPlugin);
        });

        return [...others, ...pagination];
    }

    /**
     * Merges the values reported by every registered filter plugin into a single map.
     * Third-party plugins that throw from `getValues()` or return malformed shapes are
     * skipped instead of breaking the entire listing update.
     *
     * @private
     */
    _fetchValuesOfRegisteredFilters() {
        const filters = {};

        this._getPrioritisedRegistry().forEach((filterPlugin) => {
            const values = this._callFilterPlugin(filterPlugin, 'getValues', null);

            if (!values) {
                return;
            }

            Object.keys(values).forEach((key) => {
                const value = values[key];
                let list;

                if (Array.isArray(value)) {
                    list = value;
                } else if (value !== null && typeof value === 'object') {
                    list = Object.values(value);
                } else if (value !== null && value !== undefined) {
                    list = [value];
                } else {
                    list = [];
                }

                if (!Object.prototype.hasOwnProperty.call(filters, key)) {
                    filters[key] = [];
                }

                list.forEach((entry) => {
                    // An inactive filter reports an empty string (e.g. an unchecked boolean
                    // filter). Keeping it would produce separator-only values such as `|` once
                    // the list is pipe-joined below, which the backend reads as an active filter.
                    if (entry !== null && entry !== undefined && entry !== '') {
                        filters[key].push(entry);
                    }
                });
            });
        });

        return filters;
    }

    /**
     * Serialises the merged filter map into the request query parameter map.
     *
     * Note: the `singleValuedKeys` set tracks query parameters that the listing backend
     * reads as a single value, either via `PagingListingProcessor` / `SortingListingProcessor`
     * under `Core/Content/Product/SalesChannel/Listing/Processor/`, or via the scalar casts in
     * the handlers under `Core/Content/Product/SalesChannel/Listing/Filter/` on the PHP side.
     * Pipe-joining them produces either invalid queries like `p=1|2` (400 responses on
     * `/widgets/cms/navigation/*`) or silently wrong filters like `rating=3|4`, which casts to
     * `3`. Keep this in sync when the backend adds new single-valued listing params.
     *
     * @private
     */
    _mapFilters(filters) {
        const singleValuedKeys = new Set([
            'p',
            'order',
            'limit',
            'rating',
            'shipping-free',
            'min-price',
            'max-price',
        ]);
        const mapped = {};

        Object.keys(filters).forEach((key) => {
            const value = filters[key];
            let resolved;

            if (Array.isArray(value)) {
                if (value.length === 0) {
                    return;
                }

                if (singleValuedKeys.has(key)) {
                    const last = value[value.length - 1];
                    resolved = last === null || last === undefined ? '' : String(last);
                } else {
                    resolved = value.join('|');
                }
            } else if (value !== null && value !== undefined) {
                resolved = singleValuedKeys.has(key) ? String(value) : value;
            } else {
                return;
            }

            if (`${resolved}`.length) {
                mapped[key] = resolved;
            }
        });

        return mapped;
    }

    /**
     * @param pushHistory
     * @param overrideParams
     * @private
     */
    _buildRequest(pushHistory = true, overrideParams = {}) {
        const filters = this._fetchValuesOfRegisteredFilters();
        const mapped = this._mapFilters(filters);

        if (this._filterPanelActive) {
            this._showResetAll = !!Object.keys(mapped).length;
        }

        if (this.options.params) {
            Object.keys(this.options.params).forEach((key) => {
                mapped[key] = this.options.params[key];
            });
        }

        Object.entries(overrideParams).forEach(([paramKey, paramValue]) => {
            mapped[paramKey] = paramValue;
        });

        let queryParams = new URLSearchParams(mapped);
        this.sendDataRequest(queryParams);

        delete mapped['slots'];
        delete mapped['no-aggregations'];
        delete mapped['reduce-aggregations'];
        delete mapped['only-aggregations'];
        queryParams = new URLSearchParams(mapped);

        if (pushHistory) {
            this._updateHistory(queryParams);
        }

        if (this.options.scrollTopListingWrapper) {
            this._scrollTopOfListing();
        }
    }

    _scrollTopOfListing() {
        const elemRect = this._cmsProductListingWrapper.getBoundingClientRect();
        if (elemRect.top >= 0) {
            return;
        }

        const top = elemRect.top + window.scrollY - this.options.scrollOffset;
        window.scrollTo({
            top: top,
            behavior: 'smooth',
        });
    }

    /**
     * @private
     * @returns {URLSearchParams} 
     */
    _getDisabledFiltersParamsFromParams(params) {
        const filterParams = Object.assign({}, {'only-aggregations': 1, 'reduce-aggregations': 1}, params);
        delete filterParams['p'];
        delete filterParams['order'];
        delete filterParams['no-aggregations'];

        return new URLSearchParams(filterParams);
    }
    /**
     * Update the browser history.
     *
     * @private
     * @param {URLSearchParams} queryParams
     */
    _updateHistory(queryParams) {
        const url = this._buildUrl(window.location.pathname, queryParams);
        window.history.pushState({}, '', url);

        // Update tracked path for hash-only popstate detection
        this._lastPathWithoutHash = this._getPathWithoutHash();
    }

    /**
     * Build all labels for the currently active filters.
     */
    _buildLabels() {
        let labelHtml = '';

        this._registry.forEach((filterPlugin) => {
            const labels = this._callFilterPlugin(filterPlugin, 'getLabels', []);

            if (labels.length) {
                labels.forEach((label) => {
                    labelHtml += this.getLabelTemplate(label);
                });
            }
        });

        this.activeFilterContainer.innerHTML = labelHtml;

        const resetButtons = this.activeFilterContainer.querySelectorAll(this.options.activeFilterLabelSelector);

        if (labelHtml.length) {
            this._registerLabelEvents(resetButtons);
            this.createResetAllButton();
        }
    }

    _registerLabelEvents(resetButtons) {
        resetButtons.forEach((label) => {
            label.addEventListener('click', () => this.resetFilter(label));
        });
    }

    /**
     * Create the button to reset all active filters.
     * Register event listener to remove a single filter.
     */
    createResetAllButton() {
        this.activeFilterContainer.insertAdjacentHTML('beforeend', this.getResetAllButtonTemplate());

        const resetAllButtonEl = this.activeFilterContainer.querySelector(this.options.resetAllFilterButtonSelector,
        );

        if (!this._boundResetAllFilter) {
            this._boundResetAllFilter = this.resetAllFilter.bind(this);
        }

        resetAllButtonEl.removeEventListener('click', this._boundResetAllFilter);
        resetAllButtonEl.addEventListener('click', this._boundResetAllFilter);

        if (!this._showResetAll) {
            resetAllButtonEl.remove();
        }
    }

    /**
     * Remove the given filter from the filter map.
     *
     * @param {Object} label
     */
    resetFilter(label) {
        this._registry.forEach((filterPlugin) => {
            this._callFilterPlugin(filterPlugin, 'reset', undefined, label.dataset.id);
        });

        this._buildRequest();
        this._buildLabels();
    }

    /**
     * Reset all active filters.
     */
    resetAllFilter() {
        this._registry.forEach((filterPlugin) => {
            this._callFilterPlugin(filterPlugin, 'resetAll', undefined);
        });

        this._buildRequest();
        this._buildLabels();
    }

    /**
     * Template for an active filter label.
     *
     * @param {Object} label
     * @returns {string}
     */
    getLabelTemplate(label) {
        return `
        <button
            class="${this.options.activeFilterLabelClasses}"
            data-id="${label.id}"
            title="${this.options.snippets.removeFilterAriaLabel}: ${label.label}"
            aria-label="${this.options.snippets.removeFilterAriaLabel}: ${label.label}">
            ${this.getLabelPreviewTemplate(label)}
            ${label.label}
            <span aria-hidden="true" class="ms-1 fs-4">&times;</span>
        </button>
        `;
    }

    getLabelPreviewTemplate(label) {
        const previewClass = this.options.activeFilterLabelPreviewClass;

        if (label.previewHex) {
            return `
                <span class="${previewClass}" style="background-color: ${label.previewHex};"></span>
            `;
        }

        if (label.previewImageUrl) {
            return `
                <span class="${previewClass}" style="background-image: url('${label.previewImageUrl}');"></span>
            `;
        }

        return '';
    }

    getResetAllButtonTemplate() {
        return `
        <button class="${this.options.resetAllFilterButtonClasses}" aria-label="${this.options.snippets.resetAllFiltersAriaLabel}">
            ${this.options.snippets.resetAllButtonText}
        </button>
        `;
    }

    /**
     * Add classes to add loading styling.
     * Prevents the user from clicking filter labels during filter request.
     */
    addLoadingIndicatorClass() {
        this._filterPanel.classList.add(this.options.loadingIndicatorClass);
    }

    /**
     * Remove loading styling classes.
     */
    removeLoadingIndicatorClass() {
        this._filterPanel.classList.remove(this.options.loadingIndicatorClass);
    }

    /**
     * Add classes to add loading styling for product listing
     */
    addLoadingElementLoaderClass() {
        this._cmsProductListingWrapper.classList.add(this.options.loadingElementLoaderClass);
    }

    /**
     * Remove loading styling classes for product listing
     */
    removeLoadingElementLoaderClass() {
        this._cmsProductListingWrapper.classList.remove(this.options.loadingElementLoaderClass);
    }

    /**
     * Send request to get filtered product data.
     *
     * @param {URLSearchParams} filterParams - active filters as querystring
     */
    sendDataRequest(filterParams) {
        if (this._filterPanelActive) {
            this.addLoadingIndicatorClass();
        }

        if (this._cmsProductListingWrapperActive) {
            this.addLoadingElementLoaderClass();
        }

        if (this.options.disableEmptyFilter) {
            this.sendDisabledFiltersRequest();
        }

        const url = this._buildUrl(this.options.dataUrl, filterParams);

        fetch(url, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
        })
            .then((response) => {
                if (response.ok) {
                    return response.text();
                }

                const error = new Error('Could not fetch listing data.');
                error.response = response;

                throw error;
            })
            .then((response) => {
                this.renderResponse(response);
            })
            .catch((error) => {
                if (error.response?.status === 403) {
                    const loginPageUrl = this._getLoginPageUrl(filterParams);

                    if (loginPageUrl) {
                        this._navigateTo(loginPageUrl);
                    }

                    return;
                }

                throw error;
            })
            .finally(() => {
                if (this._filterPanelActive) {
                    this.removeLoadingIndicatorClass();
                    this._updateAriaLive();
                }

                if (this._cmsProductListingWrapperActive) {
                    this.removeLoadingElementLoaderClass();
                }
            });
    }

    /**
     * Send request to get disabled filters data
     */
    sendDisabledFiltersRequest() {
        const filters = this._fetchValuesOfRegisteredFilters();
        const mapped = this._mapFilters(filters);
        if (this.options.params) {
            Object.keys(this.options.params).forEach((key) => {
                mapped[key] = this.options.params[key];
            });
        }

        // unset the debounce function after first execution
        this._allFiltersInitializedDebounce = () => {};

        const filterParams = this._getDisabledFiltersParamsFromParams(mapped);
        const url = this._buildUrl(this.options.filterUrl, filterParams);

        fetch(url, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
        })
            .then(response => response.json())
            .then(filter => {
                this._registry.forEach((item) => {
                    if (typeof item.refreshDisabledState === 'function') {
                        item.refreshDisabledState(filter, filterParams);
                    }
                });
            });
    }

    /**
     * Inject the HTML of the filtered products to the page.
     *
     * @param {String} response - HTML of filtered product data.
     */
    renderResponse(response) {
        ElementReplaceHelper.replaceFromMarkup(response, this.options.cmsProductListingSelector);

        this._registry.forEach((item) => {
            if (typeof item.afterContentChange === 'function') {
                item.afterContentChange();
            }
        });

        window.PluginManager.initializePlugins();

        this.$emitter.publish('Listing/afterRenderResponse', { response });
    }

    /**
     * Update the aria-live region with the current listing results.
     *
     * @private
     */
    _updateAriaLive() {
        if (!this.options.ariaLiveUpdates) {
            return;
        }

        if (!this.ariaLiveContainer) {
            return;
        }

        const listingResultsEl = this.el.querySelector(this.options.cmsProductListingResultsSelector);
        this.ariaLiveContainer.innerHTML = listingResultsEl.dataset.ariaLiveText;
    }

    /**
     * @private
     */
    _registerEvents() {
        window.onpopstate = this._onWindowPopstate.bind(this);
    }

    /**
     * @private
     */
    _onWindowPopstate() {
        // Skip if this is just an anchor/hash navigation (not a filter/page change)
        // Browsers fire popstate for hash changes
        if (this.options.ignoreHashOnlyPopstate && this._lastPathWithoutHash) {
            const currentPathWithoutHash = this._getPathWithoutHash();
            if (this._lastPathWithoutHash === currentPathWithoutHash) {
                return;
            }
        }

        this.refreshRegistry();

        this._registry.forEach(filterItem => {
            if (Object.keys(this._urlFilterParams).length === 0) {
                this._urlFilterParams.p = 1;
            }
            this._setFilterState(filterItem);
        });

        if (this.options.disableEmptyFilter) {
            this._allFiltersInitializedDebounce();
        }

        this.changeListing(false);
    }
    /**
     * Get current path without hash (pathname + search).
     * Used for hash-only popstate detection (Safari/Firefox anchor link fix).
     *
     * @private
     * @return {string}
     */
    _getPathWithoutHash() {
        return window.location.pathname + window.location.search;
    }

    /**
     * @private
     * @param {string} pathname
     * @param {URLSearchParams} queryParams
     * @param {string} [base]
     * @return {string}
     */
    _buildUrl(pathname, queryParams, base = window.location.origin) {
        const url = new URL(pathname, base);

        if (queryParams.size > 0) {
            queryParams.forEach((value, key) => {
                url.searchParams.append(key, value);
            });
        }

        return url.toString();
    }

    /**
     * @private
     * @param {URLSearchParams} filterParams
     * @return {string|null}
     */
    _getLoginPageUrl(filterParams) {
        const loginPageUrl = window.router?.['frontend.account.login.page'];
        const parameters = new URLSearchParams();

        if (!loginPageUrl) {
            return null;
        }

        if (!window.activeRoute) {
            return loginPageUrl;
        }

        parameters.set('redirectTo', window.activeRoute);
        parameters.set('redirectParameters', JSON.stringify(this._getLoginRedirectParameters(filterParams)));

        return `${loginPageUrl}?${parameters.toString()}`;
    }

    /**
     * @private
     * @param {URLSearchParams} filterParams
     * @return {Object}
     */
    _getLoginRedirectParameters(filterParams) {
        let routeParameters = {};

        try {
            routeParameters = JSON.parse(window.activeRouteParameters || '{}');
        } catch {
            routeParameters = {};
        }

        return {
            ...routeParameters,
            ...Object.fromEntries(filterParams.entries()),
        };
    }

    /**
     * @private
     */
    _navigateTo(url) {
        window.location.href = url;
    }
}
