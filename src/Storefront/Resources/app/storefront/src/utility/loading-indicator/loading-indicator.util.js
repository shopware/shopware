import Iterator from 'src/helper/iterator.helper';

const SELECTOR_CLASS = 'loader';

export default class LoadingIndicatorUtil {

    /**
     * Constructor
     * @param {Element|string} parent
     * @param position
     */
    constructor(parent, position = 'before') {
        this.parent = (parent instanceof Element) ? parent : document.body.querySelector(parent);
        this.position = position;
    }

    /**
     * Inserts a loading indicator inside the parent element's html
     */
    create() {
        if (this.exists()) return;
        this.parent.insertAdjacentHTML(this._getPosition(), LoadingIndicatorUtil.getTemplate());
    }

    /**
     * Removes all existing loading indicators inside the parent
     */
    remove() {
        const indicators = this.parent.querySelectorAll(`.${SELECTOR_CLASS}`);
        Iterator.iterate(indicators, indicator => indicator.remove());
    }

    /**
     * Checks if a loading indicator already exists
     * @returns {boolean}
     * @protected
     */
    exists() {
        return (this.parent.querySelectorAll(`.${SELECTOR_CLASS}`).length > 0);
    }

    /**
     * Defines the position to which the loading indicator shall be inserted.
     * Depends on the usage of the "insertAdjacentHTML" method.
     * @returns {"afterbegin"|"beforeend"}
     * @private
     */
    _getPosition() {
        return (this.position === 'before') ? 'afterbegin' : 'beforeend';
    }

    /**
     * The loading indicators HTML template definition
     * @returns {string}
     */
    static getTemplate() {
        return `<div class="${SELECTOR_CLASS}" role="status">
                    <span class="sr-only">Loading...</span>
                </div>`;
    }

    /**
     * Return the constant
     * @returns {string}
     * @constructor
     */
    static SELECTOR_CLASS() {
        return SELECTOR_CLASS;
    }
}
