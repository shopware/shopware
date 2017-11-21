import uuidv4 from 'uuid/v4';

export default {
    merge,
    formDataToObject,
    warn,
    currency,
    date,
    getObjectChangeSet,
    createUuid: uuidv4,
    isObject,
    isPlainObject,
    isEmpty,
    isRegExp,
    isArray,
    isFunction,
    isDate
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

function date(val, locale = 'de-DE') {
    return val.toLocaleString(locale);
}

function isObject(object) {
    return object !== null && typeof object === 'object';
}

function isPlainObject(obj) {
    return obj.toString() === '[object Object]';
}

function isEmpty(object) {
    return Object.keys(object).length === 0;
}

function isRegExp(exp) {
    return exp.toString() === '[object RegExp]';
}

function isArray(array) {
    return Array.isArray(array);
}

function isFunction(func) {
    return func !== null && typeof func === 'function';
}

function isDate(dateObject) {
    return dateObject instanceof Date;
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

            if (isObject(b[key]) && b[key].uuid) {
                diff.uuid = b[key].uuid;
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
        if (!item.uuid) {
            const diffObject = getObjectChangeSet(b[index], c[index]);

            if (isObject(diffObject) && !isEmpty(diffObject)) {
                diff.push(diffObject);
            }
        } else {
            const compareObject = b.find((compareItem) => {
                return item.uuid === compareItem.uuid;
            });

            if (!compareObject) {
                diff.push(item);
            } else {
                const diffObject = getObjectChangeSet(compareObject, item);

                if (isObject(diffObject) && !isEmpty(diffObject)) {
                    diff.push({ ...diffObject, uuid: item.uuid });
                }
            }
        }
    });

    return diff;
}
