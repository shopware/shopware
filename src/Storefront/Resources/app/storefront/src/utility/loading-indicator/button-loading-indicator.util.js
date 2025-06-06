import LoadingIndicatorUtil from 'src/utility/loading-indicator/loading-indicator.util';

/**
 * @sw-package framework
 */
export default class ButtonLoadingIndicatorUtil extends LoadingIndicatorUtil {

    /**
     * Constructor
     * @param {Element|string} parent
     * @param position
     */
    constructor(parent, position = 'before') {
        super(parent, position);

        if (!this._isValidElement()) {
            console.warn(`[ButtonLoadingIndicatorUtil] Parent element is not of type <button> or <a>. Given element: ${this.parent}`);
        }
    }

    /**
     * Call parent method and set the parent element disabled
     */
    create() {
        if (!this._isValidElement()) {
            console.warn(`[ButtonLoadingIndicatorUtil] Unable to create loading indicator. Parent element is not of type <button> or <a>. Given element: ${this.parent}`);
            return;
        }

        if (this.position === 'inner') {
            const currentWith = this.parent.offsetWidth;
            this.parent.style.width = `${currentWith}px`;
        }

        super.create();

        this.parent.classList.add(`is-loading-indicator-${this.position}`);

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
        if (!this.exists()) {
            return;
        }

        if (this.position === 'inner') {
            this.parent.style.width = 'auto';
        }

        super.remove();

        this.parent.classList.remove(`is-loading-indicator-${this.position}`);

        if (this._isButtonElement()) {
            this.parent.disabled = false;
        } else if (this._isAnchorElement()) {
            this.parent.classList.remove('disabled');
        }
    }

    _isValidElement() {
        return (this._isButtonElement() || this._isAnchorElement());
    }

    /**
     * Verify whether the injected parent is of type <button> or not
     * @returns {boolean}
     * @private
     */
    _isButtonElement() {
        return (this.parent?.tagName.toLowerCase() === 'button');
    }

    /**
     * Verify whether the injected parent is of type <a> or not
     * @returns {boolean}
     * @private
     */
    _isAnchorElement() {
        return (this.parent?.tagName.toLowerCase() === 'a');
    }
}
