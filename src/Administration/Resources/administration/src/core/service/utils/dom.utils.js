/**
 * @module core/service/utils/dom
 */

import { warn } from './debug.utils';

/**
 * Returns the scrollbar height of an HTML element.
 *
 * @param {Object} element
 * @returns {number} Scrollbar height
 */
function getScrollbarHeight(element) {
    if (!(element instanceof HTMLElement)) {
        warn('DOM Utilities', 'The provided element needs to be an instance of "HTMLElement".', element);
        return 0;
    }
    return (element.offsetHeight - element.scrollHeight);
}

/**
 * Returns the scrollbar width of an HTML element.
 *
 * @param {Object} element
 * @returns {number} Scrollbar width
 */
function getScrollbarWidth(element) {
    if (!(element instanceof HTMLElement)) {
        warn('DOM Utilities', 'The provided element needs to be an instance of "HTMLElement".', element);
        return 0;
    }
    return (element.offsetWidth - element.scrollWidth);
}

export default {
    getScrollbarHeight,
    getScrollbarWidth
};
