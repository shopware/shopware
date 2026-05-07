/**
 * @sw-package framework
 */

class ShopwareSetupTransformError extends Error {
    /**
     * Carries a source offset so build, lint, and editor adapters can report the same error.
     *
     * @param {string} message
     * @param {number} [index]
     */
    constructor(message, index = 0) {
        super(message);
        this.name = 'ShopwareSetupTransformError';
        this.index = index;
    }
}

module.exports = {
    ShopwareSetupTransformError,
};
