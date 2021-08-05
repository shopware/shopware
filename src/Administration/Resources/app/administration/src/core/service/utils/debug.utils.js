export default {
    warn,
    error,
};

/**
 * General logging function which provides a unified style of log messages for developers. Please keep in mind the log
 * messages will be displayed in the developer console when they're running the application in development mode.
 *
 * @param {String} [name='Core']
 * @param {...String|Array|Date|Number|Object} message
 */
export function warn(name = 'Core', ...message) {
    if (process.env.NODE_ENV !== 'production' && typeof console !== 'undefined') {
        message.unshift(`[${name}]`);
        console.warn.apply(this, message);
    }
}

/**
 *
 * @param {String} [name='Core']
 * @param {...String|Array|Date|Number|Object} message
 * @returns {String} Returns a string containing error name and message
 */
export function error(name = 'Core', ...message) {
    if (process.env.NODE_ENV !== 'production' && typeof console !== 'undefined') {
        message.unshift(`[${name}]`);
        console.error.apply(this, message);
    }
}
