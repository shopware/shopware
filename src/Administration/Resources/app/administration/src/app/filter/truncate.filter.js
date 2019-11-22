const { Filter } = Shopware;

/**
 * Filter which allows you to truncate a string.
 *
 * @param {String} [value='']
 * @param {Number} [length=75]
 * @param {Boolean} [stripHtml=true]
 * @param {String} [ellipsis='...']
 */
Filter.register('truncate', (value = '', length = 75, stripHtml = true, ellipsis = '...') => {
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
