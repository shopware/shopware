import Plugin from 'src/plugin-system/plugin.class';
import DomAccess from 'src/helper/dom-access.helper';
import Iterator from 'src/helper/iterator.helper';

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
        this._ratingPoints = DomAccess.querySelectorAll(this.el, '[' + this.options.reviewPointAttr + ']');
        this._textWrappers = DomAccess.querySelectorAll(this.el, '[' + this.options.ratingTextAttr + ']', false);

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
        Iterator.iterate(this._ratingPoints, point => {
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
        Iterator.iterate(this._ratingPoints, radio => {
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
        Iterator.iterate(this._ratingPoints, radio => {
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
        const points = DomAccess.querySelectorAll(
            this.el,
            `[${this.options.reviewPointAttr}].${this.options.activeClass}`,
            false
        );

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

        Iterator.iterate(this._textWrappers, textWrapper => {
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
