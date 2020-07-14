import Plugin from 'src/plugin-system/plugin.class';
import HttpClient from 'src/service/http-client.service';
import Iterator from 'src/helper/iterator.helper';
import DomAccess from 'src/helper/dom-access.helper';
import querystring from 'query-string';
import ElementReplaceHelper from 'src/helper/element-replace.helper';
import HistoryUtil from 'src/utility/history/history.util';

export default class ListingPlugin extends Plugin {

    static options = {
        dataUrl: '',
        filterUrl: '',
        params: {},
        filterPanelSelector: '.filter-panel',
        cmsProductListingSelector: '.cms-element-product-listing',
        cmsProductListingWrapperSelector: '.cms-element-product-listing-wrapper',
        activeFilterContainerSelector: '.filter-panel-active-container',
        activeFilterLabelClass: 'filter-active',
        activeFilterLabelRemoveClass: 'filter-active-remove',
        activeFilterLabelPreviewClass: 'filter-active-preview',
        resetAllFilterButtonClasses: 'filter-reset-all btn btn-sm btn-outline-danger',
        resetAllFilterButtonSelector: '.filter-reset-all',
        loadingIndicatorClass: 'is-loading',
        loadingElementLoaderClass: 'has-element-loader',
        snippets: {
            resetAllButtonText: 'Reset all'
        }
    };

    init() {
        this._registry = [];

        this.httpClient = new HttpClient();

        this._urlFilterParams = querystring.parse(HistoryUtil.getSearch());

        this._filterPanel = DomAccess.querySelector(document, this.options.filterPanelSelector, false);
        this._filterPanelActive = !!this._filterPanel;

        // Init functionality for the filter panel
        if (this._filterPanelActive) {
            this._showResetAll = false;
            this.activeFilterContainer = DomAccess.querySelector(
                document,
                this.options.activeFilterContainerSelector
            );
        }

        this._cmsProductListingWrapper = DomAccess.querySelector(document, this.options.cmsProductListingWrapperSelector, false);
        this._cmsProductListingWrapperActive = !!this._cmsProductListingWrapper;
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
     * @public
     */
    changeListing() {
        this._buildRequest();

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
    }

    _setFilterState(filterItem) {
        if (Object.keys(this._urlFilterParams).length > 0 && typeof filterItem.setValuesFromUrl === 'function' ) {
            const stateChanged = filterItem.setValuesFromUrl(this._urlFilterParams);

            // Return if state of filter has not changed or filter panel is not active
            if(!stateChanged || !this._filterPanelActive) return;

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
     * @private
     */
    _buildRequest() {
        const filters = {};

        this._registry.forEach((filterPlugin) => {
            const values = filterPlugin.getValues();

            Object.keys(values).forEach((key) => {
                if (filters.hasOwnProperty(key)) {
                    Object.values(values[key]).forEach((value) => {
                        filters[key].push(value);
                    });
                } else {
                    filters[key] = values[key];
                }
            });
        });

        const mapped = {};
        Object.keys(filters).forEach((key) => {
            let value = filters[key];

            if (Array.isArray(value)) {
                value = value.join('|');
            }

            const string = `${value}`;
            if (string.length) {
                mapped[key] = value;
            }
        });

        if (this._filterPanelActive) {
            this._showResetAll = !!Object.keys(mapped).length;
        }

        if (this.options.params) {
            Object.keys(this.options.params).forEach((key) => {
                mapped[key] = this.options.params[key];
            });
        }

        let query = querystring.stringify(mapped);
        this.sendDataRequest(query);

        delete mapped['slots'];
        delete mapped['no-aggregations'];
        delete mapped['reduce-aggregations'];
        delete mapped['only-aggregations'];
        query = querystring.stringify(mapped);

        this._updateHistory(query);
    }

    _updateHistory(query) {
        HistoryUtil.push(HistoryUtil.getLocation().pathname, query, {});
    }

    /**
     * Build all labels for the currently active filters.
     */
    _buildLabels() {
        let labelHtml = '';

        this._registry.forEach((filterPlugin) => {
            const labels = filterPlugin.getLabels();

            if (labels.length) {
                labels.forEach((label) => {
                    labelHtml += this.getLabelTemplate(label);
                });
            }
        });

        this.activeFilterContainer.innerHTML = labelHtml;

        const resetButtons = DomAccess.querySelectorAll(
            this.activeFilterContainer,
            `.${this.options.activeFilterLabelRemoveClass}`,
            false
        );

        if (labelHtml.length) {
            this._registerLabelEvents(resetButtons);
            this.createResetAllButton();
        }
    }

    _registerLabelEvents(resetButtons) {
        Iterator.iterate(resetButtons, (label) => {
            label.addEventListener('click', () => this.resetFilter(label));
        });
    }

    /**
     * Create the button to reset all active filters.
     * Register event listener to remove a single filter.
     */
    createResetAllButton() {
        this.activeFilterContainer.insertAdjacentHTML('beforeend', this.getResetAllButtonTemplate());

        const resetAllButtonEl = DomAccess.querySelector(
            this.activeFilterContainer,
            this.options.resetAllFilterButtonSelector
        );

        resetAllButtonEl.removeEventListener('click', this.resetAllFilter.bind(this));
        resetAllButtonEl.addEventListener('click', this.resetAllFilter.bind(this));

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
            filterPlugin.reset(label.dataset.id);
        });

        this._buildRequest();
        this._buildLabels();
    }

    /**
     * Reset all active filters.
     */
    resetAllFilter() {
        this._registry.forEach((filterPlugin) => {
            filterPlugin.resetAll();
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
        <span class="${this.options.activeFilterLabelClass}">
            ${this.getLabelPreviewTemplate(label)}
            ${label.label}
            <button class="${this.options.activeFilterLabelRemoveClass}"
                    data-id="${label.id}">
                &times;
            </button>
        </span>
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
        <button class="${this.options.resetAllFilterButtonClasses}">
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
     * @param {String} filterParams - active filters as querystring
     */
    sendDataRequest(filterParams) {
        if (this._filterPanelActive) {
            this.addLoadingIndicatorClass();
        }

        if (this._cmsProductListingWrapperActive) {
            this.addLoadingElementLoaderClass();
        }

        this.httpClient.abort();
        this.httpClient.get(`${this.options.dataUrl}?${filterParams}`, (response) => {
            this.renderResponse(response);

            if (this._filterPanelActive) {
                this.removeLoadingIndicatorClass();
            }

            if (this._cmsProductListingWrapperActive) {
                this.removeLoadingElementLoaderClass();
            }
        });
    }

    /**
     * Inject the HTML of the filtered products to the page.
     *
     * @param {String} response - HTML of filtered product data.
     */
    renderResponse(response) {
        ElementReplaceHelper.replaceFromMarkup(response, this.options.cmsProductListingSelector, false);

        this._registry.forEach((item) => {
            if (typeof item.afterContentChange === 'function') {
                item.afterContentChange();
            }
        });

        // TODO: Use the cmsSlotReloadService for replacing and reloading the elements
        window.PluginManager.initializePlugins();
    }
}
