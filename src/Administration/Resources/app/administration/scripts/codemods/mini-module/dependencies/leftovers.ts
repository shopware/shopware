/**
 * @sw-package framework
 * @private
 */

import path from 'node:path';
import ts from 'typescript';
import { isReference } from './global-uses';
import { isSideEffectImport, staysInside } from './rewrites/outside-import';

/** The lookups by a written name that `sw-core-rules/mini-module-dependencies` reports. */
const NAMED_LOOKUPS = new Set([
    'Service',
    'Store.get',
    'Mixin.getByName',
    'Filter.getByName',
]);

const calleeName = (callee: ts.Expression): string | null => {
    if (ts.isIdentifier(callee)) {
        return callee.text;
    }

    if (ts.isPropertyAccessExpression(callee)) {
        const object = calleeName(callee.expression);
        return object ? `${object}.${callee.name.text}` : null;
    }

    return null;
};

/**
 * Lists what a file of `src/` still takes from outside the module after routing, as `line: text`,
 * so the checklist names every place the sweep must look at: the same things the lint reports.
 *
 * @example
 * findLeftovers("const name = Shopware.Snippet.t('x');\n", '/m/src/x.js', '/m');
 * // → ["1: const name = Shopware.Snippet.t('x');"]
 */
export function findLeftovers(code: string, fileName: string, moduleRoot: string): string[] {
    const source = ts.createSourceFile(fileName, code, ts.ScriptTarget.Latest, true);
    const context = { fileName, moduleRoot, dependenciesFile: path.join(moduleRoot, 'dependencies.ts') };
    const lines = new Map<number, string>();
    const mark = (node: ts.Node, note = '') =>
        lines.set(source.getLineAndCharacterOfPosition(node.getStart(source)).line, note);

    const visit = (node: ts.Node) => {
        if (ts.isIdentifier(node) && node.text === 'Shopware' && isReference(node)) {
            mark(node);
        } else if (ts.isQualifiedName(node) && ts.isIdentifier(node.left) && node.left.text === 'Shopware') {
            mark(node);
        } else if (
            ts.isCallExpression(node) &&
            NAMED_LOOKUPS.has(calleeName(node.expression)?.replace(/^Shopware\./, '') ?? '') &&
            node.arguments[0] &&
            ts.isStringLiteralLike(node.arguments[0])
        ) {
            mark(node);
        } else if (
            ts.isStringLiteral(node) &&
            ts.isArrayLiteralExpression(node.parent) &&
            ts.isPropertyAssignment(node.parent.parent) &&
            ts.isIdentifier(node.parent.parent.name) &&
            node.parent.parent.name.text === 'inject'
        ) {
            mark(node);
        } else if (
            ts.isImportDeclaration(node) &&
            ts.isStringLiteral(node.moduleSpecifier) &&
            !staysInside(node.moduleSpecifier.text, context)
        ) {
            mark(
                node,
                isSideEffectImport(node) ? ' (a side effect: it stays in this file, below the imports it needs)' : '',
            );
        }

        node.forEachChild(visit);
    };
    visit(source);

    const text = code.split('\n');
    return [...lines].sort(([a], [b]) => a - b).map(([line, note]) => `${line + 1}: ${text[line].trim()}${note}`);
}
