import DomAccess from 'asset/script/helper/dom-access.helper';

const ARROW_NAVIGATION_ACTIVE_CLASS = 'active';

const ARROW_NAVIGATION_ITERATOR_DEFAULT = -1;

export default class ArrowNavigationHelper {

    /**
     * Constructor.
     * @param {Element} element
     * @param {string} parentSelector
     * @param {string} itemSelector
     * @param {boolean} infinite
     */
    constructor(element, parentSelector, itemSelector, infinite = true) {
        this._element = element;
        this._parentSelector = parentSelector;
        this._infinite = infinite;
        this._itemSelector = itemSelector;

        this.resetIterator();

        this._registerEvents();
    }

    /**
     * Reset the iterator
     */
    resetIterator() {
        this._iterator = ARROW_NAVIGATION_ITERATOR_DEFAULT;
    }

    /**
     * Register events
     * @private
     */
    _registerEvents() {
        this._element.addEventListener('keydown', this._onKeyDown.bind(this));
    }

    /**
     * Handle 'keydown' event
     * @param {Event} e
     * @private
     */
    _onKeyDown(e) {

        // early return if no iterable items exist
        if (this._itemsExist() === false) return;

        switch (e.key) {
            case 'Enter':
                this._onPressEnter(e);
                return;
            case 'ArrowDown':
                this._iterator++;
                break;
            case 'ArrowUp':
                this._iterator--;
                break;
            default:
                return;
        }

        // handle bounds
        this._compromiseBounds();

        const iterables = this._getIterables();

        // remove all active classes
        iterables.forEach((item) => item.classList.remove(ARROW_NAVIGATION_ACTIVE_CLASS));

        // add active class to current iteration
        this._getCurrentSelection().classList.add(ARROW_NAVIGATION_ACTIVE_CLASS);
    }

    /**
     * When pressing "Enter" the link inside the currently
     * selected result item shall be clicked
     * @param {Event} e
     * @private
     */
    _onPressEnter(e) {
        // handle the original form submit event only if no search result has been selected before
        if (this._iterator <= ARROW_NAVIGATION_ITERATOR_DEFAULT) {
            return;
        }

        e.preventDefault();

        try {
            const a = DomAccess.querySelector(this._getCurrentSelection(), 'a');
            a.click();
        } catch (e) {
            // do nothing, if no link has been found in result item
        }
    }

    /**
     * Method to compromise the "out of bounds" case that occurs
     * if the iterator runs below zero or above the max threshold
     * @private
     */
    _compromiseBounds() {
        // set iterator to last item if below zero
        if (this._iterator < 0) {
            this._iterator = (this._infinite) ? this._max() : 0;
        }

        // set iterator to first item if above max
        if (this._iterator > this._max()) {
            this._iterator = (this._infinite) ? 0 : this._max();
        }
    }

    /**
     * Determine if iterable items exist
     * @returns {boolean}
     * @private
     */
    _itemsExist() {
        return (this._getIterables().length > 0);
    }

    /**
     * Retrieve an arra of iterables
     * @returns []
     * @private
     */
    _getIterables() {
        try {
            const parent = DomAccess.querySelector(document, this._parentSelector);
            return Array.from(parent.querySelectorAll(this._itemSelector));
        } catch (e) {
            return [];
        }
    }

    /**
     * Return the currently selected search result item
     * @returns {Element}
     * @private
     */
    _getCurrentSelection() {
        return this._getIterables()[this._iterator];
    }

    /**
     * Returns the upper bound of iterations by
     * using the amount of existing iterables
     * @returns {number}
     * @private
     */
    _max() {
        return this._getIterables().length - 1;
    }
}
