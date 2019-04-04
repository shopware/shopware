/**
 * @module core/service/utils
 */
import throttle from 'lodash/throttle';
import debounce from 'lodash/debounce';
import get from 'lodash/get';
import uuidV4 from 'uuid/v4';

import {
    deepCopyObject,
    hasOwnProperty,
    getObjectDiff,
    getArrayChanges,
    merge
} from './utils/object.utils';
import { warn } from './utils/debug.utils';
import { currency, date, fileSize } from './utils/format.utils';
import domUtils from './utils/dom.utils';
import stringUtils from './utils/string.utils';
import typesUtils from './utils/types.utils';
import fileReaderUtils from './utils/file-reader.utils';
import sortUtils from './utils/sort.utils';

export const object = {
    deepCopyObject: deepCopyObject,
    hasOwnProperty: hasOwnProperty,
    getObjectDiff: getObjectDiff,
    getArrayChanges: getArrayChanges,
    merge: merge
};

export const debug = {
    warn: warn
};

export const format = {
    currency: currency,
    date: date,
    fileSize: fileSize
};

export const dom = {
    getScrollbarHeight: domUtils.getScrollbarHeight,
    getScrollbarWidth: domUtils.getScrollbarWidth
};

export const string = {
    capitalizeString: stringUtils.capitalizeString,
    camelCase: stringUtils.camelCase,
    md5: stringUtils.md5
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
    isNumber: typesUtils.isNumber
};

export const fileReader = {
    readAsArrayBuffer: fileReaderUtils.readFileAsArrayBuffer,
    readAsDataURL: fileReaderUtils.readFileAsDataURL,
    readAsText: fileReaderUtils.readFileAsText
};

export const sort = {
    afterSort: sortUtils.afterSort
};

export default {
    createId,
    throttle,
    debounce,
    get,
    object,
    debug,
    format,
    dom,
    string,
    types,
    fileReader,
    sort
};

/**
 * Returns a uuid string in hex format.
 *
 * @returns {String}
 */
function createId() {
    return uuidV4().replace(/-/g, '');
}
