/**
 * @sw-package framework
 */

import type { CallExpression, File as BabelFile, Node as BabelNode } from '@babel/types';
import { ShopwareSetupTransformError } from '../utils/transform-error';
import { getNodeRange, isFunctionNode, walk } from './utils';
import {
    RESERVED_OVERRIDE_STATE_NAME,
    SHOPWARE_SETUP_INTERNAL_PREFIX,
    type ShopwareSetupMacroName,
} from './macros';

type NamedBinding = {
    name: string;
    node: BabelNode;
};

// TODO: the override transform adds an override helper set and dispatches on the setup mode.
const BASE_HELPERS = new Set([
    'swDefinePublic',
    'defineEmits',
    'defineExpose',
    'defineOptions',
    'defineSlots',
    'withDefaults',
    'useSwContext',
]);

/**
 * Rejects syntax that would require native `<script setup>` semantics we do not emulate.
 * Meaning: Unsupported Vue macros, top-level await, and ES module exports.
 *
 * TODO: the override transform adds mode-dependent rejections for its marker macro and runtime helpers here.
 */
function assertNoUnsupportedSyntax(
    ast: BabelFile,
    scriptOffset: number,
    topLevelPublicCalls: Set<CallExpression>,
    topLevelUnsupportedMacroCalls: Set<CallExpression>,
): void {
    walk(ast.program, (node, ancestors) => {
        // Reject unsupported Vue macros:
        //  Vue only treats these calls as compiler macros in supported top-level setup positions.
        //  Nested calls are left untouched like compiler-sfc does.
        if (node.type === 'CallExpression' && node.callee.type === 'Identifier' && topLevelUnsupportedMacroCalls.has(node)) {
            throw new ShopwareSetupTransformError(
                `Vue macro ${node.callee.name}() is not supported inside Shopware setup blocks.`,
                scriptOffset + getNodeRange(node, scriptOffset).start,
            );
        }

        // Reject top level await:
        //  Vue rewrites top-level await into async setup() with context preservation.
        //  Shopware setup keeps the current synchronous base/override callback contract, so top-level await cannot be supported at the moment.
        if (node.type === 'AwaitExpression') {
            const isInsideFunction = ancestors.some(isFunctionNode);

            if (!isInsideFunction) {
                throw new ShopwareSetupTransformError(
                    'Top-level await is not supported inside Shopware setup blocks.',
                    scriptOffset + getNodeRange(node, scriptOffset).start,
                );
            }
        }

        // Same difference as AwaitExpression: Vue can make setup async, but the Shopware override pipeline is sync.
        if (node.type === 'ForOfStatement' && node.await) {
            const isInsideFunction = ancestors.some(isFunctionNode);

            if (!isInsideFunction) {
                throw new ShopwareSetupTransformError(
                    'Top-level await is not supported inside Shopware setup blocks.',
                    scriptOffset + getNodeRange(node, scriptOffset).start,
                );
            }
        }

        // Ensure swDefinePublic() is only called at top level
        if (
            node.type === 'CallExpression' &&
            node.callee.type === 'Identifier' &&
            node.callee.name === 'swDefinePublic' &&
            !topLevelPublicCalls.has(node)
        ) {
            throw new ShopwareSetupTransformError(
                'swDefinePublic() must be called once at the top level of a base Shopware setup block.',
                scriptOffset + getNodeRange(node, scriptOffset).start,
            );
        }

        // Reject ES module exports:
        //  Same as Vue: native <script setup> rejects runtime ES module exports because setup bindings are exposed
        //  through the generated setup return, not through module exports.
        if (
            node.type === 'ExportNamedDeclaration' ||
            node.type === 'ExportAllDeclaration' ||
            node.type === 'ExportDefaultDeclaration'
        ) {
            throw new ShopwareSetupTransformError(
                '<script setup> cannot contain ES module exports.',
                scriptOffset + getNodeRange(node, scriptOffset).start,
            );
        }
    });
}

/**
 * Prevents user bindings from shadowing generated composable-style helper names.
 */
function assertReservedMacroNames(bindings: NamedBinding[], scriptOffset: number): void {
    bindings.forEach((binding) => {
        if (BASE_HELPERS.has(binding.name)) {
            throw new ShopwareSetupTransformError(
                `"${binding.name}" is reserved by the Shopware setup transform and must not be declared or imported.`,
                scriptOffset + getNodeRange(binding.node, scriptOffset).start,
            );
        }

        if (binding.name === RESERVED_OVERRIDE_STATE_NAME) {
            throw new ShopwareSetupTransformError(
                `"${binding.name}" is reserved for Shopware override-private state and must not be declared or imported.`,
                scriptOffset + getNodeRange(binding.node, scriptOffset).start,
            );
        }

        if (binding.name.startsWith(SHOPWARE_SETUP_INTERNAL_PREFIX)) {
            throw new ShopwareSetupTransformError(
                `"${binding.name}" uses the reserved "${SHOPWARE_SETUP_INTERNAL_PREFIX}" prefix of the Shopware setup transform and must not be declared or imported.`,
                scriptOffset + getNodeRange(binding.node, scriptOffset).start,
            );
        }
    });
}

/**
 * Ensures exposed names refer to local runtime bindings, not imports or missing names.
 */
function assertStaticObjectEntries(
    localNames: string[],
    runtimeBindingNames: Set<string>,
    importedBindings: Set<string>,
    scriptOffset: number,
    macroName: ShopwareSetupMacroName,
): void {
    localNames.forEach((localName) => {
        if (importedBindings.has(localName)) {
            throw new ShopwareSetupTransformError(
                `Imported binding "${localName}" cannot be exposed with ${macroName}().`,
                scriptOffset,
            );
        }

        if (!runtimeBindingNames.has(localName)) {
            throw new ShopwareSetupTransformError(
                `${macroName}() references unknown local binding "${localName}".`,
                scriptOffset,
            );
        }
    });
}

export { type NamedBinding, assertNoUnsupportedSyntax, assertReservedMacroNames, assertStaticObjectEntries };
