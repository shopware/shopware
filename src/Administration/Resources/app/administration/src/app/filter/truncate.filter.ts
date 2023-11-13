/* eslint-disable @typescript-eslint/no-inferrable-types */

/**
 * @package admin
 *
 * @deprecated tag:v6.6.0 - Will be private
 * Filter which allows you to truncate a string.
 */
Shopware.Filter.register('truncate', (
    value: string = '',
    length: number = 75,
    stripHtml: boolean = true,
    ellipsis: string = '...',
) => {
    if (!value || !value.length) {
        return '';
    }

    // Strip HTML
    const strippedValue = (stripHtml ? value.replace(/<\/?("[^"]*"|'[^']*'|[^>])*(>|$)/g, '') : value);

    // The string is smaller than the max length, we don't have to do anything
    if (strippedValue.length <= length) {
        return strippedValue;
    }

    // Truncate the string
    const truncatedString = strippedValue.slice(0, (length - ellipsis.length));
    return `${truncatedString}${ellipsis}`;
});

/* @private */
export {};
