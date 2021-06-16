const { types } = Shopware.Utils;

/**
 * @module core/service/validation
 */
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
export function email(value) {
    const emailValidation = /^(([^<>()\[\]\\.,;:\s@"]+(\.[^<>()\[\]\\.,;:\s@"]+)*)|(".+"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/;

    return regex(value, emailValidation);
}
