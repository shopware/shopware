import crypto from 'crypto';

/**
 * @module core/service/utils/format
 */
export default {
    currency,
    date,
    md5,
    fileSize
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
export function date(val, locale = 'de-DE', options = {}) {
    const dateObj = new Date(val);
    return dateObj.toLocaleString(locale, options);
}

/**
 * Generates a md5 hash of the given value.
 *
 * @param {String} value
 * @return {String}
 */
export function md5(value) {
    return crypto.createHash('md5').update(value).digest('hex');
}

/**
 * Formats a number of bytes to a string with a unit
 *
 * @param {int} bytes
 * @returns {string}
 */
export function fileSize(bytes, locale = 'de-DE') {
    const denominator = 1024;
    const units = ['B', 'KB', 'MB', 'GB'];

    let result = Number.parseInt(bytes, 10);
    let i = 0;

    for (; i < units.length; i += 1) {
        const currentResult = result / denominator;

        if (currentResult < 0.9) {
            break;
        }

        result = currentResult;
    }

    return result.toFixed(2).toLocaleString(locale) + units[i];
}
