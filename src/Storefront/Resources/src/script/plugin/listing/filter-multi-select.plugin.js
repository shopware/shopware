import DomAccess from 'src/script/helper/dom-access.helper';
import Iterator from 'src/script/helper/iterator.helper';
import FilterBasePlugin from 'src/script/plugin/listing/filter-base.plugin';
import deepmerge from 'deepmerge';

export default class FilterMultiSelectPlugin extends FilterBasePlugin {

    static options = deepmerge(FilterBasePlugin.options, {
        checkboxSelector: '.filter-multi-select-checkbox',
        countSelector: '.filter-multi-select-count',
        dropDownSelector: '.filter-multi-select-dropdown',
    });

    init() {
        this.selection = [];
        this.counter = DomAccess.querySelector(this.el, this.options.countSelector);

        this._registerEvents();
    }

    /**
     * @private
     */
    _registerEvents() {
        const checkboxes = DomAccess.querySelectorAll(this.el, this.options.checkboxSelector);
        const dropdownMenu = DomAccess.querySelector(this.el, this.options.dropDownSelector);

        Iterator.iterate(checkboxes, (checkbox) => {
            checkbox.addEventListener('change', this._onChangeFilter.bind(this));
        });

        dropdownMenu.addEventListener('click', (event) => {
            event.stopPropagation();
        });
    }

    /**
     * @return {Array}
     * @public
     */
    getValues() {
        const checkedCheckboxes =
            DomAccess.querySelectorAll(this.el, `${this.options.checkboxSelector}:checked`, false);

        let selection = [];

        if (checkedCheckboxes) {
            Iterator.iterate(checkedCheckboxes, (checkbox) => {
                selection.push(checkbox.id);
            });
        } else {
            selection = [];
        }

        this.selection = selection;
        this._updateCount();

        const values = {};
        values[this.options.name] = selection;

        return values;
    }

    /**
     * @return {Array}
     * @public
     */
    getLabels() {
        const activeCheckboxes =
            DomAccess.querySelectorAll(this.el, `${this.options.checkboxSelector}:checked`, false);

        let labels = [];

        if (activeCheckboxes) {
            Iterator.iterate(activeCheckboxes, (checkbox) => {
                labels.push({
                    label: checkbox.dataset.label,
                    id: checkbox.id,
                });
            });
        } else {
            labels = [];
        }

        return labels;
    }

    /**
     * @private
     */
    _onChangeFilter() {
        this.listing.changeListing();
    }

    /**
     * @param id
     * @public
     */
    reset(id) {
        const checkboxEl = DomAccess.querySelector(this.el, `[id="${id}"]`, false);

        if (checkboxEl) {
            checkboxEl.checked = false;
        }
    }

    /**
     * @public
     */
    resetAll() {
        this.selection.filter = [];

        const checkedCheckboxes =
            DomAccess.querySelectorAll(this.el, `${this.options.checkboxSelector}:checked`, false);

        if (checkedCheckboxes) {
            Iterator.iterate(checkedCheckboxes, (checkbox) => {
                checkbox.checked = false;
            });
        }
    }

    /**
     * @private
     */
    _updateCount() {
        this.counter.innerText = this.selection.length ? `(${this.selection.length})` : '';
    }
}
