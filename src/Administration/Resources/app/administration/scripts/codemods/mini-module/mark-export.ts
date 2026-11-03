/**
 * @sw-package framework
 * @private
 */

const SUPPRESSION = /^\/\/ eslint-disable-next-line sw-deprecation-rules\/private-feature-declarations\n(?=export default)/m;
const DOC_COMMENT = /^(\/\*\*(?:(?!\*\/)[\s\S])*?)\n \*\/\n(?=export default)/m;

/**
 * Marks the component's `export default` with `@public` when index.ts exposes it through `load`,
 * or with `@private` in a nested private module, which nothing exposes publicly.
 * The opposite marker it had is replaced, and an export without a doc comment gets one.
 *
 * @example
 * markExport('/**\n * @private\n *\/\nexport default {};', '@public');
 * // → '/**\n * @public\n *\/\nexport default {};'
 */
export function markExport(source: string, marker: '@public' | '@private'): string {
    const opposite = new RegExp(`^ \\* ${marker === '@public' ? '@private' : '@public'}$`, 'm');
    const unsuppressed = source.replace(SUPPRESSION, '');
    const docComment = DOC_COMMENT.exec(unsuppressed);

    if (!docComment) {
        return unsuppressed.replace(/^export default/m, `/**\n * ${marker}\n */\nexport default`);
    }

    if (new RegExp(`^ \\* ${marker}$`, 'm').test(docComment[1])) {
        return unsuppressed;
    }

    const marked = opposite.test(docComment[1])
        ? docComment[0].replace(opposite, ` * ${marker}`)
        : `${docComment[1]}\n *\n * ${marker}\n */\n`;

    return unsuppressed.replace(docComment[0], marked);
}
