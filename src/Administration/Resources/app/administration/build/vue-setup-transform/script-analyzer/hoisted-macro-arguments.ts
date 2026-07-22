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
import { forEachPatternIdentifier, isBabelNodeLike } from '../utils/babel-patterns';
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

function shouldSkipReferenceChild(key: string): boolean {
    return [
        'loc',
        'range',
        'start',
        'end',
        'leadingComments',
        'trailingComments',
        'innerComments',
        'typeAnnotation',
        'typeParameters',
        'typeArguments',
        'returnType',
    ].includes(key);
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

    // e.g. `validator: (count) => count > 0` shadows a setup binding named `count` for its body.
    if (
        node.type === 'FunctionDeclaration' ||
        node.type === 'FunctionExpression' ||
        node.type === 'ArrowFunctionExpression' ||
        node.type === 'ObjectMethod' ||
        node.type === 'ClassMethod' ||
        node.type === 'ClassPrivateMethod'
    ) {
        node.params.forEach((param) =>
            forEachPatternIdentifier(param, (identifier) => childShadowedBindings.add(identifier.name)),
        );
    }

    for (const [
        key,
        value,
    ] of Object.entries(node as unknown as Record<string, unknown>)) {
        if (shouldSkipReferenceChild(key)) {
            continue;
        }

        if (Array.isArray(value)) {
            for (const child of value) {
                if (!isBabelNodeLike(child)) {
                    continue;
                }

                const reference = findLocalSetupReference(child, localBindings, childShadowedBindings, node);

                if (reference) {
                    return reference;
                }
            }

            continue;
        }

        if (isBabelNodeLike(value)) {
            const reference = findLocalSetupReference(value, localBindings, childShadowedBindings, node);

            if (reference) {
                return reference;
            }
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
    runtimeBindingNames,
    macroCalls,
}: {
    scriptOffset: number;
    runtimeBindingNames: Set<string>;
    macroCalls: { name: string; call: CallExpression }[];
}): void {
    macroCalls.forEach(({ name, call }) => {
        call.arguments.forEach((argument) => {
            const reference = findLocalSetupReference(argument, runtimeBindingNames);

            if (!reference) {
                return;
            }

            throw new ShopwareSetupTransformError(
                `${name}() arguments are hoisted outside the Shopware setup callback and must not reference local setup bindings. Use inline literals or imported constants instead.`,
                scriptOffset + getNodeRange(reference, scriptOffset).start,
            );
        });
    });
}

export { assertHoistedMacroArgumentsDoNotUseLocalSetup };
