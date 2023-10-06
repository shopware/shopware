import objectMerge from 'lodash/merge';
import objectMergeWith from 'lodash/mergeWith';
import objectCopy from 'lodash/cloneDeep';
import objectGet from 'lodash/get';
import objectSet from 'lodash/set';
import objectPick from 'lodash/pick';
import type from 'src/core/service/utils/types.utils';

/**
 * @package admin
 *
 * @module core/service/utils/object
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    deepCopyObject,
    hasOwnProperty,
    getObjectDiff,
    getArrayChanges,
    merge: objectMerge,
    mergeWith: objectMerge,
    cloneDeep: objectCopy,
    get: objectGet,
    set: objectSet,
    pick: objectPick,
};

/**
 * Lodash import for object merges.
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export const merge = objectMerge;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export const mergeWith = objectMergeWith;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export const cloneDeep = objectCopy;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export const get = objectGet;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export const set = objectSet;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export const pick = objectPick;

/**
 * Shorthand method for `Object.prototype.hasOwnProperty`
 */
// eslint-disable-next-line @typescript-eslint/no-explicit-any, sw-deprecation-rules/private-feature-declarations
export function hasOwnProperty(scope: any, prop: string): boolean {
    return Object.prototype.hasOwnProperty.call(scope, prop);
}

/**
 * Deep copy an object
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export function deepCopyObject<O extends object>(copyObject: O): O {
    return JSON.parse(JSON.stringify(copyObject)) as O;
}

/**
 * Deep merge two objects
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export function deepMergeObject<FO extends object, SO extends object>(firstObject: FO, secondObject: SO): FO & SO {
    return mergeWith(firstObject, secondObject, (objValue, srcValue) => {
        if (Array.isArray(objValue)) {
            // eslint-disable-next-line @typescript-eslint/no-unsafe-return
            return objValue.concat(srcValue);
        }
        return undefined;
    });
}

/**
 * Get a simple recursive diff of two objects.
 * Does not consider an entity schema or entity related logic.
 *
 * @param {Object} a
 * @param {Object} b
 * @return {*}
 */
// eslint-disable-next-line @typescript-eslint/no-explicit-any, sw-deprecation-rules/private-feature-declarations
export function getObjectDiff(a: any, b: any): any {
    if (a === b) {
        return {};
    }

    if (!type.isObject(a) || !type.isObject(b)) {
        return b;
    }

    if (type.isDate(a) || type.isDate(b)) {
        if (a.valueOf() === b.valueOf()) {
            return {};
        }

        return b;
    }

    return Object.keys(b).reduce((acc, key) => {
        if (!hasOwnProperty(a, key)) {
            // @ts-expect-error
            // eslint-disable-next-line @typescript-eslint/no-unsafe-assignment
            return { ...acc, [key]: b[key] };
        }

        // @ts-expect-error
        if (type.isArray(b[key])) {
            // @ts-expect-error
            // eslint-disable-next-line @typescript-eslint/no-unsafe-assignment,@typescript-eslint/no-unsafe-argument
            const changes = getArrayChanges(a[key], b[key]);

            // eslint-disable-next-line @typescript-eslint/no-unsafe-argument
            if (Object.keys(changes).length > 0) {
                // @ts-expect-error
                // eslint-disable-next-line @typescript-eslint/no-unsafe-assignment
                return { ...acc, [key]: b[key] };
            }

            return acc;
        }

        // @ts-expect-error
        if (type.isObject(b[key])) {
            // @ts-expect-error
            // eslint-disable-next-line @typescript-eslint/no-unsafe-assignment
            const changes = getObjectDiff(a[key], b[key]);

            if (!type.isObject(changes) || Object.keys(changes).length > 0) {
                // eslint-disable-next-line @typescript-eslint/no-unsafe-assignment
                return { ...acc, [key]: changes };
            }

            return acc;
        }

        // @ts-expect-error
        if (a[key] !== b[key]) {
            // @ts-expect-error
            // eslint-disable-next-line @typescript-eslint/no-unsafe-assignment
            return { ...acc, [key]: b[key] };
        }

        return acc;
    }, {});
}

/**
 * Check if the compared array has changes.
 * Works a little bit different like the object diff because it does not return a real changeset.
 * In case of a change we will always use the complete compare array,
 * because it always holds the newest state regarding deletions, additions and the order.
 */
// eslint-disable-next-line @typescript-eslint/no-explicit-any, sw-deprecation-rules/private-feature-declarations
export function getArrayChanges(a: any[], b: any[]): any[] {
    if (a === b) {
        return [];
    }

    if (!type.isArray(a) || !type.isArray(b)) {
        // eslint-disable-next-line @typescript-eslint/no-unsafe-return
        return b;
    }

    if (a.length <= 0 && b.length <= 0) {
        return [];
    }

    if (a.length !== b.length) {
        return b;
    }

    if (!type.isObject(b[0])) {
        return b.filter(item => !a.includes(item));
    }

    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    const changes: any[] = [];

    b.forEach((item, index) => {
        // eslint-disable-next-line @typescript-eslint/no-unsafe-assignment
        const objDiff = getObjectDiff(a[index], b[index]);

        // eslint-disable-next-line @typescript-eslint/no-unsafe-argument
        if (Object.keys(objDiff).length > 0) {
            changes.push(b[index]);
        }
    });

    return changes;
}
