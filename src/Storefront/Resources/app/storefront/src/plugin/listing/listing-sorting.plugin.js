// import DomAccess from 'src/helper/dom-access.helper';
import FilterBasePlugin from 'src/plugin/listing/filter-base.plugin';
import deepmerge from 'deepmerge';

export default class ListingSortingPlugin extends FilterBasePlugin {

    static options = deepmerge(FilterBasePlugin.options, {
        sorting: null,
    });

    init() {
        this.select = this.el.querySelector('select');
        this._registerEvents();
    }

    /**
     * @private
     */
    _registerEvents() {
        this.select.addEventListener('change', this.onChangeSorting.bind(this));
    }

    onChangeSorting(event) {
        this.options.sorting = event.target.value;
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
        if (this.options.sorting === null) {
            return {};
        }

        return {
            order: this.options.sorting,
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
