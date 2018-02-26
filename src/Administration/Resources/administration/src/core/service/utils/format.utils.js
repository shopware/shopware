/**
 * @module core/service/utils/format
 */
export default {
    currency,
    date
};

/**
 * Converts a Number to a formatted currency. Especially helpful for template filters.
 *
 * @param {Number} val - Number which should be formatted as a currency.
 * @param {String} sign - Currency sign which should be displayed
 * @returns {string} Formatted string
 */
export function currency(val, sign) {
    const opts = {
        style: 'currency',
        currency: sign || 'EUR'
    };
    let language = 'de-DE';
    if (opts.currency === 'USD') {
        language = 'en-US';
    }
    return val.toLocaleString(language, opts);
}

/**
 * Formats a Date object to a localized string
 *
 * @param {Date} val
 * @param {String} [locale='de-DE']
 * @returns {string}
 */
export function date(val, locale = 'de-DE') {
    return val.toLocaleString(locale);
}
