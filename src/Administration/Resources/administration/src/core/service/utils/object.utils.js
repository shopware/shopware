/**
 * @module core/service/utils/object
 */

import type from './types.utils';

export default {
    deepCopyObject,
    getObjectChangeSet
};

/**
 * Deep copy an object
 *
 * @param {Object} copyObject
 * @returns {Object}
 */
export function deepCopyObject(copyObject = {}) {
    return JSON.parse(JSON.stringify(copyObject));
}

export function getObjectChangeSet(baseObject, compareObject) {
    if (baseObject === compareObject) {
        return {};
    }

    if (!type.isObject(baseObject) || !type.isObject(compareObject)) {
        return compareObject;
    }

    if (type.isDate(baseObject) || type.isDate(compareObject)) {
        if (baseObject.valueOf() === compareObject.valueOf()) {
            return {};
        }

        return compareObject;
    }

    const b = { ...baseObject };
    const c = { ...compareObject };

    return Object.keys(c).reduce((acc, key) => {
        if (b.hasOwnProperty(key)) {
            if (type.isArray(b[key])) {
                const arrayDiff = getArrayChangeSet(b[key], c[key]);

                if (type.isArray(arrayDiff) && arrayDiff.length === 0) {
                    return acc;
                }

                return { ...acc, [key]: arrayDiff };
            }

            const diff = getObjectChangeSet(b[key], c[key]);

            if (type.isObject(diff) && type.isEmpty(diff) && !type.isDate(diff)) {
                return acc;
            }

            if (type.isObject(b[key]) && b[key].id) {
                diff.id = b[key].id;
            }

            return { ...acc, [key]: diff };
        }

        return { ...acc, [key]: c[key] };
    }, {});
}

function getArrayChangeSet(baseArray, compareArray) {
    if (baseArray === compareArray) {
        return [];
    }

    if (!type.isArray(baseArray) || !type.isArray(compareArray)) {
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

    if (!type.isObject(b[0]) || !type.isObject(c[0])) {
        return c.filter(value => b.indexOf(value) < 0);
    }

    const diff = [];

    c.forEach((item, index) => {
        if (!item.id) {
            const diffObject = getObjectChangeSet(b[index], c[index]);

            if (type.isObject(diffObject) && !type.isEmpty(diffObject)) {
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

                if (type.isObject(diffObject) && !type.isEmpty(diffObject)) {
                    diff.push({ ...diffObject, id: item.id });
                }
            }
        }
    });

    return diff;
}
