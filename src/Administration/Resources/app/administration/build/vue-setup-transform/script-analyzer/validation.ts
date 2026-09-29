/**
 * @sw-package framework
 */

/**
 * Enforces the semantic limits of the Shopware setup dialect.
 *
 * These checks keep generated code compatible with the synchronous extendable setup runtime and
 * prevent user bindings from colliding with compiler-owned helpers or override-private state.
 */

import type { File as BabelFile, Node as BabelNode } from '@babel/types';
import { ShopwareSetupTransformError } from '../utils/transform-error';
import type { ShopwareSetupMode } from '../utils/shopware-setup-block';
import { absoluteRange, walk } from './utils';
import { isFunctionLikeNode } from '../utils/ast-traversal';
import { RESERVED_OVERRIDE_STATE_NAME, SHOPWARE_SETUP_INTERNAL_PREFIX, type ShopwareSetupMacroName } from './macros';
import { RESERVED_HELPER_NAMES, VUE_BUILTIN_MACRO_NAMES, getWrongModeWalkChecks } from './macro-registry';

/**
 * Carries a declared or imported name with the AST node used for diagnostics.
 */
type NamedBinding = {
    name: string;
    node: BabelNode;
    importSource?: string;
};

/**
 * The global object the generated setup reads its runtime bridge from
 * (`Shopware.Component.attachOverrides(...)` in base mode, `overrideComponentSetup()` in override
 * mode); a user binding of this name would shadow it.
 */
const RESERVED_RUNTIME_GLOBAL_NAME = 'Shopware';

/**
 * Rejects syntax that would require native `<script setup>` semantics we do not emulate.
 * Meaning: Unsupported Vue macros, top-level await, and ES module exports.
 */
function assertNoUnsupportedSyntax(ast: BabelFile, mode: ShopwareSetupMode, scriptOffset: number): void {
    const wrongModeChecks = getWrongModeWalkChecks(mode);

    walk(ast.program, (node, ancestors) => {
        // A nested `swDefinePublic()` / `swDefineOverride()` is deliberately not rejected, matching how
        // Vue treats its own macros: it only recognises them at the top level and leaves a nested call
        // alone. Either way the marker is missing where it counts, and the required-marker rule says so.
        if (node.type === 'CallExpression' && node.callee.type === 'Identifier') {
            // Wrong-mode helpers such as useSwProps() in base mode are rejected in any position,
            // because there is no runtime input they could alias.
            const calleeName = node.callee.name;
            const wrongModeCheck = wrongModeChecks.find((check) => check.name === calleeName);

            if (wrongModeCheck) {
                throw new ShopwareSetupTransformError(wrongModeCheck.message, absoluteRange(node, scriptOffset));
            }
        }

        // Reject top-level await, in both its forms - `await x` and `for await (… of …)`.
        //  Vue rewrites top-level await into async setup() with context preservation. Shopware setup keeps
        //  the current synchronous base/override callback contract, so it cannot be supported at the moment.
        const isAwait = node.type === 'AwaitExpression' || (node.type === 'ForOfStatement' && node.await);

        // `isFunctionLikeNode` omits `TSDeclareFunction`, which is fine here: an ambient `declare
        // function` has no body, so an `await` can never sit inside one.
        if (isAwait && !ancestors.some(isFunctionLikeNode)) {
            throw new ShopwareSetupTransformError(
                'Top-level await is not supported inside Shopware setup blocks.',
                absoluteRange(node, scriptOffset),
            );
        }

        // Reject ES module exports:
        //  Same as Vue: native <script setup> rejects runtime ES module exports because setup bindings are exposed
        //  through the generated setup return, not through module exports.
        //  Two forms Vue still accepts stay accepted here:
        //   - type-only exports (`export type X`, `export interface X`): no runtime output, hoisted whole.
        //   - exports inside an ambient module augmentation (`declare module 'vue' { export interface ... }`):
        //     Vue only inspects top-level nodes; the whole `declare module` is hoisted, so its nested
        //     exports never reach the generated callback body.
        if (node.type === 'ExportNamedDeclaration') {
            const isTypeOnlyExport =
                node.exportKind === 'type' ||
                node.declaration?.type === 'TSInterfaceDeclaration' ||
                node.declaration?.type === 'TSTypeAliasDeclaration';
            const isInsideAmbientModule = ancestors.some((ancestor) => ancestor.type === 'TSModuleDeclaration');

            if (!isTypeOnlyExport && !isInsideAmbientModule) {
                throw new ShopwareSetupTransformError(
                    '<script setup> cannot contain ES module exports.',
                    absoluteRange(node, scriptOffset),
                );
            }
        }

        if (node.type === 'ExportAllDeclaration' || node.type === 'ExportDefaultDeclaration') {
            throw new ShopwareSetupTransformError(
                '<script setup> cannot contain ES module exports.',
                absoluteRange(node, scriptOffset),
            );
        }
    });
}

/**
 * Prevents user bindings from shadowing generated composable-style helper names.
 */
function assertReservedMacroNames(bindings: NamedBinding[], scriptOffset: number): void {
    const helpers = RESERVED_HELPER_NAMES;
    const vueBuiltins = VUE_BUILTIN_MACRO_NAMES;

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
                absoluteRange(binding.node, scriptOffset),
            );
        }

        if (binding.name === RESERVED_OVERRIDE_STATE_NAME) {
            throw new ShopwareSetupTransformError(
                `"${binding.name}" is reserved for Shopware override-private state and must not be declared or imported.`,
                absoluteRange(binding.node, scriptOffset),
            );
        }

        if (binding.name.startsWith(SHOPWARE_SETUP_INTERNAL_PREFIX)) {
            throw new ShopwareSetupTransformError(
                `"${binding.name}" uses the reserved "${SHOPWARE_SETUP_INTERNAL_PREFIX}" prefix of the Shopware setup transform and must not be declared or imported.`,
                absoluteRange(binding.node, scriptOffset),
            );
        }

        // `__proto__` is a valid binding name, but the generated state maps emit `name: alias` object
        // properties, and `__proto__:` is the prototype-setter syntax rather than an own key - so the
        // binding would be silently dropped from returned state. Reject it with a clear message instead.
        if (binding.name === '__proto__') {
            throw new ShopwareSetupTransformError(
                '"__proto__" cannot be a Shopware setup binding: it collides with prototype-setter syntax in the ' +
                    'generated state object and would be silently dropped. Rename the binding.',
                absoluteRange(binding.node, scriptOffset),
            );
        }

        // The generated footer destructures its state from `Shopware.Component.attachOverrides(...)`,
        // so a top-level binding named `Shopware` would land on the destructure's left-hand side and
        // shadow the global it reads from on the right-hand side (a temporal-dead-zone crash at runtime).
        if (binding.name === RESERVED_RUNTIME_GLOBAL_NAME) {
            throw new ShopwareSetupTransformError(
                `"${RESERVED_RUNTIME_GLOBAL_NAME}" is reserved by the Shopware setup transform, which generates code that reads the global "${RESERVED_RUNTIME_GLOBAL_NAME}" object. Rename your binding.`,
                absoluteRange(binding.node, scriptOffset),
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

/**
 * @private
 */
export { type NamedBinding, assertNoUnsupportedSyntax, assertReservedMacroNames, assertStaticObjectEntries };
