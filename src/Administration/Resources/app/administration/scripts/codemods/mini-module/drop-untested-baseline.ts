/**
 * @sw-package framework
 * @private
 */

import ts from 'typescript';

/**
 * Removes moved files, also those inside a moved directory, from `missingTests` in src/meta/baseline.ts: the meta test exempts everything in a
 * mini-module, and fails on a baseline entry whose file is gone.
 *
 * @example
 * dropUntestedFiles("const missingTests = [\n    'a/index.js',\n    'b/index.js',\n];\n", ['a/index.js']);
 * // → "const missingTests = [\n    'b/index.js',\n];\n"
 */
export function dropUntestedFiles(code: string, files: string[]): string {
    const source = ts.createSourceFile('baseline.ts', code, ts.ScriptTarget.Latest, true);
    const ranges: { start: number; end: number }[] = [];

    const visit = (node: ts.Node) => {
        if (
            ts.isVariableDeclaration(node) &&
            ts.isIdentifier(node.name) &&
            node.name.text === 'missingTests' &&
            node.initializer &&
            ts.isArrayLiteralExpression(node.initializer)
        ) {
            node.initializer.elements.forEach((element) => {
                const isMoved = (entry: string) => files.some((file) => entry === file || entry.startsWith(`${file}/`));
                if (ts.isStringLiteral(element) && isMoved(element.text)) {
                    const start = code.lastIndexOf('\n', element.getStart(source)) + 1;
                    const end = code.indexOf('\n', element.getEnd()) + 1;
                    ranges.push({ start, end });
                }
            });
        }

        node.forEachChild(visit);
    };
    visit(source);

    return ranges.reduceRight((text, { start, end }) => `${text.slice(0, start)}${text.slice(end)}`, code);
}
