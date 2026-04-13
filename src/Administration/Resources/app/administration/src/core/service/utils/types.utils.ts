/**
 * @sw-package framework
 *
 * @module core/service/utils/types
 */

import isObject from 'lodash-es/isObject';
import isPlainObject from 'lodash-es/isPlainObject';
import isEmpty from 'lodash-es/isEmpty';
import isRegExp from 'lodash-es/isRegExp';
import isArray from 'lodash-es/isArray';
import isFunction from 'lodash-es/isFunction';
import isDate from 'lodash-es/isDate';
import isString from 'lodash-es/isString';
import isBoolean from 'lodash-es/isBoolean';
import isEqual from 'lodash-es/isEqual';
import isNumber from 'lodash-es/isNumber';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    isObject,
    isPlainObject,
    isEmpty,
    isRegExp,
    isArray,
    isFunction,
    isDate,
    isString,
    isBoolean,
    isEqual,
    isNumber,
    isUndefined,
};

/**
 * Checks if a value is undefined
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export function isUndefined(value: unknown): boolean {
    return typeof value === 'undefined';
}
