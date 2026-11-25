/**
 * @sw-package framework
 *
 * Reads the nodes of a script's syntax tree: what an expression stands for, and the names and lines of members.
 */
import ts from 'typescript';
import { isEslintDirective } from '../../../eslint-rules/deprecation-rules/utils/eslint-directive';
import type { SourceLine } from '../model/source-line';

/**
 * @private
 *
 * The expression without what only the type checker sees: parentheses, `as`, `satisfies` and `!`.
 */
export function withoutTypeSyntax(expression: ts.Expression): ts.Expression {
    return isTypeSyntax(expression) ? withoutTypeSyntax(expression.expression) : expression;
}

type TypeSyntax = ts.ParenthesizedExpression | ts.AsExpression | ts.SatisfiesExpression | ts.NonNullExpression;

function isTypeSyntax(node: ts.Node): node is TypeSyntax {
    return (
        ts.isParenthesizedExpression(node) ||
        ts.isAsExpression(node) ||
        ts.isSatisfiesExpression(node) ||
        ts.isNonNullExpression(node)
    );
}

// What these calls return has the members of what they get.
const PASS_THROUGH_CALLS = [
    'Object.freeze',
    'markRaw',
    'Vue.markRaw',
    'reactive',
    'shallowReactive',
    'readonly',
    'toRefs',
];

/**
 * @private
 *
 * The value that a call hands back as it got it, as `Object.freeze(value)` does.
 */
export function passedThrough(node: ts.Node): ts.Expression | undefined {
    const passes =
        ts.isCallExpression(node) && node.arguments.length === 1 && PASS_THROUGH_CALLS.includes(node.expression.getText());

    return passes ? node.arguments[0] : undefined;
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
 * The name of a member of an object or of a type that is written as a literal, `null` for a computed key and
 * for a spread.
 */
export function memberName({ name }: { name?: ts.PropertyName }): string | null {
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

/**
 * @private
 *
 * Every node below `node`, and `node` itself, that the guard accepts.
 */
export function collect<Found extends ts.Node>(node: ts.Node, guard: (candidate: ts.Node) => candidate is Found): Found[] {
    const found: Found[] = guard(node) ? [node] : [];

    ts.forEachChild(node, (child) => {
        found.push(...collect(child, guard));
    });

    return found;
}

/**
 * @private
 *
 * A call of a method, `receiver.method(...)`.
 */
export type MethodCall = ts.CallExpression & { expression: ts.PropertyAccessExpression };

/** @private */
export function isMethodCall(node: ts.Node): node is MethodCall {
    return ts.isCallExpression(node) && ts.isPropertyAccessExpression(node.expression);
}

/**
 * @private
 *
 * The node as a call of one of the named functions, `ref(...)` for `['ref']`.
 */
export function callOf(node: ts.Node, names: readonly string[]): ts.CallExpression | undefined {
    return ts.isCallExpression(node) && ts.isIdentifier(node.expression) && names.includes(node.expression.text)
        ? node
        : undefined;
}

/** @private */
export type FunctionNode = ts.FunctionExpression | ts.ArrowFunction | ts.MethodDeclaration | ts.FunctionDeclaration;

/** @private */
export function isFunctionNode(node: ts.Node): node is FunctionNode {
    return (
        ts.isFunctionExpression(node) ||
        ts.isArrowFunction(node) ||
        ts.isMethodDeclaration(node) ||
        ts.isFunctionDeclaration(node)
    );
}

/**
 * @private
 *
 * What each `return` of the function hands back, `undefined` for a bare `return`. A function without a block
 * body returns its expression. The returns of nested functions are not the function's own.
 */
export function returnedValues(fn: FunctionNode | ts.GetAccessorDeclaration): (ts.Expression | undefined)[] {
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

function commentBody({ pos, end, kind }: ts.CommentRange, source: ts.SourceFile): string {
    return source.text.slice(pos + 2, kind === ts.SyntaxKind.MultiLineCommentTrivia ? end - 2 : end);
}

function commentsAbove(node: ts.Node, source: ts.SourceFile): string[] {
    return (ts.getLeadingCommentRanges(source.text, node.getFullStart()) ?? []).map((range) => commentBody(range, source));
}

/**
 * @private
 *
 * The content of the comments above the node, without their delimiters. The sources write a marker in a JSDoc
 * comment or in a `//` line.
 */
export function docOf(node: ts.Node, source: ts.SourceFile): string {
    return commentsAbove(node, source).join('\n');
}

/**
 * The comment that speaks for a declaration or a registration as a whole: the one directly above its statement,
 * as in the ESLint rule `private-feature-declarations`. A file header says nothing about a single declaration.
 * ESLint directives between that comment and the statement are skipped, as the rule skips them.
 */
function declarationDoc(statement: ts.Node, source: ts.SourceFile): string {
    return (
        commentsAbove(statement, source)
            .filter((comment) => !isEslintDirective(comment))
            .at(-1) ?? ''
    );
}

const PRIVATE_TAG = /^[\s*]*@private\b/m;

/**
 * @private
 *
 * Whether the content of a comment has an `@private` tag.
 */
export function isPrivateDoc(doc: string): boolean {
    return PRIVATE_TAG.test(doc);
}

/** @private */
export function hasPrivateTag(statement: ts.Node, source: ts.SourceFile): boolean {
    return isPrivateDoc(declarationDoc(statement, source));
}

/** The name a statement declares: that of a function or a class, or of the one variable of a `const`. */
function declaredName(statement: ts.Node): string | undefined {
    const [variable] = ts.isVariableStatement(statement) ? statement.declarationList.declarations : [];
    const name = ts.isFunctionDeclaration(statement) || ts.isClassDeclaration(statement) ? statement.name : variable?.name;

    return name !== undefined && ts.isIdentifier(name) ? name.text : undefined;
}

/** `export default example;` for what a statement declares as `example`, where the sources often write its comment. */
function defaultExportOf(statement: ts.Node, source: ts.SourceFile): ts.Statement | undefined {
    const name = declaredName(statement);

    return name === undefined
        ? undefined
        : source.statements.filter(ts.isExportAssignment).find(({ expression }) => {
              const exported = withoutTypeSyntax(expression);

              return ts.isIdentifier(exported) && exported.text === name;
          });
}

type Initialized = ts.VariableDeclaration | ts.PropertyAssignment | ts.PropertyDeclaration;

function isInitialized(node: ts.Node): node is Initialized {
    return ts.isVariableDeclaration(node) || ts.isPropertyAssignment(node) || ts.isPropertyDeclaration(node);
}

/** A variable, a member or a default export holds the node as its value, also behind type syntax or a call that passes it through. */
function holdsAsValue(holder: ts.Node, node: ts.Node): boolean {
    if (isTypeSyntax(holder) || passedThrough(holder) === node) {
        return true;
    }

    return ts.isExportAssignment(holder) ? holder.expression === node : isInitialized(holder) && holder.initializer === node;
}

/**
 * The declaration whose comment speaks for the node: the node itself when it is a statement or a member of an
 * object or a class, and otherwise what holds the node as its value. A value inside any other expression has
 * none, whatever stands above the statement around it: a list of exports says nothing about one of its names.
 * Neither has what a getter hands out, because the comment of a getter is about reading the getter.
 */
function declarationOf(node: ts.Node): ts.Node | undefined {
    if (ts.isGetAccessorDeclaration(node)) {
        return undefined;
    }

    if (ts.isVariableDeclaration(node)) {
        return statementOf(node);
    }

    if (ts.isStatement(node) || ts.isObjectLiteralElement(node) || ts.isClassElement(node)) {
        return node;
    }

    return holdsAsValue(node.parent, node) ? declarationOf(node.parent) : undefined;
}

/**
 * @private
 *
 * The comments that speak for what the node declares or is the value of: the one directly above its
 * declaration, and for a statement also the one above the default export of what it declares.
 */
export function declarationDocs(node: ts.Node, source: ts.SourceFile): string[] {
    const declaration = declarationOf(node);

    if (declaration === undefined) {
        return [];
    }

    const speaking = ts.isStatement(declaration) ? [declaration, defaultExportOf(declaration, source)] : [declaration];

    return speaking.filter((declared) => declared !== undefined).map((declared) => declarationDoc(declared, source));
}

/**
 * @private
 *
 * The statement the node is part of.
 */
export function statementOf(node: ts.Node): ts.Node {
    return ts.isStatement(node) || node.parent === undefined ? node : statementOf(node.parent);
}

const HIDDEN_MODIFIERS = [
    ts.SyntaxKind.PrivateKeyword,
    ts.SyntaxKind.ProtectedKeyword,
];

/**
 * @private
 *
 * The modifiers of a node, such as `static` and `private`.
 */
export function modifiersOf(node: ts.Node): readonly ts.Modifier[] {
    return ts.canHaveModifiers(node) ? (ts.getModifiers(node) ?? []) : [];
}

function hasModifier(node: ts.Node, kinds: readonly ts.SyntaxKind[]): boolean {
    return modifiersOf(node).some(({ kind }) => kinds.includes(kind));
}

/**
 * @private
 *
 * A class element or a constructor parameter under its name. `isHidden` is one that code outside the class
 * cannot reach.
 */
export interface NamedClassElement {
    element: ts.ClassElement | ts.ParameterDeclaration;
    name: string;
    isHidden: boolean;
    isStatic: boolean;
}

/**
 * @private
 *
 * The elements and constructor parameters of the class that have a literal name. A `#private` or computed name
 * gives none.
 */
export function namedClassElements(declaration: ts.ClassLikeDeclaration): NamedClassElement[] {
    const parameters = declaration.members.find(ts.isConstructorDeclaration)?.parameters ?? [];

    return [...declaration.members, ...parameters].flatMap((element): NamedClassElement[] => {
        const { name } = element;
        const isNamed = name !== undefined && (ts.isIdentifier(name) || ts.isStringLiteralLike(name));

        return isNamed
            ? [
                  {
                      element,
                      name: name.text,
                      isHidden: hasModifier(element, HIDDEN_MODIFIERS),
                      isStatic: hasModifier(element, [ts.SyntaxKind.StaticKeyword]),
                  },
              ]
            : [];
    });
}
