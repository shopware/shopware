/**
 * @sw-package framework
 */

/**
 * An absolute SFC range: editor adapters underline `[index, endIndex)`, build adapters point at `index`.
 */
type ShopwareSetupErrorPosition = { index: number; endIndex: number };

/**
 * A native setup diagnostic. `index` is an absolute SFC offset; `endIndex` is set when the whole
 * offending node is known, so ESLint can underline it instead of a single point.
 */
class ShopwareSetupTransformError extends Error {
    readonly index: number | null;

    readonly endIndex: number | null;

    constructor(message: string, position: number | ShopwareSetupErrorPosition | null = null) {
        super(message);
        this.name = 'ShopwareSetupTransformError';
        this.index = typeof position === 'number' ? position : (position?.index ?? null);
        this.endIndex = typeof position === 'number' ? null : (position?.endIndex ?? null);
    }
}

/**
 * Moves a script-local Babel node range into SFC coordinates.
 */
function nodeRange(node: { start?: number | null; end?: number | null }, offset: number): ShopwareSetupErrorPosition {
    return { index: offset + (node.start ?? 0), endIndex: offset + (node.end ?? 0) };
}

/**
 * @private
 */
export { ShopwareSetupTransformError, type ShopwareSetupErrorPosition, nodeRange };
