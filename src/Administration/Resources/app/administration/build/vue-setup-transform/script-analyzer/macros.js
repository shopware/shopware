/**
 * @sw-package framework
 */

const { ShopwareSetupTransformError } = require('../utils/transform-error');
const {
    getNodeRange,
    unwrapTransparentMacroExpression,
} = require('./utils');

/**
 * @typedef {import('@babel/types').Node} BabelNode
 * @typedef {import('@babel/types').Statement} Statement
 * @typedef {import('@babel/types').ExpressionStatement} ExpressionStatement
 * @typedef {import('@babel/types').CallExpression} CallExpression
 * @typedef {import('@babel/types').ObjectExpression} ObjectExpression
 */

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
 *
 * @param {CallExpression} callNode
 * @param {number} scriptOffset
 * @param {'swDefinePublic' | 'swDefineOverride'} macroName
 * @returns {ObjectExpression}
 */
function assertSingleArgument(callNode, scriptOffset, macroName) {
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
 *
 * @param {ExpressionStatement & { expression: CallExpression }} statement
 * @param {number} scriptOffset
 * @param {'swDefinePublic' | 'swDefineOverride'} macroName
 * @param {'public' | 'override'} entryType
 * @returns {string[]}
 */
function extractStaticObjectMarker(statement, scriptOffset, macroName, entryType) {
    const callNode = statement.expression;
    const publicObject = assertSingleArgument(callNode, scriptOffset, macroName);
    const seenKeys = new Set();

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
 *
 * @param {Statement} statement
 * @param {string} macroName
 * @returns {statement is ExpressionStatement & { expression: CallExpression }}
 */
function isStatementCompilerMacro(statement, macroName) {
    return (
        statement.type === 'ExpressionStatement' &&
        statement.expression.type === 'CallExpression' &&
        statement.expression.callee.type === 'Identifier' &&
        statement.expression.callee.name === macroName
    );
}

/**
 * Returns a top-level Vue compiler macro call through transparent TypeScript wrappers.
 *
 * @param {Statement} statement
 * @param {string} macroName
 * @returns {CallExpression | null}
 */
function getStatementCompilerMacroCall(statement, macroName) {
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
 *
 * @param {BabelNode} node
 * @param {string} name
 * @returns {node is CallExpression}
 */
function isCompilerMacroCall(node, name) {
    return node.type === 'CallExpression' && node.callee.type === 'Identifier' && node.callee.name === name;
}

/**
 * Adds a direct top-level setup macro call to one of the analyzer buckets.
 *
 * @param {BabelNode | null | undefined} expression
 * @param {object} buckets
 * @param {CallExpression[]} buckets.definePropsCalls
 * @param {CallExpression[]} buckets.defineEmitsCalls
 * @param {CallExpression[]} buckets.defineSlotsCalls
 * @param {CallExpression[]} buckets.withDefaultsCalls
 * @param {Set<CallExpression>} buckets.topLevelUnsupportedMacroCalls
 * @returns {void}
 */
function collectTopLevelSetupMacroCall(expression, buckets) {
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
 *
 * @param {Statement} statement
 * @param {object} buckets
 * @param {CallExpression[]} buckets.definePropsCalls
 * @param {CallExpression[]} buckets.defineEmitsCalls
 * @param {CallExpression[]} buckets.defineSlotsCalls
 * @param {CallExpression[]} buckets.withDefaultsCalls
 * @param {Set<CallExpression>} buckets.topLevelUnsupportedMacroCalls
 * @returns {void}
 */
function collectTopLevelSetupMacroCalls(statement, buckets) {
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
 *
 * @param {BabelNode} node
 * @returns {node is CallExpression}
 */
function isWithDefaultsCall(node) {
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
