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
 * @typedef {import('@babel/types').ImportDeclaration} ImportDeclaration
 * @typedef {import('@babel/types').Statement} Statement
 * @typedef {import('@babel/types').VariableDeclarator} VariableDeclarator
 *
 * @typedef {object} RuntimeBinding
 * @property {string} name
 * @property {BabelNode} node
 */

/**
 * TypeScript ambient declarations are compile-time only and must not become setup callback code or returned state.
 *
 * @param {Statement} statement
 * @returns {boolean}
 */
function isTypeScriptDeclareDeclaration(statement) {
    return Boolean(
        statement.declare &&
            (statement.type === 'VariableDeclaration' ||
                statement.type === 'TSDeclareFunction' ||
                statement.type === 'ClassDeclaration' ||
                statement.type === 'TSEnumDeclaration' ||
                statement.type === 'TSModuleDeclaration'),
    );
}

/**
 * Tracks import locals so imports stay preserved but are never returned as state.
 *
 * @param {ImportDeclaration} importNode
 * @param {Set<string>} importedBindings
 * @returns {void}
 */
function collectImportBindings(importNode, importedBindings) {
    importNode.specifiers.forEach((specifier) => {
        if (!specifier.local?.name) {
            return;
        }

        importedBindings.add(specifier.local.name);
    });
}

/**
 * Adds a top-level runtime binding and rejects duplicates before lowering.
 *
 * @param {RuntimeBinding[]} runtimeBindings
 * @param {Set<string>} runtimeBindingNames
 * @param {string} name
 * @param {BabelNode} node
 * @param {number} scriptOffset
 * @returns {void}
 */
function addRuntimeBinding(runtimeBindings, runtimeBindingNames, name, node, scriptOffset) {
    if (runtimeBindingNames.has(name)) {
        // Vue mostly relies on JavaScript parser scope errors here. Shopware also rejects duplicate collected names
        // explicitly because aliases such as var/function combinations can otherwise overwrite returned state.
        throw new ShopwareSetupTransformError(
            `Duplicate top-level Shopware setup binding "${name}".`,
            scriptOffset + getNodeRange(node, scriptOffset).start,
        );
    }

    runtimeBindingNames.add(name);
    runtimeBindings.push({
        name,
        node,
    });
}

/**
 * Collects runtime-visible setup bindings from one declaration pattern.
 *
 * @param {RuntimeBinding[]} runtimeBindings
 * @param {Set<string>} runtimeBindingNames
 * @param {BabelNode | null | undefined} pattern
 * @param {number} scriptOffset
 * @returns {void}
 */
function collectRuntimeBindingPattern(runtimeBindings, runtimeBindingNames, pattern, scriptOffset) {
    if (!pattern) {
        return;
    }

    if (pattern.type === 'Identifier') {
        addRuntimeBinding(runtimeBindings, runtimeBindingNames, pattern.name, pattern, scriptOffset);
        return;
    }

    if (pattern.type === 'RestElement') {
        collectRuntimeBindingPattern(runtimeBindings, runtimeBindingNames, pattern.argument, scriptOffset);
        return;
    }

    if (pattern.type === 'AssignmentPattern') {
        collectRuntimeBindingPattern(runtimeBindings, runtimeBindingNames, pattern.left, scriptOffset);
        return;
    }

    if (pattern.type === 'ArrayPattern') {
        pattern.elements.forEach((element) => {
            collectRuntimeBindingPattern(runtimeBindings, runtimeBindingNames, element, scriptOffset);
        });
        return;
    }

    if (pattern.type === 'ObjectPattern') {
        pattern.properties.forEach((property) => {
            if (property.type === 'RestElement') {
                collectRuntimeBindingPattern(runtimeBindings, runtimeBindingNames, property.argument, scriptOffset);
                return;
            }

            collectRuntimeBindingPattern(runtimeBindings, runtimeBindingNames, property.value, scriptOffset);
        });
    }
}

/**
 * Allows `const props = useSwProps()` without returning `props` as component state.
 *
 * @param {VariableDeclarator} declaration
 * @param {'base' | 'override'} mode
 * @returns {boolean}
 */
function isRuntimeInputAlias(declaration, mode) {
    const runtimeInputHelpers =
        mode === 'base'
            ? new Set([
                  'useSwProps',
                  'useSwContext',
              ])
            : new Set([
                  'useSwPreviousState',
                  'useSwProps',
                  'useSwContext',
              ]);

    return (
        declaration.id.type === 'Identifier' &&
        declaration.init?.type === 'CallExpression' &&
        declaration.init.callee.type === 'Identifier' &&
        runtimeInputHelpers.has(declaration.init.callee.name)
    );
}

/**
 * Checks whether a variable declaration reads setup input through a supported helper/macro.
 *
 * @param {VariableDeclarator} declaration
 * @returns {boolean}
 */
function isSetupInputDeclaration(declaration) {
    const init = unwrapTransparentMacroExpression(declaration.init);

    return (
        init?.type === 'CallExpression' &&
        init.callee.type === 'Identifier' &&
        (init.callee.name === 'defineProps' ||
            init.callee.name === 'defineEmits' ||
            init.callee.name === 'defineSlots' ||
            init.callee.name === 'withDefaults' ||
            init.callee.name === 'useSwProps')
    );
}

/**
 * Classifies top-level declarations that become private/base or override state.
 *
 * @param {Statement} statement
 * @param {RuntimeBinding[]} runtimeBindings
 * @param {Set<string>} runtimeBindingNames
 * @param {number} scriptOffset
 * @param {'base' | 'override'} mode
 * @returns {void}
 */
function collectRuntimeBinding(statement, runtimeBindings, runtimeBindingNames, scriptOffset, mode) {
    if (isTypeScriptDeclareDeclaration(statement)) {
        // Vue/TypeScript treat ambient declarations as type-only. The lowered callback only contains runtime setup
        // code, so keeping or returning these declarations would produce invalid output.
        throw new ShopwareSetupTransformError(
            'TypeScript declare declarations are not runtime Shopware setup bindings.',
            scriptOffset + getNodeRange(statement, scriptOffset).start,
        );
    }

    if (statement.type === 'VariableDeclaration') {
        statement.declarations.forEach((declaration) => {
            if (isSetupInputDeclaration(declaration)) {
                if (declaration.id.type !== 'Identifier') {
                    collectRuntimeBindingPattern(runtimeBindings, runtimeBindingNames, declaration.id, scriptOffset);
                }

                return;
            }

            if (isRuntimeInputAlias(declaration, mode)) {
                return;
            }

            collectRuntimeBindingPattern(runtimeBindings, runtimeBindingNames, declaration.id, scriptOffset);
        });
        return;
    }

    if (statement.type === 'FunctionDeclaration' || statement.type === 'ClassDeclaration') {
        if (!statement.id?.name) {
            throw new ShopwareSetupTransformError(
                'Anonymous top-level declarations are not supported inside Shopware setup blocks.',
                scriptOffset + getNodeRange(statement, scriptOffset).start,
            );
        }

        addRuntimeBinding(runtimeBindings, runtimeBindingNames, statement.id.name, statement.id, scriptOffset);
        return;
    }

    if (statement.type === 'TSEnumDeclaration') {
        addRuntimeBinding(runtimeBindings, runtimeBindingNames, statement.id.name, statement.id, scriptOffset);
    }
}

module.exports = {
    collectImportBindings,
    collectRuntimeBinding,
};
