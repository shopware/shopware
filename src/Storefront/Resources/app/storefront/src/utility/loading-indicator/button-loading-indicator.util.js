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

        if (this._isButtonElement() === false) {
            throw Error('Parent element is not of type <button>');
        }
    }

    /**
     * Call parent method and set the parent element disabled
     */
    create() {
        super.create();
        this.parent.disabled = true;
    }

    /**
     * Call parent method and re-enable parent element
     */
    remove() {
        super.remove();
        this.parent.disabled = false;
    }

    /**
     * Verify whether the injected parent is of type <button> or not
     * @returns {boolean}
     * @private
     */
    _isButtonElement() {
        return (this.parent.tagName.toLowerCase() === 'button');
    }
}
