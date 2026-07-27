/**
 * @sw-package framework
 */

/**
 * Error type used by Shopware setup transform diagnostics.
 *
 * `index` is an absolute SFC source offset, letting build integrations and editor tooling point at the
 * same author location. It is `null` when the thrower had no position to give - which is distinct from
 * offset `0`, a real position at the very start of the file. The transform boundary re-anchors a
 * position-less error to the enclosing block; it must not do that to a genuine offset `0`.
 */
class ShopwareSetupTransformError extends Error {
    readonly index: number | null;

    /**
     * Carries a source offset so build, lint, and editor adapters can report the same error.
     *
     * Omit `index` only when no position is known; pass a number - including `0` - whenever one is.
     */
    constructor(message: string, index: number | null = null) {
        super(message);
        this.name = 'ShopwareSetupTransformError';
        this.index = index;
    }
}

/**
 * @private
 */
export { ShopwareSetupTransformError };
