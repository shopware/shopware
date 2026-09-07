/*
 * @sw-package inventory
 */

import FilterBasePlugin from 'src/plugin/listing/filter-base.plugin';
import deepmerge from 'deepmerge';

export default class ListingPaginationPlugin extends FilterBasePlugin {

    static options = deepmerge(FilterBasePlugin.options, {
        page: 1,
        paginationItemSelector: '.pagination .page-link',
        paginationNavSelector: '.pagination-nav',
    });

    init() {
        this._initButtons();
        this.tempValue = null;
        this._pageChanged = false;
    }

    _initButtons() {
        this.buttons = this.el.querySelectorAll(this.options.paginationItemSelector);

        if (this.buttons) {
            this._registerButtonEvents();
        }
    }

    /**
     * @private
     */
    _registerButtonEvents() {
        this.buttons.forEach((button) => {
            button.addEventListener('click', this.onChangePage.bind(this));
        });
    }

    /**
     * @param {PointerEvent} event
     */
    onChangePage(event) {
        event.preventDefault();

        this.tempValue = this._getPageNumber(event.currentTarget.dataset.page);
        this._saveFocusState(event.currentTarget);

        this._pageChanged = true;
        this.listing.changeListing();

        this._updateCanonicalUrl(this.tempValue);

        this.tempValue = null;
    }

    /**
     * Save the current pagination item focus state.
     * The focus state is resumed after the listing has been updated.
     *
     * @param {HTMLElement} clickedPaginationEl
     * @returns {void}
     * @private
     */
    _saveFocusState(clickedPaginationEl) {
        let paginationLocation = clickedPaginationEl.closest(this.options.paginationNavSelector).dataset.paginationLocation;

        // If scroll to top option is true in the `Listing` plugin, we always focus the top pagination
        if (this.listing.options.scrollTopListingWrapper) {
            paginationLocation = 'top';
        }

        window.focusHandler.saveFocusState('listing-pagination', `[data-pagination-location="${paginationLocation}"] [data-focus-id="${clickedPaginationEl.dataset.focusId}"]`);
    }

    /**
     * Resume the previously saved focus state.
     *
     * @returns {void}
     * @private
     */
    _resumeFocusState() {
        window.focusHandler.resumeFocusState('listing-pagination', { preventScroll: true });
    }

    /**
     * @public
     */
    reset() {
    }

    /**
     * @public
     */
    resetAll() {
    }

    /**
     * @return {Object}
     * @public
     */
    getValues() {
        if (this.tempValue !== null) {
            return { p: this.tempValue };
        }
        return { p: 1 };
    }

    afterContentChange() {
        this._initButtons();

        if (this.buttons && this._pageChanged) {
            this._resumeFocusState();
        }

        this._pageChanged = false;
    }

    /**
     * @return {Array}
     * @public
     */
    getLabels() {
        return [];
    }

    /**
     * @param {Object} params
     * @returns {boolean}
     */
    setValuesFromUrl(params) {
        let stateChanged = false;
        this.tempValue = 1;

        if (params.p) {
            const pageNumber = this._getPageNumber(params.p);
            if (pageNumber !== this.tempValue) {
                this.tempValue = pageNumber;
                stateChanged = true;
            }
        }

        return stateChanged;
    }

    /**
     * Update the canonical URL with the new page number.
     * @param {number} newPageNumber
     * @private
     */
    _updateCanonicalUrl(newPageNumber) {
        const canonicalMetaTag = document.querySelector('link[rel="canonical"]');
        if (canonicalMetaTag?.href) {
            const canonicalUrl = new URL(canonicalMetaTag.href);
            if (newPageNumber > 1) {
                canonicalUrl.searchParams.set('p', newPageNumber);
            } else {
                canonicalUrl.searchParams.delete('p');
            }
            canonicalMetaTag.href = canonicalUrl.href;
        }
    }

    /**
     * @param {string} page
     * @returns {number}
     * @private
     */
    _getPageNumber(page) {
        const pageNumber = parseInt(page, 10);

        return Number.isInteger(pageNumber) ? pageNumber : 1;
    }
}
