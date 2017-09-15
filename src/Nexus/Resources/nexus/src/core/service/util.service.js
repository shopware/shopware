import { updatedDiff } from 'deep-object-diff';
import uuidv4 from 'uuid/v4';

export default {
    merge,
    formDataToObject,
    warn,
    currency,
    compareObjects: updatedDiff,
    createUuid: uuidv4
};

// Todo: This has an issue when you want to copy into a new object
function merge(target, source) {
    Object.keys(source).forEach((key) => {
        if (source[key] instanceof Object) {
            if (!target[key]) {
                Object.assign(target, { [key]: {} });
            }
            Object.assign(source[key], merge(target[key], source[key]));
        }
    });

    Object.assign(target || {}, source);
    return target;
}

/**
 * Transforms FormData to a plain & simple object which can be used with the HTTP client for example.
 *
 * @param {FormData} formData
 * @returns {Object}
 */
function formDataToObject(formData) {
    return Array.from(formData).reduce((result, item) => {
        result[item[0]] = item[1];
        return result;
    }, {});
}

/**
 * General logging function which provides a unified style of log messages for developers. Please keep in mind the log
 * messages will be displayed in the developer console when they're running the application in development mode.
 *
 * @param {String} name
 * @param {String|Object|Array} message
 */
function warn(name = 'Core', ...message) {
    if (process.env.NODE_ENV !== 'production' && typeof console !== 'undefined') {
        message.unshift(`[${name}]`);
        console.warn.apply(this, message);
    }
}

/**
 * Converts a Number to a formatted currency. Especially helpful for template filters.
 *
 * @param {Number} val - Number which should be formatted as a currency.
 * @returns {string} Formatted string
 */
function currency(val) {
    const opts = {
        style: 'currency',
        currency: 'EUR'
    };
    return val.toLocaleString('de-DE', opts);
}
