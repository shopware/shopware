import MD5 from 'md5-es';
import types from 'src/core/service/utils/types.utils';

/**
 * @module core/service/utils/format
 */
export default {
    currency,
    date,
    md5,
    fileSize,
};

/**
 * Converts a Number to a formatted currency. Especially helpful for template filters.
 * Defaults to the currencyISOCode of the standard currency and locale of the user.
 *
 * @param {Number} val - Number which should be formatted as a currency.
 * @param {String} sign - Currency sign which should be displayed
 * @param {Number} [decimalPlaces] - Number of decimal places
 * @param {Object} additionalOptions
 * @returns {string} Formatted string
 */
export function currency(val, sign, decimalPlaces, additionalOptions = {}) {
    const decimalOpts = decimalPlaces !== undefined ? {
        minimumFractionDigits: decimalPlaces,
        maximumFractionDigits: decimalPlaces,
    } : {
        minimumFractionDigits: 2,
        maximumFractionDigits: 20,
    };

    const opts = {
        style: 'currency',
        currency: sign || Shopware.Context.app.systemCurrencyISOCode,
        ...decimalOpts,
        ...additionalOptions,
    };

    return val.toLocaleString((additionalOptions.language ?? Shopware.State.get('session').currentLocale) ?? 'en-US', opts);
}

/**
 * Formats a Date object to a localized string
 *
 * @param {Date} val
 * @param {Object} options
 * @returns {string}
 */
let dateTimeFormatter;
let dateTimeOptions;
export function date(val, options = {}) {
    if (val === null) {
        return '';
    }
    const dateObj = new Date(val);
    // eslint-disable-next-line
    if (isNaN(dateObj)) {
        return '';
    }

    const lastKnownLang = Shopware.Application.getContainer('factory').locale.getLastKnownLocale();
    const defaultOptions = {
        day: '2-digit',
        month: '2-digit',
        year: '2-digit',
    };
    options = { ...defaultOptions, ...options };

    if (!types.isEqual(dateTimeOptions, options)) {
        dateTimeOptions = options;
    }

    dateTimeFormatter = new Intl.DateTimeFormat(lastKnownLang, dateTimeOptions);

    return dateTimeFormatter.format(dateObj);
}

/**
 * Generates a md5 hash of the given value.
 *
 * @param {String} value
 * @return {String}
 */
export function md5(value) {
    return MD5.hash(value);
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

    return `${result.toFixed(2).toLocaleString(locale)}${units[i]}`;
}
