/**
 * @sw-package framework
 */

/**
 * Enforces the semantic limits of the Shopware setup dialect.
 *
 * These checks keep generated code compatible with the synchronous extendable setup runtime and
 * prevent user bindings from colliding with compiler-owned helpers or override-private state.
 */

import type { CallExpression, File as BabelFile, Node as BabelNode } from '@babel/types';
import { ShopwareSetupTransformError } from '../utils/transform-error';
import type { ShopwareSetupMode } from '../utils/shopware-setup-block';
import { getNodeRange, isFunctionNode, walk } from './utils';
import { RESERVED_OVERRIDE_STATE_NAME, SHOPWARE_SETUP_INTERNAL_PREFIX, type ShopwareSetupMacroName } from './macros';
import { getReservedHelperNames, getTopLevelOnlyWalkChecks, getVueBuiltinMacroNames, getWrongModeWalkChecks } from './macro-registry';

/**
 * Carries a declared or imported name with the AST node used for diagnostics.
 */
type NamedBinding = {
    name: string;
    node: BabelNode;
    importSource?: string;
};

/**
 * Rejects syntax that would require native `<script setup>` semantics we do not emulate.
 * Meaning: Unsupported Vue macros, top-level await, and ES module exports.
 */
function assertNoUnsupportedSyntax(
    ast: BabelFile,
    mode: ShopwareSetupMode,
    scriptOffset: number,
    topLevelMarkerCalls: Map<string, Set<CallExpression>>,
): void {
    const wrongModeChecks = getWrongModeWalkChecks(mode);
    const topLevelOnlyChecks = getTopLevelOnlyWalkChecks();

    walk(ast.program, (node, ancestors) => {
        if (node.type === 'CallExpression' && node.callee.type === 'Identifier') {
            const calleeName = node.callee.name;

            // Wrong-mode helpers such as useSwProps() in base mode are rejected in any position,
            // because there is no runtime input they could alias.
            const wrongModeCheck = wrongModeChecks.find((check) => check.name === calleeName);

            if (wrongModeCheck) {
                throw new ShopwareSetupTransformError(
                    wrongModeCheck.message,
                    scriptOffset + getNodeRange(node, scriptOffset).start,
                );
            }

            // Shopware marker macros are only meaningful as top-level statements; a nested call would
            // otherwise silently stay in the generated callback body.
            const topLevelOnlyCheck = topLevelOnlyChecks.find((check) => check.name === calleeName);

            if (topLevelOnlyCheck && !topLevelMarkerCalls.get(calleeName)?.has(node)) {
                throw new ShopwareSetupTransformError(
                    topLevelOnlyCheck.message,
                    scriptOffset + getNodeRange(node, scriptOffset).start,
                );
            }
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
    const helpers = getReservedHelperNames();
    const vueBuiltins = getVueBuiltinMacroNames();

    bindings.forEach((binding) => {
        // Vue macro names may be imported from 'vue' (the call stays a macro and Vue drops the
        // import again during its own compilation). Any other source is rejected: Vue would still
        // treat the calls as macros, silently hijacking the imported function.
        if (binding.importSource === 'vue' && vueBuiltins.has(binding.name)) {
            return;
        }

        if (helpers.has(binding.name)) {
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
