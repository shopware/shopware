/*
 * @sw-package inventory
 */

import FilterMultiSelectPlugin from 'src/plugin/listing/filter-multi-select.plugin';
import deepmerge from 'deepmerge';

export default class FilterRatingSelectPlugin extends FilterMultiSelectPlugin {

    static options = deepmerge(FilterMultiSelectPlugin.options, {
        maxPoints: 5,
        snippets: {
            filterRatingActiveLabelStart: 'Minimum',
            filterRatingActiveLabelEndSingular: 'star',
            filterRatingActiveLabelEnd: 'stars',
            disabledFilterText: 'Filter not active',
            ariaLabel: '',
            ariaLabelCount: '',
            ariaLabelCountSingular: '',
        },
    });

    /**
     * @return {Object}
     * @public
     */
    getValues() {
        const values = {};
        const activeRadio = this.el.querySelector(`${this.options.checkboxSelector}:checked`);

        this.currentRating = activeRadio ? activeRadio.value : false;
        this._updateCount();

        values[this.options.name] = this.currentRating ? this.currentRating.toString() : '';

        return values;
    }

    setValuesFromUrl(params) {
        let stateChanged = false;
        Object.keys(params).forEach(key => {
            if (key === this.options.name) {
                this.currentRating = params[key];
                this._updateCount();

                const radios =  this.el.querySelectorAll(this.options.checkboxSelector);
                if (radios) {
                    radios.forEach((radio) => {
                        if (radio.value === this.currentRating) {
                            radio.checked = true;
                        }
                    });
                }

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
        const activeRadio = this.el.querySelector(`${this.options.checkboxSelector}:checked`);
        const currentRating = activeRadio ? activeRadio.value : false;

        let labels = [];

        if (currentRating) {
            let endSnippet = this.options.snippets.filterRatingActiveLabelEnd;
            if (parseInt(currentRating) === 1) {
                endSnippet = this.options.snippets.filterRatingActiveLabelEndSingular;
            }

            labels.push({
                label: `${this.options.snippets.filterRatingActiveLabelStart}
                        ${currentRating}/${this.options.maxPoints}
                        ${endSnippet}`,
                id: 'rating',
            });
        } else {
            labels = [];
        }

        return labels;
    }

    /**
     * @public
     */
    refreshDisabledState(filter) {
        const ratingFilter = filter[this.options.name];
        const maxRating = ratingFilter.max;

        if (maxRating && maxRating > 0 ) {
            this.enableFilter();
            this._disableInactiveFilterOptions(maxRating);
            return;
        }

        this.disableFilter();
    }

    /**
     * @private
     */
    _disableInactiveFilterOptions(maxRating) {
        const radios = this.el.querySelectorAll(this.options.checkboxSelector);
        radios.forEach((radio) => {
            if (radio.checked === true) {
                return;
            }

            if (maxRating >= radio.value) {
                this.enableOption(radio);
            } else {
                this.disableOption(radio);
            }
        });
    }

    /**
     * @public
     */
    reset() {
        this.resetAll();
    }

    /**
     * @private
     */
    _updateCount() {
        this.counter.textContent = this.currentRating ? `(${this.currentRating}/${this.options.maxPoints})` : '';

        this._updateAriaLabel();
    }

    /**
     * Update the aria-label for the filter toggle button to reflect the number of already selected stars.
     * @private
     */
    _updateAriaLabel() {
        if (!this.options.snippets.ariaLabel) {
            return;
        }

        if (!this.currentRating) {
            this.mainFilterButton.setAttribute('aria-label', this.options.snippets.ariaLabel);
            return;
        }

        if (parseInt(this.currentRating) === 1) {
            this.mainFilterButton.setAttribute('aria-label', `${this.options.snippets.ariaLabel} (${this.options.snippets.ariaLabelCountSingular})`);
            return;
        }

        this.mainFilterButton.setAttribute(
            'aria-label',
            `${this.options.snippets.ariaLabel} (${this.options.snippets.ariaLabelCount.replace('%stars%', this.currentRating)})`
        );
    }
}
