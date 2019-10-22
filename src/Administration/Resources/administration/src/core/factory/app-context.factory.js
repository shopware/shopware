/**
 * @module core/factory/context
 * @type factory
 */
export default function createContext(context = {}) {
    Object.assign(context, {
        environment: process.env.NODE_ENV
    });

    return context;
}
