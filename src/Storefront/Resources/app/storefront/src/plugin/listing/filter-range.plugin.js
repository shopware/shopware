import FilterBasePlugin from 'src/plugin/listing/filter-base.plugin';
import DomAccess from 'src/helper/dom-access.helper';
import deepmerge from 'deepmerge';

export default class FilterRangePlugin extends FilterBasePlugin {

    static options = deepmerge(FilterBasePlugin.options, {
        inputMinSelector: '.min-input',
        inputMaxSelector: '.max-input',
        inputInvalidCLass: 'is-invalid',
        inputTimeout: 500,
        minKey: 'min-price',
        maxKey: 'max-price',
        errorContainerClass: 'filter-range-error',
        containerSelector: '.filter-range-container',
        snippets: {
            filterRangeActiveMinLabel: '',
            filterRangeActiveMaxLabel: '',
            filterRangeErrorMessage: '',
        },
    });

    init() {
        this._container = DomAccess.querySelector(this.el, this.options.containerSelector);
        this._inputMin = DomAccess.querySelector(this.el, this.options.inputMinSelector);
        this._inputMax = DomAccess.querySelector(this.el, this.options.inputMaxSelector);
        this._timeout = null;
        this._hasError = false;

        this._registerEvents();
    }

    /**
     * @private
     */
    _registerEvents() {
        this._inputMin.addEventListener('input', this._onChangeInput.bind(this));
        this._inputMax.addEventListener('input', this._onChangeInput.bind(this));
    }

    /**
     * @private
     */
    _onChangeInput() {
        clearTimeout(this._timeout);

        this._timeout = setTimeout(() => {
            if (this._isInputInvalid()) {
                this._setError();
            } else {
                this._removeError();
            }
            this.listing.changeListing();
        }, this.options.inputTimeout);
    }

    /**
     * @return {Object}
     * @public
     */
    getValues() {
        const values = {};

        values[this.options.minKey] = this._inputMin.value;
        values[this.options.maxKey] = this._inputMax.value;

        return values;
    }

    /**
     * @return {boolean}
     * @private
     */
    _isInputInvalid() {
        return parseInt(this._inputMin.value) > parseInt(this._inputMax.value);
    }

    /**
     * @return {string}
     * @private
     */
    _getErrorMessageTemplate() {
        return `<div class="${this.options.errorContainerClass}">${this.options.snippets.filterRangeErrorMessage}</div>`;
    }

    /**
     * @private
     */
    _setError() {
        if (this._hasError) {
            return;
        }

        this._inputMin.classList.add(this.options.inputInvalidCLass);
        this._inputMax.classList.add(this.options.inputInvalidCLass);

        this._container.insertAdjacentHTML('afterend', this._getErrorMessageTemplate());

        this._hasError = true;
    }

    /**
     * @private
     */
    _removeError() {
        this._inputMin.classList.remove(this.options.inputInvalidCLass);
        this._inputMax.classList.remove(this.options.inputInvalidCLass);

        const error = DomAccess.querySelector(this.el, `.${this.options.errorContainerClass}`, false);

        if (error) {
            error.remove();
        }

        this._hasError = false;
    }

    /**
     * @param params
     * @public
     * @return {boolean}
     */
    setValuesFromUrl(params) {
        let stateChanged = false;
        Object.keys(params).forEach(key => {
            if (key === this.options.minKey) {
                this._inputMin.value = params[key];
                stateChanged = true;
            }
            if (key === this.options.maxKey) {
                this._inputMax.value = params[key];
                stateChanged = true;
            }
        });

        return stateChanged;
    }

    /**
     * @return {Array}
     * @public
     */
    getLabels() {
        let labels = [];

        if (this._inputMin.value.length || this._inputMax.value.length) {
            if (this._inputMin.value.length) {
                labels.push({
                    label: `${this.options.snippets.filterRangeActiveMinLabel} ${this._inputMin.value} ${this.options.currencySymbol}`,
                    id: this.options.minKey,
                });
            }

            if (this._inputMax.value.length) {
                labels.push({
                    label: `${this.options.snippets.filterRangeActiveMaxLabel} ${this._inputMax.value} ${this.options.currencySymbol}`,
                    id: this.options.maxKey,
                });
            }
        } else {
            labels = [];
        }

        return labels;
    }

    /**
     * @param id
     * @public
     */
    reset(id) {
        if (id === this.options.minKey) {
            this._inputMin.value = '';
        }

        if (id === this.options.maxKey) {
            this._inputMax.value = '';
        }

        this._removeError();
    }

    /**
     * @public
     */
    resetAll() {
        this._inputMin.value = '';
        this._inputMax.value = '';
        this._removeError();
    }
}
