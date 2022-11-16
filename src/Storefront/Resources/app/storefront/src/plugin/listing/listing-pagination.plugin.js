/*
 * @package inventory
 */

import DomAccess from 'src/helper/dom-access.helper';
import FilterBasePlugin from 'src/plugin/listing/filter-base.plugin';
import deepmerge from 'deepmerge';

export default class ListingPaginationPlugin extends FilterBasePlugin {

    static options = deepmerge(FilterBasePlugin.options, {
        page: 1,
    });

    init() {
        this._initButtons();
        this.tempValue = null;
    }

    _initButtons() {
        this.buttons = DomAccess.querySelectorAll(this.el,  '.pagination input[type=radio]', false);

        if (this.buttons) {
            this._registerButtonEvents();
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

    onChangePage(event) {
        this.tempValue = event.target.value;
        this.listing.changeListing();
        this.tempValue = null;
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
    }

    /**
     * @return {Array}
     * @public
     */
    getLabels() {
        return [];
    }

    setValuesFromUrl(params) {
        let stateChanged = false;
        this.tempValue = 1;

        if (params.p && parseInt(params.p) !== parseInt(this.tempValue)) {
            this.tempValue = parseInt(params.p);
            stateChanged = true;
        }

        return stateChanged;
    }
}
