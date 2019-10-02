// import DomAccess from 'src/script/helper/dom-access.helper';
import FilterBasePlugin from 'src/script/plugin/filter/filter-base.plugin';
import deepmerge from 'deepmerge';

export default class PaginationPlugin extends FilterBasePlugin {

    static options = deepmerge(FilterBasePlugin.options, {
        page: 1,
    });

    init() {
        this.buttons = this.el.querySelectorAll('.pagination input[type=radio]');
        this.tempValue = null;
        this._registerEvents();
    }

    /**
     * @private
     */
    _registerEvents() {
        this.buttons.forEach((radio) => {
            radio.addEventListener('change', this.onChangePage.bind(this));
        });
    }

    onChangePage(event) {
        this.tempValue = event.target.value;
        this.filterPanel.changeFilter();
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
        return { p: this.options.page };
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
