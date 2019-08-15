import Plugin from 'src/script/plugin-system/plugin.class';
import HttpClient from 'src/script/service/http-client.service';
import Iterator from 'src/script/helper/iterator.helper';
import DomAccess from 'src/script/helper/dom-access.helper';
import querystring from 'query-string';

export default class FilterPanelPlugin extends Plugin {

    static options = {
        dataUrl: '',
        filterUrl: '',
        params: {},
        cmsProductListingSelector: '.cms-block-product-listing',
        activeFilterContainerSelector: '.filter-panel-active-container',
        activeFilterLabelClass: 'filter-active',
        activeFilterLabelRemoveClass: 'filter-active-remove',
        activeFilterLabelPreviewClass: 'filter-active-preview',
        resetAllFilterButtonClasses: 'filter-reset-all btn btn-sm btn-outline-danger',
        resetAllFilterButtonSelector: '.filter-reset-all',
        resetAllButtonText: 'Reset all',
        loadingIndicatorClass: 'is-loading',
    };

    init() {
        this._registry = [];
        this._isFilterActive = false;
        this.httpClient = new HttpClient(window.accessKey, window.contextToken);

        this.activeFilterContainer = DomAccess.querySelector(
            this.el,
            this.options.activeFilterContainerSelector
        );
    }

    /**
     * @public
     */
    changeFilter() {
        this.buildRequest();
        this.buildLabels();
    }

    /**
     * @param filterItem
     * @public
     */
    registerFilter(filterItem) {
        this._registry.push(filterItem);
    }

    buildRequest() {
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

            if (value.length) {
                mapped[key] = value;
            }
        });

        this._isFilterActive = !!Object.keys(mapped).length;

        if (this.options.params) {
            Object.keys(this.options.params).forEach((key) => {
                mapped[key] = this.options.params[key];
            });
        }

        this.sendDataRequest(querystring.stringify(mapped));
    }

    /**
     * Build all labels for the currently active filters.
     */
    buildLabels() {
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

        if (resetButtons) {
            this._registerLabelEvents(resetButtons);
        }

        this.createResetAllButton();
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

        if (!this._isFilterActive) {
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

        this.buildRequest();
        this.buildLabels();
    }

    /**
     * Reset all active filters.
     */
    resetAllFilter() {
        this._registry.forEach((filterPlugin) => {
            filterPlugin.resetAll();
        });

        this.buildRequest();
        this.buildLabels();
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
            ${this.options.resetAllButtonText}
        </button>
        `;
    }

    /**
     * Add classes to add loading styling.
     * Prevents the user from clicking filter labels during filter request.
     */
    addLoadingIndicatorClass() {
        this.el.classList.add(this.options.loadingIndicatorClass);
    }

    /**
     * Remove loading styling classes.
     */
    removeLoadingIndicatorClass() {
        this.el.classList.remove(this.options.loadingIndicatorClass);
    }

    /**
     * Send request to get filtered product data.
     *
     * @param {String} filterParams - active filters as querystring
     */
    sendDataRequest(filterParams) {
        this.addLoadingIndicatorClass();

        this.httpClient.abort();
        this.httpClient.get(`${this.options.dataUrl}?${filterParams}`, (response) => {
            this.renderResponse(response);
            this.sendFilterRequest(filterParams);
        });
    }

    /**
     * Send request to get updated filters.
     *
     * @param {String} filterParams - active filters as querystring
     */
    sendFilterRequest(filterParams) {
        this.httpClient.abort();
        this.httpClient.get(`${this.options.filterUrl}?${filterParams}`, (response) => {
            this.validateFilters(response);
            this.removeLoadingIndicatorClass();
        });
    }

    /**
     * Inject the HTML of the filtered products to the page.
     *
     * @param {String} response - HTML of filtered product data.
     */
    renderResponse(response) {
        const listing = DomAccess.querySelector(document, this.options.cmsProductListingSelector);
        listing.innerHTML = response;
        // TODO: Use the cmsSlotReloadService for replacing and reloading the elements
        window.PluginManager.initializePlugins();
    }

    validateFilters(response) {
        this._registry.forEach((filterPlugin) => {
            filterPlugin.validate(JSON.parse(response), true);
        });
    }
}
