/**
 * @sw-package framework
 * @private
 */

import ts from 'typescript';
import type { Dependency, GlobalUse, OutsideEdit, Rewrite, RewriteContext, Typing } from '../model';

/** A rewrite that puts `text` in place of a node and needs the given names from `../dependencies`. */
export const replaceNode = (
    node: ts.Node,
    context: RewriteContext,
    text: string,
    dependencies: Dependency[],
    outsideEdits: OutsideEdit[] = [],
): Rewrite => ({
    start: node.getStart(context.source),
    end: node.getEnd(),
    text,
    imports: Object.fromEntries(
        dependencies.map((dependency) => {
            const name = dependency.kind === 'reexport' ? dependency.exported : dependency.name;
            return [name, name];
        }),
    ),
    dependencies,
    outsideEdits,
});

/** The range of a statement from the start of its line up to and including its line break, to cut it out. */
export const statementRange = (source: ts.SourceFile, statement: ts.Node) => {
    const text = source.text;
    const lineStart = text.lastIndexOf('\n', statement.getStart(source) - 1) + 1;
    const lineBreak = text.indexOf('\n', statement.getEnd());

    return { start: lineStart, end: lineBreak === -1 ? text.length : lineBreak + 1 };
};

/**
 * The range that removes a statement with its line, and with the blank line after it when one is before it too,
 * so removing a statement between two blank lines leaves one.
 */
export const removalRange = (source: ts.SourceFile, statement: ts.Node) => {
    const { start, end } = statementRange(source, statement);
    const text = source.text;
    const blankBefore = start === 0 || text.slice(0, start).endsWith('\n\n');

    return { start, end: blankBefore && text[end] === '\n' ? end + 1 : end };
};

/**
 * The parameter list of an accessor: it names the return type when the global types type the name as `$TSFixMe`,
 * so the lint sees that returning `any` is what the global types say.
 *
 * @example
 * accessorHead('any', 'ServiceContainer', 'filterService');
 * // → "(): ServiceContainer['filterService']"
 */
export const accessorHead = (typing: Typing, container: string, name: string) =>
    typing === 'any' ? `(): ${container}['${name}']` : '()';

/** The text of the only argument of a call when it is a plain string, or null. */
export const literalArgument = (call: ts.CallExpression | undefined): string | null => {
    if (!call || call.arguments.length !== 1) {
        return null;
    }

    const [argument] = call.arguments;
    return ts.isStringLiteral(argument) || ts.isNoSubstitutionTemplateLiteral(argument) ? argument.text : null;
};

/** Whether the use is exactly the given path, such as `['Mixin', 'getByName']`. */
export const isPath = (use: GlobalUse, ...path: string[]) =>
    use.path.length === path.length && path.every((segment, index) => use.path[index] === segment);

/**
 * The node of the use that covers only the first `length` segments of its path, such as `Shopware.Context.api`
 * in `Shopware.Context.api.languageId`, or null when a destructured name covers more than that.
 */
export const prefixNode = (use: GlobalUse, length: number): ts.Expression | null => {
    let node: ts.Expression = use.node;

    for (let steps = use.path.length - length; steps > 0; steps -= 1) {
        if (!ts.isPropertyAccessExpression(node)) {
            return null;
        }

        node = node.expression;
    }

    return node;
};
