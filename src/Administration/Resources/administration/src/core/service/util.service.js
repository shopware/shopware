/**
 * @module core/service/utils
 */

import uuidv4 from 'uuid/v4';

export default {
    formDataToObject,
    warn,
    currency,
    date,
    deepCopyObject,
    getObjectChangeSet,
    createId: uuidv4,
    isObject,
    isPlainObject,
    isEmpty,
    isRegExp,
    isArray,
    isFunction,
    isDate,
    isString,
    capitalizeString,
    debounce
};

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
 * @param {String} sign - Currency sign which should be displayed
 * @returns {string} Formatted string
 */
function currency(val, sign) {
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
function date(val, locale = 'de-DE') {
    return val.toLocaleString(locale);
}

/**
 * Checks if the provided argument is an object
 *
 * @param {any} object Object to check
 * @returns {boolean}
 */
function isObject(object) {
    return (object instanceof Object && !(object instanceof Array));
}

/**
 * Checks if the provided argument is a plain object
 *
 * @param {any} obj
 * @returns {boolean}
 */
function isPlainObject(obj) {
    return obj.toString() === '[object Object]';
}

/**
 * Checks if the provided argument is an empty object
 *
 * @param {Object} object
 * @returns {boolean}
 */
function isEmpty(object) {
    return Object.keys(object).length === 0;
}

/**
 * Checks if the provided argument is a regular expression
 *
 * @param {any} exp
 * @returns {boolean}
 */
function isRegExp(exp) {
    return exp.toString() === '[object RegExp]';
}

/**
 * Checks if the provided argument is an array
 * @param {any} array
 * @returns {boolean}
 */
function isArray(array) {
    return Array.isArray(array);
}

/**
 *
 * @param func
 * @returns {boolean}
 */
function isFunction(func) {
    return func !== null && typeof func === 'function';
}

/**
 * Checks if the provided argument is a date object
 *
 * @param {any} dateObject
 * @returns {boolean}
 */
function isDate(dateObject) {
    return dateObject instanceof Date;
}

/**
 * Checks if the provided argument is a string
 *
 * @param {String|Number|Object|Array} obj
 * @returns {boolean}
 */
function isString(obj) {
    return (Object.prototype.toString.call(obj) === '[object String]');
}

let debounceTimeout;

/**
 * Debounces a function call.
 *
 * @param {Function} callback
 * @param {Number} debounceTime
 * @returns {Number}
 */
function debounce(callback, debounceTime) {
    window.clearTimeout(debounceTimeout);

    debounceTimeout = window.setTimeout(callback, debounceTime);
    return debounceTimeout;
}

/**
 * Deep copy an object
 *
 * @param {Object} copyObject
 * @returns {Object}
 */
function deepCopyObject(copyObject = {}) {
    return JSON.parse(JSON.stringify(copyObject));
}

function getObjectChangeSet(baseObject, compareObject) {
    if (baseObject === compareObject) {
        return {};
    }

    if (!isObject(baseObject) || !isObject(compareObject)) {
        return compareObject;
    }

    if (isDate(baseObject) || isDate(compareObject)) {
        if (baseObject.valueOf() === compareObject.valueOf()) {
            return {};
        }

        return compareObject;
    }

    const b = { ...baseObject };
    const c = { ...compareObject };

    return Object.keys(c).reduce((acc, key) => {
        if (b.hasOwnProperty(key)) {
            if (isArray(b[key])) {
                const arrayDiff = getArrayChangeSet(b[key], c[key]);

                if (isArray(arrayDiff) && arrayDiff.length === 0) {
                    return acc;
                }

                return { ...acc, [key]: arrayDiff };
            }

            const diff = getObjectChangeSet(b[key], c[key]);

            if (isObject(diff) && isEmpty(diff) && !isDate(diff)) {
                return acc;
            }

            if (isObject(b[key]) && b[key].id) {
                diff.id = b[key].id;
            }

            return { ...acc, [key]: diff };
        }

        return acc;
    }, {});
}

function getArrayChangeSet(baseArray, compareArray) {
    if (baseArray === compareArray) {
        return [];
    }

    if (!isArray(baseArray) || !isArray(compareArray)) {
        return compareArray;
    }

    if (baseArray.length === 0) {
        return compareArray;
    }

    if (compareArray.length === 0) {
        return baseArray;
    }

    const b = [...baseArray];
    const c = [...compareArray];

    if (!isObject(b[0]) || !isObject(c[0])) {
        return c.filter(value => b.indexOf(value) < 0);
    }

    const diff = [];

    c.forEach((item, index) => {
        if (!item.id) {
            const diffObject = getObjectChangeSet(b[index], c[index]);

            if (isObject(diffObject) && !isEmpty(diffObject)) {
                diff.push(diffObject);
            }
        } else {
            const compareObject = b.find((compareItem) => {
                return item.id === compareItem.id;
            });

            if (!compareObject) {
                diff.push(item);
            } else {
                const diffObject = getObjectChangeSet(compareObject, item);

                if (isObject(diffObject) && !isEmpty(diffObject)) {
                    diff.push({ ...diffObject, id: item.id });
                }
            }
        }
    });

    return diff;
}

/**
 * Capitalizes the first character of the provided argument.
 *
 * @param {String} str String which should be transformed
 * @returns {String} Transformed string
 */
function capitalizeString(str) {
    return str.charAt(0).toUpperCase() + str.slice(1);
}
