import DomAccess from 'src/helper/dom-access.helper';
import Iterator from 'src/helper/iterator.helper';

const ARROW_NAVIGATION_ACTIVE_CLASS = 'is-active';

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
     * @param {Event} event
     * @private
     */
    _onKeyDown(event) {
        const parent = DomAccess.querySelector(document, this._parentSelector, false);
        if (!parent) return;

        this._items = parent.querySelectorAll(this._itemSelector);
        // early return if no items exist
        if (this._items.length === 0) return;

        switch (event.key) {
            case 'Enter':
                this._onPressEnter(event);
                return;
            case 'ArrowDown':
                event.preventDefault();
                this._iterator++;
                break;
            case 'ArrowUp':
                event.preventDefault();
                this._iterator--;
                break;
            default:
                return;
        }

        this._clampIterator();

        // remove all active classes
        Iterator.iterate(this._items, (item) => item.classList.remove(ARROW_NAVIGATION_ACTIVE_CLASS));

        // add active class to current iteration
        this._getCurrentSelection().classList.add(ARROW_NAVIGATION_ACTIVE_CLASS);
    }

    /**
     * When pressing "Enter" the link inside the currently
     * selected result item shall be clicked
     * @param {Event} event
     * @private
     */
    _onPressEnter(event) {
        // handle the original form submit event only if no search result has been selected before
        if (this._iterator <= ARROW_NAVIGATION_ITERATOR_DEFAULT) {
            return;
        }

        try {
            const a = DomAccess.querySelector(this._getCurrentSelection(), 'a');
            event.preventDefault();
            a.click();
        } catch (e) {
            // do nothing, if no link has been found in result item
        }
    }

    /**
     * Return the currently selected search result item
     * @returns {Element}
     * @private
     */
    _getCurrentSelection() {
        return this._items[this._iterator];
    }

    /**
     * Method to compromise the "out of bounds" case that occurs
     * if the iterator runs below zero or above the max threshold
     * @private
     */
    _clampIterator() {
        const max = this._getMaxItemCount();

        // set iterator to last item if below zero
        if (this._iterator < 0) {
            this._iterator = (this._infinite) ? max : 0;
        }

        // set iterator to first item if above max
        if (this._iterator > max) {
            this._iterator = (this._infinite) ? 0 : max;
        }
    }

    /**
     * Returns the upper bound of iterations by
     * using the amount of existing iterables
     * @returns {number}
     * @private
     */
    _getMaxItemCount() {
        return this._items.length - 1;
    }
}
