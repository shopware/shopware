/**
 * @module core/service/utils
 */
import throttle from 'lodash/throttle';
import flow from 'lodash/flow';
import debounce from 'lodash/debounce';
import flattenDeep from 'lodash/flattenDeep';
import uuidV4 from 'uuid/v4';
import remove from 'lodash/remove';
import slice from 'lodash/slice';
import uniqBy from 'lodash/uniqBy';

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
import { currency, date, fileSize, md5 } from './utils/format.utils';
import domUtils from './utils/dom.utils';
import stringUtils from './utils/string.utils';
import typesUtils, { isUndefined } from './utils/types.utils';
import fileReaderUtils from './utils/file-reader.utils';
import sortUtils from './utils/sort.utils';

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

export const debug = {
    warn: warn,
    error: error,
};

export const format = {
    currency: currency,
    date: date,
    fileSize: fileSize,
    md5: md5,
};

export const dom = {
    getScrollbarHeight: domUtils.getScrollbarHeight,
    getScrollbarWidth: domUtils.getScrollbarWidth,
    copyToClipboard: domUtils.copyToClipboard,
};

export const string = {
    capitalizeString: stringUtils.capitalizeString,
    camelCase: stringUtils.camelCase,
    kebabCase: stringUtils.kebabCase,
    snakeCase: stringUtils.snakeCase,
    md5: md5,
    isEmptyOrSpaces: stringUtils.isEmptyOrSpaces,
    isUrl: stringUtils.isUrl,
    isValidIp: stringUtils.isValidIp,
};

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

export const fileReader = {
    readAsArrayBuffer: fileReaderUtils.readFileAsArrayBuffer,
    readAsDataURL: fileReaderUtils.readFileAsDataURL,
    readAsText: fileReaderUtils.readFileAsText,
    getNameAndExtensionFromFile: fileReaderUtils.getNameAndExtensionFromFile,
    getNameAndExtensionFromUrl: fileReaderUtils.getNameAndExtensionFromUrl,
};

export const sort = {
    afterSort: sortUtils.afterSort,
};

export const array = {
    flattenDeep: flattenDeep,
    remove: remove,
    slice: slice,
    uniqBy: uniqBy,
};

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
};

/**
 * Returns a uuid string in hex format.
 *
 * @returns {String}
 */
function createId() {
    return uuidV4().replace(/-/g, '');
}
