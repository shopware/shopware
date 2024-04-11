import LoadingIndicatorUtil from 'src/utility/loading-indicator/loading-indicator.util';

/**
 * @package storefront
 */
export default class ButtonLoadingIndicatorUtil extends LoadingIndicatorUtil {

    /**
     * Constructor
     * @param {Element|string} parent
     * @param position
     */
    constructor(parent, position = 'before') {
        super(parent, position);

        if (!this._isButtonElement() && !this._isAnchorElement()) {
            throw Error('Parent element is not of type <button> or <a>');
        }
    }

    /**
     * Call parent method and set the parent element disabled
     */
    create() {
        super.create();

        if (this._isButtonElement()) {
            this.parent.disabled = true;
        } else if (this._isAnchorElement()) {
            this.parent.classList.add('disabled');
        }
    }

    /**
     * Call parent method and re-enable parent element
     */
    remove() {
        super.remove();

        if (this._isButtonElement()) {
            this.parent.disabled = false;
        } else if (this._isAnchorElement()) {
            this.parent.classList.remove('disabled');
        }
    }

    /**
     * Verify whether the injected parent is of type <button> or not
     * @returns {boolean}
     * @private
     */
    _isButtonElement() {
        return (this.parent.tagName.toLowerCase() === 'button');
    }

    /**
     * Verify whether the injected parent is of type <a> or not
     * @returns {boolean}
     * @private
     */
    _isAnchorElement() {
        return (this.parent.tagName.toLowerCase() === 'a');
    }
}
