/**
 * @package admin
 *
 * @module core/service/utils
 */
import throttle from 'lodash/throttle';
import flow from 'lodash/flow';
import debounce from 'lodash/debounce';
import flattenDeep from 'lodash/flattenDeep';
import { uuidv7 } from 'uuidv7';
import remove from 'lodash/remove';
import slice from 'lodash/slice';
import uniqBy from 'lodash/uniqBy';
import chunk from 'lodash/chunk';
import intersectionBy from 'lodash/intersectionBy';

import {
    deepCopyObject,
    hasOwnProperty,
    getObjectDiff,
    getArrayChanges,
    cloneDeep,
    merge,
    mergeWith,
    deepMergeObject,
    get,
    set,
    pick,
} from './utils/object.utils';
import { warn, error } from './utils/debug.utils';
import { currency, date, dateWithUserTimezone, fileSize, md5, toISODate } from './utils/format.utils';
import domUtils from './utils/dom.utils';
import stringUtils from './utils/string.utils';
import typesUtils, { isUndefined } from './utils/types.utils';
import fileReaderUtils from './utils/file-reader.utils';
import sortUtils from './utils/sort.utils';
import VueHelper from './utils/vue-helper.utils';
import EventBus from './utils/eventBus.utils';
import genericRuleConditionUtils from './utils/generic-rule-condition.utils';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export const object = {
    deepCopyObject: deepCopyObject,
    hasOwnProperty: hasOwnProperty,
    getObjectDiff: getObjectDiff,
    getArrayChanges: getArrayChanges,
    cloneDeep: cloneDeep,
    merge: merge,
    mergeWith: mergeWith,
    deepMergeObject: deepMergeObject,
    get: get,
    set: set,
    pick: pick,
};

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export const debug = {
    warn: warn,
    error: error,
};

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export const format = {
    currency: currency,
    date: date,
    dateWithUserTimezone: dateWithUserTimezone,
    fileSize: fileSize,
    md5: md5,
    toISODate: toISODate,
};

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export const dom = {
    getScrollbarHeight: domUtils.getScrollbarHeight,
    getScrollbarWidth: domUtils.getScrollbarWidth,
    copyStringToClipboard: domUtils.copyStringToClipboard,
};

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export const string = {
    capitalizeString: stringUtils.capitalizeString,
    camelCase: stringUtils.camelCase,
    upperFirst: stringUtils.upperFirst,
    kebabCase: stringUtils.kebabCase,
    snakeCase: stringUtils.snakeCase,
    md5: md5,
    isEmptyOrSpaces: stringUtils.isEmptyOrSpaces,
    isUrl: stringUtils.isUrl,
    isValidIp: stringUtils.isValidIp,
    isValidCidr: stringUtils.isValidCidr,
};

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export const types = {
    isObject: typesUtils.isObject,
    isPlainObject: typesUtils.isPlainObject,
    isEmpty: typesUtils.isEmpty,
    isRegExp: typesUtils.isRegExp,
    isArray: typesUtils.isArray,
    isFunction: typesUtils.isFunction,
    isDate: typesUtils.isDate,
    isString: typesUtils.isString,
    isBoolean: typesUtils.isBoolean,
    isEqual: typesUtils.isEqual,
    isNumber: typesUtils.isNumber,
    isUndefined: isUndefined,
};

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export const fileReader = {
    readAsArrayBuffer: fileReaderUtils.readFileAsArrayBuffer,
    readAsDataURL: fileReaderUtils.readFileAsDataURL,
    readAsText: fileReaderUtils.readFileAsText,
    getNameAndExtensionFromFile: fileReaderUtils.getNameAndExtensionFromFile,
    getNameAndExtensionFromUrl: fileReaderUtils.getNameAndExtensionFromUrl,
};

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export const sort = {
    afterSort: sortUtils.afterSort,
};

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export const array = {
    flattenDeep: flattenDeep,
    remove: remove,
    slice: slice,
    uniqBy: uniqBy,
    chunk: chunk,
    intersectionBy: intersectionBy,
};

/**
 * @private
 */
export const genericRuleCondition = {
    getPlaceholderSnippet: genericRuleConditionUtils.getPlaceholderSnippet,
};

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    createId,
    throttle,
    debounce,
    flow,
    get,
    object,
    debug,
    format,
    dom,
    string,
    types,
    fileReader,
    sort,
    array,
    moveItem,
    VueHelper,
    EventBus,
    genericRuleCondition,
};

/**
 * Returns an uuid string in hex format.
 *
 * @returns { String }
 */
function createId(): string {
    // eslint-disable-next-line max-len
    // eslint-disable-next-line @typescript-eslint/no-unsafe-call,@typescript-eslint/no-unsafe-return,@typescript-eslint/no-unsafe-member-access
    return uuidv7().replace(/-/g, '');
}

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export function moveItem(entity: MutationObserver[], oldIndex: number, newIndex: number) {
    if (newIndex === null) {
        newIndex = entity.length;
    }

    if (oldIndex < 0 || oldIndex >= entity.length || newIndex === oldIndex) {
        return;
    }

    const movedItem = entity.find((_, index) => index === oldIndex);
    if (!movedItem) {
        return;
    }

    const remainingItems = entity.filter((_, index) => index !== oldIndex);

    const orderedItems = [
        ...remainingItems.slice(0, newIndex),
        movedItem,
        ...remainingItems.slice(newIndex),
    ];

    entity.splice(0, entity.length, ...orderedItems);
}
