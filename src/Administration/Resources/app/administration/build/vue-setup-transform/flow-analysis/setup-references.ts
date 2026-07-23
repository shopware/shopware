/**
 * @sw-package framework
 */

/**
 * Identifier-flow queries over a setup-script Babel subtree.
 *
 * Answers "does this hoisted node reference a binding that stays inside the setup callback" - both in
 * value positions (with function-scope shadowing, so a local that shadows a top-level name is fine) and
 * in type positions (`typeof localConst`, or an `enum` used as a type). Built on the generic traversal
 * primitives in utils/; consumed by the hoist-safety guard.
 *
 * Vocabulary used below:
 * - **`localBindings`**: the names that live *inside* the generated setup callback (top-level setup
 *   bindings). A hoisted node that reads one of these is unsafe, because after hoisting the read runs
 *   at the module root where the binding does not exist.
 * - **`shadowedBindings`**: names re-declared locally within the subtree walked so far (a callback
 *   parameter, or a `const`/`function`/`catch` inside an argument). They shadow a same-named
 *   `localBinding`, so a read of them is self-contained and safe.
 * - **runtime identifier reference**: an identifier that reads a value at runtime, as opposed to a
 *   declaration id, a member-access property name, or a static object key.
 * - **entity name**: a (possibly qualified) type name - `A`, or `A.B.C` (a Babel `TSQualifiedName`);
 *   its *leftmost* identifier (`A`) is the one that resolves to a binding.
 */

import type { Identifier, Node as BabelNode } from '@babel/types';
import { forEachPatternIdentifier } from '../utils/babel-patterns';
import { childBabelNodes, findInTree, isTypeKey } from '../utils/ast-traversal';

/**
 * Whether an identifier is a **runtime identifier reference** - a value read - rather than a
 * declaration id, a member-access property name, or a static object key. Decided from its parent node.
 */
function isRuntimeIdentifierReference(node: Identifier, parent: BabelNode | null): boolean {
    if (!parent) {
        return true;
    }

    // e.g. `source.count` reads `source` but not `count`; `source[count]` reads both.
    if (parent.type === 'MemberExpression' || parent.type === 'OptionalMemberExpression') {
        return parent.property !== node || Boolean(parent.computed);
    }

    // e.g. `{ count: value }` reads `value` but not the static key `count`; `{ [count]: value }` reads both.
    if (parent.type === 'ObjectProperty') {
        return parent.value === node || Boolean(parent.computed);
    }

    // e.g. `{ count() {} }` does not read the method name `count`.
    if (parent.type === 'ObjectMethod') {
        return parent.key !== node || Boolean(parent.computed);
    }

    // e.g. `const count = 1` or `function count() {}` declare `count` rather than reading it.
    if (
        parent.type === 'VariableDeclarator' ||
        parent.type === 'FunctionDeclaration' ||
        parent.type === 'FunctionExpression' ||
        parent.type === 'ClassDeclaration' ||
        parent.type === 'ClassExpression'
    ) {
        return parent.id !== node;
    }

    return true;
}

/**
 * Returns the leftmost identifier of a type **entity name** (`A` in `A`, `A.B`, or `A.B.C`).
 *
 * That leftmost identifier is the one that resolves to a value/type binding; the rest are property
 * accesses on it. Returns null for entity names that are not a plain identifier chain.
 */
function getLeftmostEntityIdentifier(entity: BabelNode | null | undefined): Identifier | null {
    if (!entity) {
        return null;
    }

    if (entity.type === 'Identifier') {
        return entity;
    }

    if (entity.type === 'TSQualifiedName') {
        return getLeftmostEntityIdentifier(entity.left);
    }

    return null;
}

/**
 * Finds the first type position that references a binding staying inside the setup callback.
 *
 * Only genuine references into the value/type space are reported - a `typeof localConst` query or a
 * `TSTypeReference` to a runtime binding such as an `enum`. Type member keys, tuple labels and type
 * parameter names are declarations, not references, so they are ignored (a prop typed `{ save: [] }`
 * next to a `function save()` binding is fine).
 */
function findLocalSetupTypeReference(node: BabelNode | null | undefined, localBindings: Set<string>): Identifier | null {
    return findInTree(node, (candidate) => {
        // e.g. `{ kind: Kind }` where `Kind` is an enum kept inside the callback.
        if (candidate.type === 'TSTypeReference') {
            const identifier = getLeftmostEntityIdentifier(candidate.typeName);

            return identifier && localBindings.has(identifier.name) ? identifier : null;
        }

        // e.g. `typeof localConst` reads a runtime value from type space.
        if (candidate.type === 'TSTypeQuery') {
            const identifier = getLeftmostEntityIdentifier(candidate.exprName);

            return identifier && localBindings.has(identifier.name) ? identifier : null;
        }

        return null;
    });
}

/**
 * The node kinds that open a new function scope for the shadowing check.
 */
function isFunctionLikeNode(node: BabelNode): boolean {
    return (
        node.type === 'FunctionDeclaration' ||
        node.type === 'FunctionExpression' ||
        node.type === 'ArrowFunctionExpression' ||
        node.type === 'ObjectMethod' ||
        node.type === 'ClassMethod' ||
        node.type === 'ClassPrivateMethod'
    );
}

/**
 * Collects the names a function body declares locally, into `into`.
 *
 * Descends through the body but stops at nested function boundaries - a nested function's own locals
 * belong to its own scope and are collected when the walk reaches it. This is function-scope
 * granularity (not per-block lexical scoping), which is enough to stop false hoist-safety rejections
 * for realistic macro arguments.
 */
function collectFunctionScopeDeclarations(node: BabelNode | null | undefined, into: Set<string>, isRoot = true): void {
    if (!node) {
        return;
    }

    // Nested functions open their own scope; do not pull their locals into this one.
    if (!isRoot && isFunctionLikeNode(node)) {
        return;
    }

    if (node.type === 'VariableDeclarator') {
        forEachPatternIdentifier(node.id, (identifier) => into.add(identifier.name));
    }

    if ((node.type === 'FunctionDeclaration' || node.type === 'ClassDeclaration') && node.id) {
        into.add(node.id.name);
    }

    if (node.type === 'CatchClause' && node.param) {
        forEachPatternIdentifier(node.param, (identifier) => into.add(identifier.name));
    }

    childBabelNodes(node, isTypeKey).forEach((child) => collectFunctionScopeDeclarations(child, into, false));
}

/**
 * Finds the first `localBindings` name read inside a subtree (e.g. a hoisted macro argument), or null.
 *
 * @param localBindings names that stay inside the setup callback; a read of one is what makes a
 *   hoisted node unsafe.
 * @param shadowedBindings names re-declared locally on the way down (function params, and body-level
 *   `const`/`function`/`catch`); a read of a shadowed name is safe and ignored.
 * @param parent the parent node, so `isRuntimeIdentifierReference` can tell a read from a declaration.
 */
function findLocalSetupReference(
    node: BabelNode | null | undefined,
    localBindings: Set<string>,
    shadowedBindings = new Set<string>(),
    parent: BabelNode | null = null,
): Identifier | null {
    if (!node) {
        return null;
    }

    if (
        node.type === 'Identifier' &&
        localBindings.has(node.name) &&
        !shadowedBindings.has(node.name) &&
        isRuntimeIdentifierReference(node, parent)
    ) {
        return node;
    }

    const childShadowedBindings = new Set(shadowedBindings);

    // e.g. `validator: (count) => { const count = 2; return count; }` shadows a setup binding named
    // `count` for its whole body. Collect everything the function declares locally - parameters plus
    // body-level const/let/var, nested function/class names, and catch params - so a same-named local
    // is not mistaken for the top-level binding it shadows.
    if (isFunctionLikeNode(node)) {
        const functionNode = node as unknown as { params: BabelNode[]; body: BabelNode };

        functionNode.params.forEach((param) =>
            forEachPatternIdentifier(param, (identifier) => childShadowedBindings.add(identifier.name)),
        );

        collectFunctionScopeDeclarations(functionNode.body, childShadowedBindings);
    }

    for (const child of childBabelNodes(node, isTypeKey)) {
        const reference = findLocalSetupReference(child, localBindings, childShadowedBindings, node);

        if (reference) {
            return reference;
        }
    }

    return null;
}

export { findLocalSetupReference, findLocalSetupTypeReference };
