import DomAccess from 'src/helper/dom-access.helper';
import Iterator from 'src/helper/iterator.helper';
import FilterBasePlugin from 'src/plugin/listing/filter-base.plugin';
import deepmerge from 'deepmerge';

export default class FilterMultiSelectPlugin extends FilterBasePlugin {

    static options = deepmerge(FilterBasePlugin.options, {
        checkboxSelector: '.filter-multi-select-checkbox',
        countSelector: '.filter-multi-select-count',
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

        Iterator.iterate(checkboxes, (checkbox) => {
            checkbox.addEventListener('change', this._onChangeFilter.bind(this));
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

    setValuesFromUrl(params) {
        let stateChanged = false;
        Object.keys(params).forEach(key => {
            if (key === this.options.name) {
                stateChanged = true;
                const ids = params[key].split('|');

                ids.forEach(id => {
                    const checkboxEl = DomAccess.querySelector(this.el, `[id="${id}"]`, false);

                    if (checkboxEl) {
                        checkboxEl.checked = true;
                        this.selection.push(checkboxEl.id);
                    }
                });
            }
        });

        this._updateCount();

        return stateChanged;
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
