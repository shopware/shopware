import FilterBasePlugin from 'src/script/plugin/listing/filter-base.plugin';
import DomAccess from 'src/script/helper/dom-access.helper';
import deepmerge from 'deepmerge';

export default class FilterRatingPlugin extends FilterBasePlugin {

    static options = deepmerge(FilterBasePlugin.options, {
        countSelector: '.filter-rating-count',
        maxPoints: 5,
        ratingSystemSelector: '.filter-rating-container',
        radioSelector: '.product-detail-review-form-radio',
        snippets: {
            activeFilterLabelText: '',
        },
    });

    init() {
        this.currentRating = 0;
        this.counter = DomAccess.querySelector(this.el, this.options.countSelector);

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
        const dropdownMenu = DomAccess.querySelector(this.el, '.filter-panel-item-dropdown');
        const radios = DomAccess.querySelectorAll(this.el, this.options.radioSelector);

        dropdownMenu.addEventListener('click', (event) => {
            event.stopPropagation();
        });

        radios.forEach((radio) => {
            radio.addEventListener('change', this._onChangeRating.bind(this));
        });
    }

    /**
     * @private
     */
    _onChangeRating() {
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

    /**
     * @return {Array}
     * @public
     */
    getLabels() {
        const currentRating = this.ratingSystem.getRating();

        let labels = [];

        if (currentRating) {
            labels.push({
                label: `${currentRating} ${this.options.snippets.activeFilterLabelText}`,
                id: 'rating',
            });
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
