/**
 * @sw-package framework
 */

/**
 * Guards one edge case: a hoisted macro argument that reads a local setup binding.
 *
 * The transform hoists `defineProps(...)`, `withDefaults(...)`, `defineEmits(...)`, and
 * `defineOptions(...)` to the generated script root, while every other top-level statement moves into
 * the Shopware setup callback. A macro argument that references a callback-local value would therefore
 * read a binding that no longer exists at the root:
 *
 * ```ts
 * const events = ['save'];
 * const emit = defineEmits(events); // hoisted -> `events` is out of reach at the root
 * ```
 *
 * The scan is scope-aware: identifiers shadowed by function parameters inside the argument (for
 * example a prop `validator` callback parameter) are fine and stay accepted.
 */

import type { CallExpression, Identifier, Node as BabelNode } from '@babel/types';
import { ShopwareSetupTransformError } from '../utils/transform-error';
import { forEachPatternIdentifier } from '../utils/babel-patterns';
import { childBabelNodes, findInTree, isTypeKey } from '../utils/ast-traversal';
import { getNodeRange } from './utils';

/**
 * Checks whether an identifier reads a runtime value instead of declaring or naming one.
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
 * Returns the leftmost identifier of a type entity name (`A` in `A`, `A.B`, or `A.B.C`).
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
 * Finds the first local setup binding read inside a macro argument that is moved to the generated script root.
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

/**
 * Hoisted Vue macros run outside the generated Shopware setup callback.
 * Their runtime arguments must therefore stay independent from setup-local values.
 */
function assertHoistedMacroArgumentsDoNotUseLocalSetup({
    scriptOffset,
    localSetupNames,
    macroCalls,
}: {
    scriptOffset: number;
    localSetupNames: Set<string>;
    macroCalls: { name: string; call: CallExpression }[];
}): void {
    macroCalls.forEach(({ name, call }) => {
        // Both the value arguments and the type arguments (`defineProps<...>()`) are hoisted with the
        // call. Value arguments are scanned for value references; every hoisted node (arguments and type
        // parameters) is scanned for type references into a binding that stays inside the callback.
        const valueReference = call.arguments
            .map((argument) => findLocalSetupReference(argument, localSetupNames))
            .find(Boolean);
        const typeReference = [
            ...call.arguments,
            call.typeParameters,
        ]
            .map((hoistedNode) => findLocalSetupTypeReference(hoistedNode, localSetupNames))
            .find(Boolean);
        const reference = valueReference ?? typeReference;

        if (!reference) {
            return;
        }

        throw new ShopwareSetupTransformError(
            `${name}() arguments are hoisted outside the Shopware setup callback and must not reference local setup bindings. Use inline literals or imported constants instead.`,
            scriptOffset + getNodeRange(reference, scriptOffset).start,
        );
    });
}

export { assertHoistedMacroArgumentsDoNotUseLocalSetup };
