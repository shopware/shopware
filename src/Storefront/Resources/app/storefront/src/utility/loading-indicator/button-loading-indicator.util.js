import LoadingIndicatorUtil from 'src/utility/loading-indicator/loading-indicator.util';

/**
 * @sw-package framework
 */
export default class ButtonLoadingIndicatorUtil extends LoadingIndicatorUtil {

    /**
     * Constructor
     * @param {HTMLButtonElement|HTMLAnchorElement|string} parent
     * @param {string} position
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

        // If the position is "inner", the loading indicator will replace the button content.
        // To prevent the button from jumping in width, we set the current width as inline styling first.
        if (this.position === 'inner') {
            const currentWith = this.parent.getBoundingClientRect().width;
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
            console.warn(`[ButtonLoadingIndicatorUtil] Unable to remove loading indicator. No indicator present on given element: ${this.parent}`);
            return;
        }

        // Restore the automatic width again after removing the loading indicator.
        // We do not remove the style attribute, because other expected inline styles can in the template.
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

    /**
     * Verify if the given element is valid to apply a button loading indicator.
     * @return {boolean}
     * @private
     */
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
