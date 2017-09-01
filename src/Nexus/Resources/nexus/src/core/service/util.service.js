export default {
    merge,
    formDataToObject,
    warn
};

// Todo: This has an issue when you want to copy into a new object
function merge(target, source) {
    Object.keys(source).forEach((key) => {
        if (source[key] instanceof Object) {
            if (!target[key]) {
                Object.assign(target, { [key]: {} });
            }
            Object.assign(source[key], merge(target[key], source[key]));
        }
    });

    Object.assign(target || {}, source);
    return target;
}


function formDataToObject(formData) {
    return Array.from(formData).reduce((result, item) => {
        result[item[0]] = item[1];
        return result;
    }, {});
}

/**
 *
 * @param {String} name
 * @param {String|Object|Array} message
 */
function warn(name = 'Core', ...message) {
    if (process.env.NODE_ENV !== 'production' && typeof console !== 'undefined') {
        message.unshift(`[${name}]`);
        console.warn.apply(this, message);
    }
}
