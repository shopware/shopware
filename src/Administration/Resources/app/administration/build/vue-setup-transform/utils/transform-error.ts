/**
 * @sw-package framework
 */

/**
 * Error type used by Shopware setup transform diagnostics.
 *
 * `index` is an absolute SFC source offset after boundary code has adjusted script-local analyzer
 * errors, letting build integrations and editor tooling point at the same author location.
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
