import Iterator from 'src/helper/iterator.helper';

const SELECTOR_CLASS = 'loader';
const VISUALLY_HIDDEN_CLASS = 'visually-hidden';

export const INDICATOR_POSITION = {
    BEFORE: 'before',
    AFTER: 'after',
    INNER: 'inner',
};

/**
 * @package storefront
 */
export default class LoadingIndicatorUtil {

    /**
     * Constructor
     * @param {Element|string} parent
     * @param position
     */
    constructor(parent, position = INDICATOR_POSITION.BEFORE) {
        this.parent = (parent instanceof Element) ? parent : document.body.querySelector(parent);
        this.position = position;
    }

    /**
     * Inserts a loading indicator inside the parent element's html
     */
    create() {
        if (this.exists()) return;

        if (this.position === INDICATOR_POSITION.INNER) {
            this.parent.innerHTML = LoadingIndicatorUtil.getTemplate();

            return;
        }

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
        return (this.position === INDICATOR_POSITION.BEFORE) ? 'afterbegin' : 'beforeend';
    }

    /**
     * The loading indicators HTML template definition
     * @returns {string}
     */
    static getTemplate() {
        return `<div class="${SELECTOR_CLASS}" role="status">
                    <span class="${VISUALLY_HIDDEN_CLASS}">Loading...</span>
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
