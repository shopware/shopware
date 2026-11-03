/**
 * @sw-package framework
 * @private
 */

import ts from 'typescript';

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

/** Whether a doc comment directly above the component's `export default` holds the marker, also with text after it. */
const isDocumented = (source: string, marker: '@public' | '@private') => {
    const file = ts.createSourceFile('component.ts', source, ts.ScriptTarget.Latest, true, ts.ScriptKind.TS);
    const exported = file.statements.find(ts.isExportAssignment);
    if (!exported) {
        return false;
    }

    return (ts.getLeadingCommentRanges(source, exported.getFullStart()) ?? []).some(
        ({ pos, end, kind }) =>
            kind === ts.SyntaxKind.MultiLineCommentTrivia &&
            source.startsWith('/**', pos) &&
            new RegExp(`^\\s*\\*\\s*${marker}\\b`, 'm').test(source.slice(pos, end)),
    );
};

/**
 * Whether a doc comment directly above the component's `export default` marks it `@private`,
 * also with a reason after the tag, so the component becomes a private module whatever its registration says.
 *
 * @example
 * isDocumentedPrivate('/**\n * @private might get removed\n *\/\nexport default {};');
 * // → true
 */
export function isDocumentedPrivate(source: string): boolean {
    return isDocumented(source, '@private');
}

/**
 * Whether a doc comment directly above the component's `export default` marks it `@public`, which keeps
 * a component public although it extends a private one.
 *
 * @example
 * isDocumentedPublic('/**\n * @public\n *\/\nexport default {};');
 * // → true
 */
export function isDocumentedPublic(source: string): boolean {
    return isDocumented(source, '@public');
}
