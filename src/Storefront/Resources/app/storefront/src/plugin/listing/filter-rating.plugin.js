import FilterBasePlugin from 'src/plugin/listing/filter-base.plugin';
import DomAccess from 'src/helper/dom-access.helper';
import deepmerge from 'deepmerge';

/**
 * @deprecated tag:v6.4.0 - The filter rating plugin will be replaced with a new implementation
 */
export default class FilterRatingPlugin extends FilterBasePlugin {

    static options = deepmerge(FilterBasePlugin.options, {
        countSelector: '.filter-rating-count',
        maxPoints: 5,
        ratingSystemSelector: '.filter-rating-container',
        radioSelector: '.product-detail-review-form-radio',
        snippets: {
            filterRatingActiveLabelStart: 'Minimum',
            filterRatingActiveLabelEndSingular: 'star',
            filterRatingActiveLabelEnd: 'stars',
            disabledFilterText: 'Filter not active'
        },
        reviewPointAttr: 'data-review-form-point'
    });

    init() {
        this.currentRating = 0;
        this.counter = DomAccess.querySelector(this.el, this.options.countSelector);
        this._maxRating = null;

        this._getRatingSystemPluginInstance();
        this._registerEvents();
    }

    /**
     * @private
     */
    _getRatingSystemPluginInstance() {
        const element = DomAccess.querySelector(this.el, this.options.ratingSystemSelector);
        this.ratingSystem = window.PluginManager.getPluginInstanceFromElement(element, 'RatingSystem');
    }

    /**
     * @private
     */
    _registerEvents() {
        const radios = DomAccess.querySelectorAll(this.el, this.options.radioSelector);

        radios.forEach((radio) => {
            radio.addEventListener('change', this._onChangeRating.bind(this));
        });
    }

    /**
     * @private
     */
    _onChangeRating(event) {
        if (event) {
            const points = event.target.value;
            if (this._maxRating && this._maxRating < points) {
                return;
            }
        }

        this.listing.changeListing();
    }

    /**
     * @return {Object}
     * @public
     */
    getValues() {
        const values = {};
        const currentRating = this.ratingSystem.getRating();

        this.currentRating = currentRating;
        this._updateCount();

        values[this.options.name] = currentRating ? currentRating.toString() : '';

        return values;
    }

    setValuesFromUrl(params) {
        let stateChanged = false;
        Object.keys(params).forEach(key => {
            if (key === this.options.name) {
                this.currentRating = params[key];
                this._updateCount();

                this.ratingSystem.setRating(this.currentRating);
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
        const currentRating = this.ratingSystem.getRating();

        let labels = [];

        if (currentRating) {
            let endSnippet = this.options.snippets.filterRatingActiveLabelEnd;
            if (parseInt(currentRating) === 1) {
                endSnippet = this.options.snippets.filterRatingActiveLabelEndSingular;
            }

            labels.push({
                label: `${this.options.snippets.filterRatingActiveLabelStart}
                        ${currentRating}
                        ${endSnippet}`,
                id: 'rating'
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
            this.setMaxRating(maxRating);
            this.ratingSystem.setMaxRating(maxRating);
            this._maxRating = maxRating;
            return;
        }

        this.disableFilter();
    }

    /**
     * @public
     */
    disableFilter() {
        const button = DomAccess.querySelector(this.el, '.filter-panel-item-toggle');

        button.disabled = true;
        button.setAttribute('title', this.options.snippets.disabledFilterText);
        this.el.classList.add('disabled');
    }

    /**
     * @public
     */
    enableFilter() {
        const button = DomAccess.querySelector(this.el, '.filter-panel-item-toggle');

        button.disabled = false;
        button.removeAttribute('title');
        this.el.classList.remove('disabled');
    }

    /**
     * @public
     */
    setMaxRating(maxRating) {
        const ratingPoints = DomAccess.querySelectorAll(this.el, '[' + this.options.reviewPointAttr + ']');

        ratingPoints.forEach((radio) => {
            const radioValue = radio.getAttribute(this.options.reviewPointAttr);

            if (radioValue > maxRating) {
                radio.classList.add('disabled');
                radio.setAttribute('title', this.options.snippets.disabledFilterText);
            } else {
                radio.classList.remove('disabled');
                radio.removeAttribute('title');
            }
        });
    }

    /**
     * @param id
     * @public
     */
    reset(id) {
        if (id !== 'rating') {
            return;
        }

        this.ratingSystem.resetRating();
    }

    /**
     * @public
     */
    resetAll() {
        this.ratingSystem.resetRating();
    }

    /**
     * @private
     */
    _updateCount() {
        this.counter.innerText = this.currentRating ? `(${this.currentRating}/${this.options.maxPoints})` : '';
    }
}
