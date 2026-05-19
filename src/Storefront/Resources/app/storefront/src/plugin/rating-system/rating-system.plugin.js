import Plugin from 'src/plugin-system/plugin.class';

/**
 * @package content
 */
export default class RatingSystemPlugin extends Plugin {

    static options = {
        reviewPointAttr: 'data-review-form-point',
        ratingTextAttr: 'data-rating-text',

        activeClass: 'is-active',
        hiddenClass: 'd-none',
    };

    init() {
        this._ratingPoints = this.el.querySelectorAll('[' + this.options.reviewPointAttr + ']');
        this._textWrappers = this.el.querySelectorAll('[' + this.options.ratingTextAttr + ']');

        this._maxRating = null;

        if (!this._ratingPoints) {
            return;
        }

        this._registerEvents();
    }

    /**
     * @private
     */
    _registerEvents() {
        this._ratingPoints.forEach(point => {
            point.addEventListener('click', this._onClickRating.bind(this));
        });
    }

    /**
     * @private
     * @param {Event} event
     */
    _onClickRating(event) {
        const points = event.currentTarget.getAttribute(this.options.reviewPointAttr);

        if (this._maxRating && this._maxRating < points) {
            return;
        }

        this.setRating(points);
    }

    /**
     * set icon class to display the current rating
     *
     * @public
     * @param points
     */
    setRating(points){
        this._ratingPoints.forEach(radio => {
            const radioValue = radio.getAttribute(this.options.reviewPointAttr);

            if (radioValue <= points) {
                radio.classList.add(this.options.activeClass);

            } else {
                radio.classList.remove(this.options.activeClass);
            }

            radio.addEventListener('click', this._showInfoText.bind(this));
        });
    }

    /**
     * reset the current rating
     *
     * @public
     */
    resetRating() {
        this._ratingPoints.forEach(radio => {
            radio.classList.remove(this.options.activeClass);
        });
    }

    /**
     * get the current rating
     *
     * @public
     * @return {number}
     */
    getRating() {
        const points = this.el.querySelectorAll(`[${this.options.reviewPointAttr}].${this.options.activeClass}`);

        return points ? points.length : 0;
    }

    /**
     * Stops the onclick handler for points higher than the maxRating
     *
     * @param {number} maxRating
     *
     * @public
     */
    setMaxRating(maxRating) {
        this._maxRating = maxRating;
    }

    /**
     * show info text for current rating
     *
     * @param {Event} event
     *
     * @private
     */
    _showInfoText(event) {
        const targetValue = event.target.value;

        this._textWrappers.forEach(textWrapper => {
            if (textWrapper.hasAttribute(`${this.options.ratingTextAttr}`)) {
                if (textWrapper.getAttribute(`${this.options.ratingTextAttr}`) === targetValue) {
                    textWrapper.classList.remove(this.options.hiddenClass);
                } else {
                    textWrapper.classList.add(this.options.hiddenClass);
                }
            }
        });
    }
}
