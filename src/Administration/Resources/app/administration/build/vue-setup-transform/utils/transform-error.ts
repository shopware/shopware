/**
 * @sw-package framework
 */

class ShopwareSetupTransformError extends Error {
    readonly index: number;

    /**
     * Carries a source offset so build, lint, and editor adapters can report the same error.
     */
    constructor(message: string, index = 0) {
        super(message);
        this.name = 'ShopwareSetupTransformError';
        this.index = index;
    }
}

export { ShopwareSetupTransformError };
