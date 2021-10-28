/**
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
    return (element.offsetHeight - element.clientHeight);
}

/**
 * Returns the scrollbar width of an HTML element.
 */
function getScrollbarWidth(element: HTMLElement): number {
    if (!(element instanceof HTMLElement)) {
        warn('DOM Utilities', 'The provided element needs to be an instance of "HTMLElement".', element);
        return 0;
    }
    return (element.offsetWidth - element.clientWidth);
}

/**
 * uses the browser's copy function to copy a string
 */
function copyToClipboard(stringToCopy: string): void {
    const tempTextArea = document.createElement('textarea');
    tempTextArea.value = stringToCopy;
    document.body.appendChild(tempTextArea);
    tempTextArea.select();
    document.execCommand('copy');
    document.body.removeChild(tempTextArea);
}

export default {
    getScrollbarHeight,
    getScrollbarWidth,
    copyToClipboard,
};
