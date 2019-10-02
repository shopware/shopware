import DomAccess from 'src/script/helper/dom-access.helper';
import FilterBasePlugin from 'src/script/plugin/filter/filter-base.plugin';
import deepmerge from 'deepmerge';

export default class FilterBooleanPlugin extends FilterBasePlugin {

    static options = deepmerge(FilterBasePlugin.options, {
        checkboxSelector: '.filter-boolean-input',
        activeClass: 'is-active',
    });

    init() {
        this.checkbox = DomAccess.querySelector(this.el, this.options.checkboxSelector);

        this._registerEvents();
    }

    /**
     * @private
     */
    _registerEvents() {
        this.checkbox.addEventListener('change', this._onChangeCheckbox.bind(this));
    }

    /**
     * @param id
     * @public
     */
    reset(id) {
        if (id !== this.options.name) {
            return;
        }

        this.checkbox.checked = false;
    }

    /**
     * @public
     */
    resetAll() {
        this.checkbox.checked = false;
    }

    /**
     * @return {Object}
     * @public
     */
    getValues() {
        const values = {};
        values[this.options.name] = this.checkbox.checked ? '1' : '';

        return values;
    }

    /**
     * @return {Array}
     * @public
     */
    getLabels() {
        let labels = [];

        if (this.checkbox.checked) {
            labels.push({
                label: this.options.displayName,
                id: this.options.name,
            });
        } else {
            labels = [];
        }

        return labels;
    }

    /**
     * @public
     */
    validate() {

    }

    /**
     * @private
     */
    _onChangeCheckbox() {
        this.filterPanel.changeFilter();
    }
}
