import DomAccess from 'src/helper/dom-access.helper';
import FilterBasePlugin from 'src/plugin/listing/filter-base.plugin';
import deepmerge from 'deepmerge';

export default class FilterBooleanPlugin extends FilterBasePlugin {

    static options = deepmerge(FilterBasePlugin.options, {
        checkboxSelector: '.filter-boolean-input',
        activeClass: 'is-active',
        snippets: {
            disabledFilterText: 'Filter not active',
        },
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

    setValuesFromUrl(params) {
        let stateChanged = false;
        Object.keys(params).forEach(key => {
            if (key === this.options.name) {
                if (params[key]) {
                    this.checkbox.checked = 1;
                    stateChanged = true;
                }
            }
        });

        return stateChanged;
    }

    /**
     * @public
     */
    refreshDisabledState(filter) {
        const booleanFilter = filter[this.options.name];

        if (booleanFilter.max && booleanFilter.max > 0) {
            this.enableFilter();
        } else {
            this.disableFilter();
        }
    }

    /**
     * @public
     */
    enableFilter() {
        this.el.classList.remove('disabled');
        this.el.removeAttribute('title');
        this.checkbox.removeAttribute('disabled');
    }

    /**
     * @public
     */
    disableFilter() {
        this.el.classList.add('disabled');
        this.el.setAttribute('title', this.options.snippets.disabledFilterText);
        this.checkbox.disabled = true;
    }

    /**
     * @private
     */
    _onChangeCheckbox() {
        this.listing.changeListing();
    }
}
