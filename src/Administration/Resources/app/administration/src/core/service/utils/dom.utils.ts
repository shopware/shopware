/**
 * @package admin
 *
 * @module core/service/utils/dom
 */

import { warn } from './debug.utils';

/**
 * Returns the scrollbar height of an HTML element.
 */
function getScrollbarHeight(element: HTMLElement): number {
    if (!(element instanceof HTMLElement)) {
        warn('DOM Utilities', 'The provided element needs to be an instance of "HTMLElement".', element);
        return 0;
    }
    return element.offsetHeight - element.clientHeight;
}

/**
 * Returns the scrollbar width of an HTML element.
 */
function getScrollbarWidth(element: HTMLElement): number {
    if (!(element instanceof HTMLElement)) {
        warn('DOM Utilities', 'The provided element needs to be an instance of "HTMLElement".', element);
        return 0;
    }
    return element.offsetWidth - element.clientWidth;
}

async function copyStringToClipboard(stringToCopy: string): Promise<void> {
    await navigator.clipboard.writeText(stringToCopy);
}

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    getScrollbarHeight,
    getScrollbarWidth,
    copyStringToClipboard,
};
