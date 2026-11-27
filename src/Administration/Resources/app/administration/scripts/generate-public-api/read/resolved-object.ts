/**
 * @sw-package framework
 *
 * Says what a value of the sources is as an object: follows `new`, calls of factory functions, getters and
 * names down to a class, an object literal or a function.
 */
import ts from 'typescript';
import type { Declarations, Located } from './declarations';
import { isFunctionNode, returnedValues } from './script-nodes';
import type { FunctionNode } from './script-nodes';

/**
 * @private
 *
 * What a value is, as far as the syntax says:
 *
 * - `instance`: an instance of a class.
 * - `object`: an object literal. `factory` is the function that returns it, when a named one does.
 * - `class`: a class itself, which code reaches through its static members.
 * - `function`: a function, which has no members that code reaches by name.
 * - `nothing`: a function returns no value.
 * - `unresolved`: an expression the reader cannot follow, such as a value from a package.
 */
export type ResolvedObject =
    | { kind: 'instance'; declaration: Located<ts.ClassLikeDeclaration> }
    | { kind: 'object'; declaration: Located<ts.ObjectLiteralExpression>; factory: Located<FunctionNode> | null }
    | { kind: 'class'; declaration: Located<ts.ClassLikeDeclaration> }
    | { kind: 'function'; declaration: Located<FunctionNode> }
    | { kind: 'nothing' }
    | { kind: 'unresolved'; expression: Located };

/**
 * @private
 *
 * Whether both are the same class, object literal, function or expression of the sources.
 */
export function isSameObject(a: ResolvedObject, b: ResolvedObject): boolean {
    const nodeOf = (resolved: ResolvedObject): ts.Node | undefined => {
        if (resolved.kind === 'nothing') {
            return undefined;
        }

        return resolved.kind === 'unresolved' ? resolved.expression.node : resolved.declaration.node;
    };

    return a.kind === b.kind && nodeOf(a) === nodeOf(b);
}

/** What a function or a getter hands back when it runs. */
type Returning = FunctionNode | ts.GetAccessorDeclaration;

/** The class that the function declares to return an instance of, as `static get instance(): Store` does. */
function declaredInstance({ node, source }: Located<Returning>, declarations: Declarations): ResolvedObject | undefined {
    const name = node.type !== undefined && ts.isTypeReferenceNode(node.type) ? node.type.typeName : undefined;
    const declared = name !== undefined && ts.isIdentifier(name) ? declarations.valueOf(name, source) : undefined;

    return declared !== undefined && ts.isClassLike(declared.node)
        ? { kind: 'instance', declaration: { node: declared.node, source: declared.source } }
        : undefined;
}

/**
 * What every `return` of the function hands back. Several `return` statements have to hand back the same
 * object, as a provider does that builds its service on the first call only.
 */
function returnedValue(fn: Located<Returning>, declarations: Declarations): ResolvedObject {
    const [first, ...others] = returnedValues(fn.node)
        .filter((value) => value !== undefined)
        .map((value) => objectAt(declarations.valueOf(value, fn.source), declarations));

    if (first === undefined) {
        return { kind: 'nothing' };
    }

    if (others.some((other) => !isSameObject(first, other))) {
        return { kind: 'unresolved', expression: fn };
    }

    const { node } = fn;

    return first.kind === 'object' && first.factory === null && ts.isFunctionDeclaration(node)
        ? { ...first, factory: { node, source: fn.source } }
        : first;
}

/**
 * @private
 *
 * What the function returns. Where its `return` statements do not say, its declared return type may.
 */
export function returnedBy(fn: Located<Returning>, declarations: Declarations): ResolvedObject {
    const returned = returnedValue(fn, declarations);
    const isKnown = returned.kind !== 'nothing' && returned.kind !== 'unresolved';

    return isKnown ? returned : (declaredInstance(fn, declarations) ?? returned);
}

/**
 * What `new X()` or `x()` gives: an instance of a class, or what a function returns. `new` on a function that
 * returns an object gives that object, while one that returns none fills `this`, which is not read.
 */
function builtBy(call: Located<ts.NewExpression | ts.CallExpression>, declarations: Declarations): ResolvedObject {
    const unresolved: ResolvedObject = { kind: 'unresolved', expression: call };
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

/**
 * @private
 *
 * What the value at a place of the sources is as an object. A getter stands for what it returns.
 */
export function objectAt(value: Located, declarations: Declarations): ResolvedObject {
    const { node, source } = value;

    if (ts.isObjectLiteralExpression(node)) {
        return { kind: 'object', declaration: { node, source }, factory: null };
    }

    if (ts.isGetAccessorDeclaration(node)) {
        return returnedBy({ node, source }, declarations);
    }

    if (ts.isClassLike(node)) {
        return { kind: 'class', declaration: { node, source } };
    }

    if (isFunctionNode(node)) {
        return { kind: 'function', declaration: { node, source } };
    }

    return ts.isNewExpression(node) || ts.isCallExpression(node)
        ? builtBy({ node, source }, declarations)
        : { kind: 'unresolved', expression: value };
}

/**
 * @private
 *
 * What a member of the object is: a member of an instance of its class, a key of its literal, or a static
 * member of a class. `undefined` for a member that the object does not write itself.
 */
export function memberOfObject(
    object: ResolvedObject,
    name: string,
    declarations: Declarations,
): ResolvedObject | undefined {
    if (object.kind === 'nothing' || object.kind === 'unresolved' || object.kind === 'function') {
        return undefined;
    }

    const member =
        object.kind === 'instance'
            ? declarations.instanceMemberOf(object.declaration, name)
            : declarations.memberOf(object.declaration, name);

    return member && objectAt(member, declarations);
}
