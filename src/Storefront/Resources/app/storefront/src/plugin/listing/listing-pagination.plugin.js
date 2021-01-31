import DomAccess from 'src/helper/dom-access.helper';
import FilterBasePlugin from 'src/plugin/listing/filter-base.plugin';
import deepmerge from 'deepmerge';

export default class ListingPaginationPlugin extends FilterBasePlugin {

    static options = deepmerge(FilterBasePlugin.options, {
        page: 1,
        limit: 24
    });

    init() {
        this._initButtons();
        this._initLimitSelection();
        this.tempPage = null;
        this.tempLimit = null;
    }

    _initButtons() {
        this.buttons = DomAccess.querySelectorAll(this.el,  '.pagination input[type=radio]', false);

        if (this.buttons) {
            this._registerButtonEvents();
        }
    }

    _initLimitSelection() {
        this.limitSelections = DomAccess.querySelectorAll(this.el,  '.product-count select', false);

        if (this.limitSelections) {
            this._registerLimitSelectionEvents();
        }
    }

    /**
     * @private
     */
    _registerButtonEvents() {
        this.buttons.forEach((radio) => {
            radio.addEventListener('change', this.onChangePage.bind(this));
        });
    }

    /**
     * @private
     */
    _registerLimitSelectionEvents() {
        this.limitSelections.forEach(select => {
            select.addEventListener('change', this.onChangeProductCount.bind(this));
        });
    }

    onChangePage(event) {
        this.tempPage = event.target.value;
        this.listing.changeListing();
        this.tempPage = null;
    }

    onChangeProductCount(event) {
        this.tempLimit = event.target.value;
        this.listing.changeListing();
        this.tempLimit = null;
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
        const result = {};

        if (this.tempPage !== null) {
            result.p = this.tempPage;
        }

        if (this.tempLimit !== null) {
            result.limit = this.tempLimit;
        }

        return result;
    }

    afterContentChange() {
        this._initButtons();
        this._initLimitSelection();
    }

    /**
     * @return {Array}
     * @public
     */
    getLabels() {
        return [];
    }
}
