import {response} from "express";

const { types } = Shopware.Utils;

/**
 * @sw-package framework
 *
 * @module core/service/validation
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    required,
    regex,
    email,
};

/**
 * Checks if a value is set based on its type.
 *
 * @memberOf module:core/service/validation
 * @param value
 * @returns {boolean}
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export function required(value) {
    if (typeof value === 'string' && value.length <= 0) {
        return false;
    }

    if (typeof value === 'boolean') {
        return value === true;
    }

    if (types.isObject(value)) {
        return Object.keys(value).length > 0;
    }

    return typeof value !== 'undefined' && value !== null;
}

/**
 * Checks the value against the given regular expression.
 *
 * @memberOf module:core/service/validation
 * @param value
 * @param expression
 * @returns {boolean}
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export function regex(value, expression) {
    if (expression instanceof RegExp) {
        return expression.test(value);
    }

    return new RegExp(expression).test(value);
}

/**
 * Checks if the value is a valid email address.
 *
 * @memberOf module:core/service/validation
 * @param value
 * @returns {boolean}
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export async function email(value) {
    // const validationApiService = Shopware.Application.getContainer('service').validationApiService;
    //
    // return validationApiService.validateEmailAddress(value);

    return false;
}
