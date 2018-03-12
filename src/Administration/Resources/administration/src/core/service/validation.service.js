export default {
    required,
    regex,
    email
};

export function required(value) {
    if (typeof value === 'string' && value.length <= 0) {
        return false;
    }

    if (typeof value === 'boolean') {
        return value === true;
    }

    return typeof value !== 'undefined' && value !== null;
}

export function regex(value, expression) {
    if (expression instanceof RegExp) {
        return expression.test(value);
    }

    return new RegExp(expression).test(value);
}

export function email(value) {
    const emailValidation = /^(([^<>()\[\]\\.,;:\s@"]+(\.[^<>()\[\]\\.,;:\s@"]+)*)|(".+"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/;

    return regex(value, emailValidation);
}
