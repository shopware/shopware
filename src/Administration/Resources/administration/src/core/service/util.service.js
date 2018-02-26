/**
 * @module core/service/utils
 */
import throttle from 'lodash/throttle';
import uuidV4 from 'uuid/v4';

import { deepCopyObject, getObjectChangeSet } from './utils/object.utils';
import { warn } from './utils/debug.utils';
import { currency, date } from './utils/format.utils';
import stringUtils from './utils/string.utils';
import typesUtils from './utils/types.utils';

export const object = {
    deepCopyObject: deepCopyObject,
    getObjectChangeSet: getObjectChangeSet
};

export const debug = {
    warn: warn
};

export const format = {
    currency: currency,
    date: date
};

export const string = {
    capitalizeString: stringUtils.capitalizeString,
    camelCase: stringUtils.camelCase
};

export const types = {
    isObject: typesUtils.isObject,
    isPlainObject: typesUtils.isPlainObject,
    isEmpty: typesUtils.isEmpty,
    isRegExp: typesUtils.isRegExp,
    isArray: typesUtils.isArray,
    isFunction: typesUtils.isFunction,
    isDate: typesUtils.isDate,
    isString: typesUtils.isString
};

export default {
    createId: uuidV4,
    throttle,
    object,
    debug,
    format,
    string,
    types
};
