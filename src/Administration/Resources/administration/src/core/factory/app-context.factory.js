/**
 * @module core/factory/context
 * @param {Object} context
 * @type factory
 */
export default function createContext(context = {}) {
    Object.assign(context, {
        environment: process.env.NODE_ENV,
        fallbackLocale: 'en-GB'
    });

    return context;
}
