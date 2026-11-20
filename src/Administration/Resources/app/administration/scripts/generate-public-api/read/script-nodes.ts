/**
 * @sw-package framework
 *
 * Reads the nodes of a script's syntax tree: what an expression stands for, and the names and lines of members.
 */
import ts from 'typescript';
import type { SourceLine } from '../model/source-line';

/**
 * @private
 *
 * The expression without what only the type checker sees: parentheses, `as`, `satisfies` and `!`.
 */
export function withoutTypeSyntax(expression: ts.Expression): ts.Expression {
    const isTypeSyntax =
        ts.isParenthesizedExpression(expression) ||
        ts.isAsExpression(expression) ||
        ts.isSatisfiesExpression(expression) ||
        ts.isNonNullExpression(expression);

    return isTypeSyntax ? withoutTypeSyntax(expression.expression) : expression;
}

function topLevelValue(name: string, source: ts.SourceFile): ts.Expression | undefined {
    return source.statements
        .filter(ts.isVariableStatement)
        .flatMap(({ declarationList }) => declarationList.declarations)
        .find((declaration) => ts.isIdentifier(declaration.name) && declaration.name.text === name)?.initializer;
}

/**
 * @private
 *
 * The value the expression has when it names a `const` of the same file, otherwise the expression itself.
 */
export function valueOf(expression: ts.Expression, source: ts.SourceFile, seen: readonly string[] = []): ts.Expression {
    const value = withoutTypeSyntax(expression);

    if (!ts.isIdentifier(value) || seen.includes(value.text)) {
        return value;
    }

    const declared = topLevelValue(value.text, source);

    return declared === undefined ? value : valueOf(declared, source, [...seen, value.text]);
}

/**
 * @private
 *
 * The name of an object member that is written as a literal, `null` for a computed key and for a spread.
 */
export function memberName({ name }: ts.ObjectLiteralElementLike): string | null {
    if (name === undefined) {
        return null;
    }

    if (ts.isIdentifier(name) || ts.isStringLiteralLike(name) || ts.isNumericLiteral(name)) {
        return name.text;
    }

    return ts.isComputedPropertyName(name) && ts.isStringLiteralLike(name.expression) ? name.expression.text : null;
}

/**
 * @private
 *
 * What an object member holds: the value of a property, or the method itself.
 */
export function memberValue(member: ts.ObjectLiteralElementLike, source: ts.SourceFile): ts.Node {
    if (ts.isPropertyAssignment(member)) {
        return valueOf(member.initializer, source);
    }

    return ts.isShorthandPropertyAssignment(member) ? valueOf(member.name, source) : member;
}

/** @private */
export type FunctionNode = ts.FunctionExpression | ts.ArrowFunction | ts.MethodDeclaration;

/** @private */
export function isFunctionNode(node: ts.Node): node is FunctionNode {
    return ts.isFunctionExpression(node) || ts.isArrowFunction(node) || ts.isMethodDeclaration(node);
}

/**
 * @private
 *
 * What each `return` of the function hands back, `undefined` for a bare `return`. A function without a block
 * body returns its expression. The returns of nested functions are not the function's own.
 */
export function returnedValues(fn: FunctionNode): (ts.Expression | undefined)[] {
    const { body } = fn;

    if (body === undefined) {
        return [];
    }

    if (!ts.isBlock(body)) {
        return [body];
    }

    const returns: ts.ReturnStatement[] = [];
    const visit = (node: ts.Node): void => {
        if (ts.isReturnStatement(node)) {
            returns.push(node);
        } else if (!ts.isFunctionLike(node)) {
            ts.forEachChild(node, visit);
        }
    };

    ts.forEachChild(body, visit);

    return returns.map(({ expression }) => expression);
}

/**
 * @private
 *
 * The one object literal the function returns. `null` when it returns something else or in several places.
 */
export function returnedObject(fn: FunctionNode): ts.ObjectLiteralExpression | null {
    const values = returnedValues(fn);
    const value = values.length === 1 && values[0] !== undefined ? withoutTypeSyntax(values[0]) : undefined;

    return value !== undefined && ts.isObjectLiteralExpression(value) ? value : null;
}

/** @private */
export function lineOf(node: ts.Node, source: ts.SourceFile): SourceLine {
    return { file: source.fileName, line: source.getLineAndCharacterOfPosition(node.getStart(source)).line + 1 };
}

/**
 * @private
 *
 * The source text of the node on one line, as the report prints a type or an expression.
 */
export function textOf(node: ts.Node, source: ts.SourceFile): string {
    return node.getText(source).replace(/\s+/g, ' ').trim();
}

/**
 * @private
 *
 * The content of the comments above the node, without their delimiters. The sources write a marker in a JSDoc
 * comment or in a `//` line.
 */
export function docOf(node: ts.Node, source: ts.SourceFile): string {
    return (ts.getLeadingCommentRanges(source.text, node.getFullStart()) ?? [])
        .map(({ pos, end, kind }) =>
            source.text.slice(pos + 2, kind === ts.SyntaxKind.MultiLineCommentTrivia ? end - 2 : end),
        )
        .join('\n');
}
