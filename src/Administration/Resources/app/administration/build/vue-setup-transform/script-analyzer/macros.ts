/**
 * @sw-package framework
 */

import type {
    CallExpression,
    ExpressionStatement,
    Node as BabelNode,
    ObjectExpression,
    Statement,
} from '@babel/types';
import { ShopwareSetupTransformError } from '../utils/transform-error';
import {
    getNodeRange,
    unwrapTransparentMacroExpression,
} from './utils';

type ShopwareSetupMacroName = 'swDefinePublic' | 'swDefineOverride';
type ShopwareSetupEntryType = 'public' | 'override';
type StatementMacroCall = ExpressionStatement & { expression: CallExpression };
type SetupMacroBuckets = {
    definePropsCalls: CallExpression[],
    defineEmitsCalls: CallExpression[],
    defineSlotsCalls: CallExpression[],
    withDefaultsCalls: CallExpression[],
    topLevelUnsupportedMacroCalls: Set<CallExpression>,
};

const UNSUPPORTED_VUE_MACROS = new Set([
    'defineModel',
]);

const WRONG_MODE_SW_DEFINE_PUBLIC_MESSAGE = [
    'swDefinePublic() is a Shopware setup compile-time macro for base components.',
    'It declares which setup bindings are public and may be replaced by overrides.',
    'Override components must use swDefineOverride() to declare replacement bindings instead.',
].join(' ');

const WRONG_MODE_SW_DEFINE_OVERRIDE_MESSAGE = [
    'swDefineOverride() is a Shopware setup compile-time macro for override components.',
    'It declares which base component bindings this override replaces.',
    'Base components must use swDefinePublic() to expose overrideable setup bindings instead.',
].join(' ');

const RESERVED_OVERRIDE_STATE_NAME = '__swOverride';

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
 * Extracts the exposed local binding names from the top-level `swDefinePublic()` marker.
 */
function extractStaticObjectMarker(
    statement: StatementMacroCall,
    scriptOffset: number,
    macroName: ShopwareSetupMacroName,
    entryType: ShopwareSetupEntryType,
): string[] {
    const callNode = statement.expression;
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
 * Detects a compiler-style macro represented by one expression statement.
 */
function isStatementCompilerMacro(statement: Statement, macroName: string): statement is StatementMacroCall {
    return (
        statement.type === 'ExpressionStatement' &&
        statement.expression.type === 'CallExpression' &&
        statement.expression.callee.type === 'Identifier' &&
        statement.expression.callee.name === macroName
    );
}

/**
 * Returns a top-level Vue compiler macro call through transparent TypeScript wrappers.
 */
function getStatementCompilerMacroCall(statement: Statement, macroName: string): CallExpression | null {
    if (statement.type !== 'ExpressionStatement') {
        return null;
    }

    const call = unwrapTransparentMacroExpression(statement.expression);

    if (
        call?.type === 'CallExpression' &&
        call.callee.type === 'Identifier' &&
        call.callee.name === macroName
    ) {
        return call;
    }

    return null;
}

/**
 * Detects a compiler macro call expression.
 */
function isCompilerMacroCall(node: BabelNode, name: string): node is CallExpression {
    return node.type === 'CallExpression' && node.callee.type === 'Identifier' && node.callee.name === name;
}

/**
 * Adds a direct top-level setup macro call to one of the analyzer buckets.
 */
function collectTopLevelSetupMacroCall(expression: BabelNode | null | undefined, buckets: SetupMacroBuckets): void {
    const call = unwrapTransparentMacroExpression(expression);

    if (!call || call.type !== 'CallExpression' || call.callee.type !== 'Identifier') {
        return;
    }

    if (call.callee.name === 'defineProps') {
        buckets.definePropsCalls.push(call);
        return;
    }

    if (call.callee.name === 'defineEmits') {
        buckets.defineEmitsCalls.push(call);
        return;
    }

    if (call.callee.name === 'defineSlots') {
        buckets.defineSlotsCalls.push(call);
        return;
    }

    if (call.callee.name === 'withDefaults') {
        buckets.withDefaultsCalls.push(call);
        return;
    }

    if (UNSUPPORTED_VUE_MACROS.has(call.callee.name)) {
        buckets.topLevelUnsupportedMacroCalls.add(call);
    }
}

/**
 * Collects Vue compiler macro calls only from the direct top-level forms that compiler-sfc recognizes.
 */
function collectTopLevelSetupMacroCalls(statement: Statement, buckets: SetupMacroBuckets): void {
    if (statement.type === 'ExpressionStatement') {
        collectTopLevelSetupMacroCall(statement.expression, buckets);
        return;
    }

    if (statement.type !== 'VariableDeclaration') {
        return;
    }

    statement.declarations.forEach((declaration) => {
        collectTopLevelSetupMacroCall(declaration.init, buckets);
    });
}

/**
 * Detects `withDefaults(...)` call expressions. The Vue compiler validates the nested defineProps() shape later.
 */
function isWithDefaultsCall(node: BabelNode): node is CallExpression {
    return isCompilerMacroCall(node, 'withDefaults');
}

module.exports = {
    RESERVED_OVERRIDE_STATE_NAME,
    UNSUPPORTED_VUE_MACROS,
    WRONG_MODE_SW_DEFINE_OVERRIDE_MESSAGE,
    WRONG_MODE_SW_DEFINE_PUBLIC_MESSAGE,
    collectTopLevelSetupMacroCalls,
    extractStaticObjectMarker,
    getStatementCompilerMacroCall,
    isCompilerMacroCall,
    isStatementCompilerMacro,
    isWithDefaultsCall,
};

export {
    type SetupMacroBuckets,
    type ShopwareSetupEntryType,
    type ShopwareSetupMacroName,
    type StatementMacroCall,
    RESERVED_OVERRIDE_STATE_NAME,
    UNSUPPORTED_VUE_MACROS,
    WRONG_MODE_SW_DEFINE_OVERRIDE_MESSAGE,
    WRONG_MODE_SW_DEFINE_PUBLIC_MESSAGE,
    collectTopLevelSetupMacroCalls,
    extractStaticObjectMarker,
    getStatementCompilerMacroCall,
    isCompilerMacroCall,
    isStatementCompilerMacro,
    isWithDefaultsCall,
};
