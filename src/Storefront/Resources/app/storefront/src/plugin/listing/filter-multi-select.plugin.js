/*
 * @sw-package inventory
 */

import FilterBasePlugin from 'src/plugin/listing/filter-base.plugin';
import deepmerge from 'deepmerge';

export default class FilterMultiSelectPlugin extends FilterBasePlugin {

    static options = deepmerge(FilterBasePlugin.options, {
        checkboxSelector: '.filter-multi-select-checkbox',
        countSelector: '.filter-multi-select-count',
        listItemSelector: '.filter-multi-select-list-item',
        snippets: {
            disabledFilterText: 'Filter not active',
            ariaLabel: '',
            ariaLabelCount: '',
        },
        mainFilterButtonSelector: '.filter-panel-item-toggle',
    });

    init() {
        this.selection = [];
        this.counter = this.el.querySelector(this.options.countSelector);
        this.mainFilterButton = this.el.querySelector(this.options.mainFilterButtonSelector);

        this._registerEvents();
    }

    /**
     * @private
     */
    _registerEvents() {
        const checkboxes = this.el.querySelectorAll(this.options.checkboxSelector);

        checkboxes.forEach((checkbox) => {
            checkbox.addEventListener('change', this._onChangeFilter.bind(this));
        });
    }

    /**
     * @return {Array}
     * @public
     */
    getValues() {
        const checkedCheckboxes =
            this.el.querySelectorAll(`${this.options.checkboxSelector}:checked`);

        let selection = [];

        if (checkedCheckboxes) {
            checkedCheckboxes.forEach((checkbox) => {
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
            this.el.querySelectorAll(`${this.options.checkboxSelector}:checked`);

        let labels = [];

        if (activeCheckboxes) {
            activeCheckboxes.forEach((checkbox) => {
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

    setValuesFromUrl(params = {}) {
        let stateChanged = false;

        const properties = params[this.options.name];

        const ids = properties ? properties.split('|') : [];

        const uncheckItems = this.selection.filter(x => !ids.includes(x));
        const checkItems = ids.filter(x => !this.selection.includes(x));

        if (uncheckItems.length > 0 || checkItems.length > 0) {
            stateChanged = true;
        }

        checkItems.forEach(id => {
            const checkboxEl = this.el.querySelector(`[id="${id}"]`);

            if (checkboxEl) {
                checkboxEl.checked = true;
                this.selection.push(checkboxEl.id);
            }
        });

        uncheckItems.forEach(id => {
            this.reset(id);

            this.selection = this.selection.filter(item => item !== id);
        });

        this._updateCount();

        return stateChanged;
    }

    /**
     * @private
     */
    _onChangeFilter() {
        // reset page to 1 when updating the filter
        this.listing.changeListing(true, { p: 1 });
    }

    /**
     * @param id
     * @public
     */
    reset(id) {
        const checkboxEl = this.el.querySelector(`[id="${id}"]`);

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
            this.el.querySelectorAll(`${this.options.checkboxSelector}:checked`);

        if (checkedCheckboxes) {
            checkedCheckboxes.forEach((checkbox) => {
                checkbox.checked = false;
            });
        }
    }

    /**
     * @public
     */
    refreshDisabledState(filter) {
        const disabledFilter = filter[this.options.name];

        if (!disabledFilter.entities || disabledFilter.entities.length < 1) {
            this.disableFilter();
            return;
        }

        this.enableFilter();

        this._disableInactiveFilterOptions(disabledFilter.entities.map(entity => entity.id));
    }

    /**
     * @private
     */
    _disableInactiveFilterOptions(activeItemIds) {
        const checkboxes = this.el.querySelectorAll(this.options.checkboxSelector);
        checkboxes.forEach((checkbox) => {
            if (checkbox.checked === true) {
                return;
            }

            if (activeItemIds.includes(checkbox.id)) {
                this.enableOption(checkbox);
            } else {
                this.disableOption(checkbox);
            }
        });
    }

    /**
     * @public
     */
    disableOption(input){
        const listItem = input.closest(this.options.listItemSelector);
        listItem.classList.add('disabled');
        listItem.setAttribute('title', this.options.snippets.disabledFilterText);
        input.disabled = true;
    }

    /**
     * @public
     */
    enableOption(input) {
        const listItem = input.closest(this.options.listItemSelector);
        listItem.removeAttribute('title');
        listItem.classList.remove('disabled');
        input.disabled = false;
    }

    /**
     * @public
     */
    enableAllOptions() {
        const checkboxes = this.el.querySelectorAll(this.options.checkboxSelector);
        checkboxes.forEach((checkbox) => {
            this.enableOption(checkbox);
        });
    }

    /**
     * @public
     */
    disableFilter() {
        const mainFilterButton = this.el.querySelector(this.options.mainFilterButtonSelector);
        mainFilterButton.classList.add('disabled');
        mainFilterButton.setAttribute('disabled', 'disabled');
        mainFilterButton.setAttribute('title', this.options.snippets.disabledFilterText);
    }

    /**
     * @public
     */
    enableFilter() {
        const mainFilterButton = this.el.querySelector(this.options.mainFilterButtonSelector);
        mainFilterButton.classList.remove('disabled');
        mainFilterButton.removeAttribute('disabled');
        mainFilterButton.removeAttribute('title');
    }

    /**
     * @private
     */
    _updateCount() {
        this.counter.textContent = this.selection.length ? `(${this.selection.length})` : '';

        this._updateAriaLabel();
    }

    /**
     * Update the aria-label for the filter toggle button to reflect the number of already selected items.
     * @private
     */
    _updateAriaLabel() {
        if (!this.options.snippets.ariaLabel) {
            return;
        }

        if (this.selection.length === 0) {
            this.mainFilterButton.setAttribute('aria-label', this.options.snippets.ariaLabel);
            return;
        }

        this.mainFilterButton.setAttribute(
            'aria-label',
            `${this.options.snippets.ariaLabel} (${this.options.snippets.ariaLabelCount.replace('%count%', this.selection.length.toString())})`
        );
    }
}
