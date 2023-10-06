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
 * @deprecated tag:v6.6.0 - The document.execCommand() API is deprecated, use copyStringToClipBoard instead
 */
function copyToClipboard(stringToCopy: string): void {
    const tempTextArea = document.createElement('textarea');
    tempTextArea.value = stringToCopy;
    document.body.appendChild(tempTextArea);
    tempTextArea.select();
    document.execCommand('copy');
    document.body.removeChild(tempTextArea);
}

async function copyStringToClipboard(stringToCopy: string): Promise<void> {
    await navigator.clipboard.writeText(stringToCopy);
}

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    getScrollbarHeight,
    getScrollbarWidth,
    copyToClipboard,
    copyStringToClipboard,
};
