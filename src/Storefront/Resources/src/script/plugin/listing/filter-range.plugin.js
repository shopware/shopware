import FilterBasePlugin from 'src/script/plugin/listing/filter-base.plugin';
import DomAccess from 'src/script/helper/dom-access.helper';
import deepmerge from 'deepmerge';

export default class FilterRangePlugin extends FilterBasePlugin {

    static options = deepmerge(FilterBasePlugin.options, {
        inputMinSelector: '.min-input',
        inputMaxSelector: '.max-input',
        inputTimeout: 500,
        minKey: 'min-price',
        maxKey: 'max-price',
    });

    init() {
        this.inputMin = DomAccess.querySelector(this.el, this.options.inputMinSelector);
        this.inputMax = DomAccess.querySelector(this.el, this.options.inputMaxSelector);
        this._timeout = null;

        this._registerEvents();
    }

    /**
     * @private
     */
    _registerEvents() {
        this.inputMin.addEventListener('input', this._onChangeInput.bind(this));
        this.inputMax.addEventListener('input', this._onChangeInput.bind(this));
    }

    /**
     * @private
     */
    _onChangeInput() {
        clearTimeout(this.timeout);

        this.timeout = setTimeout(() => {
            this.listing.changeListing();
        }, this.options.inputTimeout);
    }

    /**
     * @return {Object}
     * @public
     */
    getValues() {
        const values = {};

        values[this.options.minKey] = this.inputMin.value;
        values[this.options.maxKey] = this.inputMax.value;

        return values;
    }

    /**
     * @return {Array}
     * @public
     */
    getLabels() {
        let labels = [];

        if (this.inputMin.value.length || this.inputMax.value.length) {
            if (this.inputMin.value.length) {
                labels.push({
                    label: this.options.minLabelActiveFilter + ' ' +
                           this.inputMin.value + ' ' +
                           this.options.currencySymbol,
                    id: this.options.minKey,
                });
            }

            if (this.inputMax.value.length) {
                labels.push({
                    label: `${this.options.maxLabelActiveFilter}
                            ${this.inputMax.value}
                            ${this.options.currencySymbol}`,
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
            this.inputMin.value = '';
        }

        if (id === this.options.maxKey) {
            this.inputMax.value = '';
        }
    }

    /**
     * @public
     */
    resetAll() {
        this.inputMin.value = '';
        this.inputMax.value = '';
    }
}
