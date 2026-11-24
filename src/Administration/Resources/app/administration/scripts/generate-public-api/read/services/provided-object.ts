/**
 * @sw-package framework
 *
 * Finds the object a piece of source provides as a service: follows a provider function to what it returns,
 * through `new`, through calls of factory functions and through names, down to a class or an object literal.
 */
import ts from 'typescript';
import type { Declarations, Located } from '../declarations';
import { isFunctionNode, returnedValues } from '../script-nodes';
import type { FunctionNode } from '../script-nodes';

/**
 * @private
 *
 * What a service is, as far as the syntax says:
 *
 * - `instance`: an instance of a class.
 * - `object`: an object literal. `factory` is the function that returns it, when a named one does.
 * - `callable`: a function or a class itself, which has no members a plugin reaches by name.
 * - `nothing`: the provider returns no value.
 * - `unresolved`: an expression the reader cannot follow, such as a member of a runtime object.
 */
export type ProvidedObject =
    | { kind: 'instance'; declaration: Located<ts.ClassLikeDeclaration> }
    | { kind: 'object'; declaration: Located<ts.ObjectLiteralExpression>; factory: Located<FunctionNode> | null }
    | { kind: 'callable'; declaration: Located }
    | { kind: 'nothing' }
    | { kind: 'unresolved'; expression: Located };

/**
 * @private
 *
 * Whether both are the same class, object literal or expression of the sources.
 */
export function isSameObject(a: ProvidedObject, b: ProvidedObject): boolean {
    const nodeOf = (provided: ProvidedObject): ts.Node | undefined => {
        if (provided.kind === 'nothing') {
            return undefined;
        }

        return provided.kind === 'unresolved' ? provided.expression.node : provided.declaration.node;
    };

    return a.kind === b.kind && nodeOf(a) === nodeOf(b);
}

/** A factory that a declaration names, such as `export default function createLoginService()`, carries the comment of the service. */
function namedFactory(fn: Located<FunctionNode>): Located<FunctionNode> | null {
    return ts.isFunctionDeclaration(fn.node) ? fn : null;
}

/**
 * What the function returns. Several `return` statements have to hand back the same object, as a provider does
 * that builds its service on the first call only.
 */
function returnedBy(fn: Located<FunctionNode>, declarations: Declarations): ProvidedObject {
    const [first, ...others] = returnedValues(fn.node)
        .filter((value) => value !== undefined)
        .map((value) => valueOf(value, fn.source, declarations));

    if (first === undefined) {
        return { kind: 'nothing' };
    }

    if (others.some((other) => !isSameObject(first, other))) {
        return { kind: 'unresolved', expression: fn };
    }

    return first.kind === 'object' && first.factory === null ? { ...first, factory: namedFactory(fn) } : first;
}

/**
 * What `new X()` or `x()` gives: an instance of a class, or what a function returns. `new` on a function that
 * returns an object gives that object, while one that returns none fills `this`, which is not read.
 */
function builtBy(call: Located<ts.NewExpression | ts.CallExpression>, declarations: Declarations): ProvidedObject {
    const unresolved: ProvidedObject = { kind: 'unresolved', expression: call };
    const callee = declarations.valueOf(call.node.expression, call.source);
    const isNew = ts.isNewExpression(call.node);

    if (isNew && ts.isClassLike(callee.node)) {
        return { kind: 'instance', declaration: { node: callee.node, source: callee.source } };
    }

    if (!isFunctionNode(callee.node)) {
        return unresolved;
    }

    const returned = returnedBy({ node: callee.node, source: callee.source }, declarations);

    return isNew && returned.kind === 'nothing' ? unresolved : returned;
}

function valueOf(expression: ts.Expression, source: ts.SourceFile, declarations: Declarations): ProvidedObject {
    const value = declarations.valueOf(expression, source);
    const { node } = value;

    if (ts.isObjectLiteralExpression(node)) {
        return { kind: 'object', declaration: { node, source: value.source }, factory: null };
    }

    if (ts.isClassLike(node) || isFunctionNode(node)) {
        return { kind: 'callable', declaration: value };
    }

    return ts.isNewExpression(node) || ts.isCallExpression(node)
        ? builtBy({ node, source: value.source }, declarations)
        : { kind: 'unresolved', expression: value };
}

/**
 * @private
 *
 * What the provider of `addServiceProvider('<name>', <provider>)` hands to the container.
 */
export function providedBy(provider: ts.Expression, source: ts.SourceFile, declarations: Declarations): ProvidedObject {
    const fn = declarations.valueOf(provider, source);

    return isFunctionNode(fn.node)
        ? returnedBy({ node: fn.node, source: fn.source }, declarations)
        : { kind: 'unresolved', expression: fn };
}
