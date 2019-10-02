// import DomAccess from 'src/script/helper/dom-access.helper';
import FilterBasePlugin from 'src/script/plugin/filter/filter-base.plugin';
import deepmerge from 'deepmerge';

export default class SortingPlugin extends FilterBasePlugin {

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
        this.filterPanel.changeFilter();
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
            sort: this.options.sorting,
        };
    }

    afterContentChange() {
        this.filterPanel.unregisterFilter(this);
    }

    /**
     * @return {Array}
     * @public
     */
    getLabels() {
        return [];
    }

    /**
     * @public
     */
    validate() {

    }
}
