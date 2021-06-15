import domPurify from 'dompurify';

/**
 * @description Contains all valid middleware names
 * @type {Array<String>}
 */
const middlewareNames = [
    'beforeSanitizeElements',
    'uponSanitizeElement',
    'afterSanitizeElements',
    'beforeSanitizeAttributes',
    'uponSanitizeAttribute',
    'afterSanitizeAttributes',
    'beforeSanitizeShadowDOM',
    'uponSanitizeShadowNode',
    'afterSanitizeShadowDOM',
];

export default class Sanitizer {
    /**
     * Sets the domPurify config globally until {@link Sanitizer#clearConfig} will get called.
     * See <https://github.com/cure53/DOMPurify/tree/master/demos#what-is-this> for all configuration options.
     *
     * @static
     * @param {Object} config
     * @return {void}
     */
    static setConfig(config) {
        return domPurify.setConfig(config);
    }

    /**
     * Clears all globally set configuration values.
     *
     * @static
     * @return {void}
     */
    static clearConfig() {
        return domPurify.clearConfig();
    }

    /**
     * Adds a middleware to the sanitizer to allow modifying content.
     *
     * @static
     * @param {String} middlewareName
     * @param {Function} [middlewareFn=() => {}]
     * @return {boolean}
     */
    static addMiddleware(middlewareName, middlewareFn = () => {}) {
        if (!middlewareNames.includes(middlewareName)) {
            Shopware.Utils.debug.warn(
                'Sanitizer',
                `No middleware found for name "${middlewareName}", 
                the following are available: ${middlewareNames.join(', ')}`,
            );
            return false;
        }

        domPurify.addHook(middlewareName, middlewareFn);
        return true;
    }

    /**
     * Removes a registered middleware using the provided name.
     *
     * @static
     * @param {String}middlewareName
     * @return {boolean}
     */
    static removeMiddleware(middlewareName) {
        if (!middlewareNames.includes(middlewareName)) {
            Shopware.Utils.debug.warn(
                'Sanitizer',
                `No middleware found for name "${middlewareName}", 
                the following are available: ${middlewareNames.join(', ')}`,
            );
            return false;
        }

        domPurify.removeHooks(middlewareName);
        return true;
    }

    /**
     * Sanitizes a malformed HTML string and suspicious strings
     *
     * @param {String} dirtyHtml
     * @param {Object} [config={}]
     * @return {String}
     */
    static sanitize(dirtyHtml, config = {}) {
        return domPurify.sanitize(dirtyHtml, config);
    }
}
