/**
 * @sw-package framework
 */

/**
 * An absolute SFC source range for a diagnostic: `index` is the start offset, `endIndex` the exclusive
 * end. Editor adapters underline `[index, endIndex)`; build/CLI adapters point at `index`.
 */
type ShopwareSetupErrorPosition = { index: number; endIndex: number };

/**
 * Error type used by Shopware setup transform diagnostics.
 *
 * `index` is an absolute SFC source offset, letting build integrations and editor tooling point at the
 * same author location. It is `null` when the thrower had no position to give - which is distinct from
 * offset `0`, a real position at the very start of the file. The transform boundary re-anchors a
 * position-less error to the enclosing block; it must not do that to a genuine offset `0`.
 *
 * `endIndex` is the exclusive end offset when the thrower had a full node range (via `absoluteRange`),
 * so ESLint can underline the whole offending token instead of a single point; it is `null` for a
 * point-only position (a bare offset, e.g. a parser error) or when no position was given.
 */
class ShopwareSetupTransformError extends Error {
    readonly index: number | null;

    readonly endIndex: number | null;

    /**
     * Carries a source position so build, lint, and editor adapters can report the same error.
     *
     * Pass a `{ index, endIndex }` range (from `absoluteRange`) for a full token span, a bare number
     * for a point, or omit it when no position is known.
     */
    constructor(message: string, position: number | ShopwareSetupErrorPosition | null = null) {
        super(message);
        this.name = 'ShopwareSetupTransformError';

        if (position === null) {
            this.index = null;
            this.endIndex = null;
        } else if (typeof position === 'number') {
            this.index = position;
            this.endIndex = null;
        } else {
            this.index = position.index;
            this.endIndex = position.endIndex;
        }
    }
}

/**
 * @private
 */
export { ShopwareSetupTransformError, type ShopwareSetupErrorPosition };
