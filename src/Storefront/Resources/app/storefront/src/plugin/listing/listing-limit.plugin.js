/*
 * @sw-package inventory
 */

import FilterBasePlugin from 'src/plugin/listing/filter-base.plugin';
import deepmerge from 'deepmerge';

export default class ListingLimitPlugin extends FilterBasePlugin {

    static options = deepmerge(FilterBasePlugin.options, {
        limit: null,
    });

    init() {
        this.select = this.el.querySelector('select');
        this._registerEvents();
    }

    /**
     * @private
     */
    _registerEvents() {
        this.select.addEventListener('change', this.onChangeLimit.bind(this));
    }

    onChangeLimit(event) {
        this.options.limit = event.target.value;
        this.listing.changeListing();
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
        if (this.options.limit === null) {
            return {};
        }

        return {
            limit: this.options.limit,
        };
    }

    afterContentChange() {
        this.listing.deregisterFilter(this);
    }

    /**
     * @return {Array}
     * @public
     */
    getLabels() {
        return [];
    }
}
