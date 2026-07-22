/**
 * @sw-package framework
 */

/**
 * Defines and validates Shopware setup compiler macros.
 *
 * The script analyzer uses this module to recognize the top-level marker calls that declare
 * public base bindings or override replacement bindings before normal runtime state is collected.
 */

import type { CallExpression, Node as BabelNode, ObjectExpression } from '@babel/types';
import { ShopwareSetupTransformError } from '../utils/transform-error';
import { getNodeRange } from './utils';

type ShopwareSetupMacroName = 'swDefinePublic' | 'swDefineOverride';
type ShopwareSetupEntryType = 'public' | 'override';

const RESERVED_OVERRIDE_STATE_NAME = '__swOverride';

// Every binding the transform generates is prefixed with this, so reserving it lets generated names
// stay deterministic and collision-free without renaming user code.
const SHOPWARE_SETUP_INTERNAL_PREFIX = '__swSetup';

/**
 * Enforces the single object-literal shape of `swDefinePublic({...})`.
 */
function assertSingleArgument(
    callNode: CallExpression,
    scriptOffset: number,
    macroName: ShopwareSetupMacroName,
): ObjectExpression {
    if (callNode.arguments.length !== 1 || callNode.arguments[0].type !== 'ObjectExpression') {
        throw new ShopwareSetupTransformError(
            `${macroName}() requires exactly one object-literal argument.`,
            scriptOffset + getNodeRange(callNode, scriptOffset).start,
        );
    }

    return callNode.arguments[0];
}

/**
 * Extracts the exposed local binding names from the top-level `swDefinePublic()` marker call.
 */
function extractStaticObjectMarker(
    callNode: CallExpression,
    scriptOffset: number,
    macroName: ShopwareSetupMacroName,
    entryType: ShopwareSetupEntryType,
): string[] {
    const publicObject = assertSingleArgument(callNode, scriptOffset, macroName);
    const seenKeys = new Set<string>();

    return publicObject.properties.map((property) => {
        if (property.type === 'SpreadElement') {
            throw new ShopwareSetupTransformError(
                `Spread properties are not supported inside ${macroName}().`,
                scriptOffset + getNodeRange(property, scriptOffset).start,
            );
        }

        if (property.type !== 'ObjectProperty') {
            throw new ShopwareSetupTransformError(
                `${macroName}() only supports plain object properties.`,
                scriptOffset + getNodeRange(property, scriptOffset).start,
            );
        }

        // Only shorthand bindings are allowed so the public key always equals the local binding
        // name. Renaming or string/computed keys would let a public key shadow another binding.
        if (property.computed || !property.shorthand || property.key.type !== 'Identifier') {
            throw new ShopwareSetupTransformError(
                `${macroName}() only supports shorthand bindings such as { a, b }. Renaming and string or computed keys (for example { a: b } or { 'a': b }) are not supported.`,
                scriptOffset + getNodeRange(property, scriptOffset).start,
            );
        }

        const localName = property.key.name;

        if (localName === RESERVED_OVERRIDE_STATE_NAME) {
            throw new ShopwareSetupTransformError(
                `"${localName}" is reserved for Shopware override-private state and cannot be exposed with ${macroName}().`,
                scriptOffset + getNodeRange(property, scriptOffset).start,
            );
        }

        if (seenKeys.has(localName)) {
            throw new ShopwareSetupTransformError(
                `Duplicate ${entryType} Shopware setup binding key "${localName}".`,
                scriptOffset + getNodeRange(property, scriptOffset).start,
            );
        }

        seenKeys.add(localName);

        return localName;
    });
}

/**
 * Detects a compiler macro call expression at any AST position, without unwrapping.
 *
 * Matches the `defineProps()` node itself, wherever it sits (e.g. nested in `withDefaults(...)`).
 */
function isCompilerMacroCall(node: BabelNode, name: string): node is CallExpression {
    return node.type === 'CallExpression' && node.callee.type === 'Identifier' && node.callee.name === name;
}

/**
 * Detects `withDefaults(...)` call expressions. The Vue compiler validates the nested defineProps() shape later.
 */
function isWithDefaultsCall(node: BabelNode): node is CallExpression {
    return isCompilerMacroCall(node, 'withDefaults');
}

export {
    type ShopwareSetupEntryType,
    type ShopwareSetupMacroName,
    RESERVED_OVERRIDE_STATE_NAME,
    SHOPWARE_SETUP_INTERNAL_PREFIX,
    extractStaticObjectMarker,
    isCompilerMacroCall,
    isWithDefaultsCall,
};
