/**
 * @sw-package framework
 * @private
 */

const SUPPRESSION = /^\/\/ eslint-disable-next-line sw-deprecation-rules\/private-feature-declarations\n(?=export default)/m;
const DOC_COMMENT = /^(\/\*\*(?:(?!\*\/)[\s\S])*?)\n \*\/\n(?=export default)/m;

/**
 * Marks the component's `export default` `@public`, because index.ts exposes it through `load`.
 * A `@private` tag it had becomes `@public`, and an export without a doc comment gets one.
 *
 * @example
 * markPublic('/**\n * @private\n *\/\nexport default {};');
 * // → '/**\n * @public\n *\/\nexport default {};'
 */
export function markPublic(source: string): string {
    const unsuppressed = source.replace(SUPPRESSION, '');
    const docComment = DOC_COMMENT.exec(unsuppressed);

    if (!docComment) {
        return unsuppressed.replace(/^export default/m, '/**\n * @public\n */\nexport default');
    }

    const marked = /^ \* @private$/m.test(docComment[1])
        ? docComment[0].replace(/^ \* @private$/m, ' * @public')
        : `${docComment[1]}\n *\n * @public\n */\n`;

    return unsuppressed.replace(docComment[0], marked);
}
