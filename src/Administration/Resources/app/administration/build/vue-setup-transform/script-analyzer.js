/**
 * @sw-package framework
 */

const { parse } = require('@babel/parser');
const { ShopwareSetupTransformError } = require('./utils/transform-error');

/**
 * @typedef {import('@babel/types').File} BabelFile
 * @typedef {import('@babel/types').Node} BabelNode
 * @typedef {import('@babel/types').ImportDeclaration} ImportDeclaration
 * @typedef {import('@babel/types').Statement} Statement
 * @typedef {import('@babel/types').ExpressionStatement} ExpressionStatement
 * @typedef {import('@babel/types').CallExpression} CallExpression
 * @typedef {import('@babel/types').ObjectExpression} ObjectExpression
 * @typedef {import('@babel/types').ObjectProperty} ObjectProperty
 * @typedef {import('@babel/types').VariableDeclarator} VariableDeclarator
 *
 * @typedef {object} SourceRange
 * @property {number} start
 * @property {number} end
 *
 * @typedef {SourceRange & { code: string }} ImportBlock
 *
 * @typedef {object} RuntimeBinding
 * @property {string} name
 * @property {BabelNode} node
 *
 * @typedef {object} PublicEntry
 * @property {{ type: 'identifier' | 'string', value: string }} key
 * @property {string} localName
 *
 * @typedef {object} ShopwareSetupScriptAnalysis
 * @property {ImportBlock[]} imports
 * @property {string} body
 * @property {RuntimeBinding[]} runtimeBindings
 * @property {Set<string>} runtimeBindingNames
 * @property {Set<string>} importedBindings
 * @property {PublicEntry[]} publicEntries
 */

const UNSUPPORTED_VUE_MACROS = new Set([
    'defineProps',
    'defineEmits',
    'defineExpose',
    'defineOptions',
    'defineSlots',
    'defineModel',
    'withDefaults',
]);

const BASE_HELPERS = new Set([
    'swDefinePublic',
    'useSwProps',
    'useSwContext',
]);

const OVERRIDE_HELPERS = new Set([
    'useSwPreviousState',
    'useSwProps',
    'useSwContext',
]);

/**
 * Converts Babel source ranges into the transform's compact range shape.
 *
 * @param {BabelNode} node
 * @param {number} scriptOffset
 * @returns {SourceRange}
 */
function getNodeRange(node, scriptOffset) {
    if (typeof node.start !== 'number' || typeof node.end !== 'number') {
        throw new ShopwareSetupTransformError(
            'Missing source range metadata while transforming Shopware setup.',
            scriptOffset,
        );
    }

    return {
        start: node.start,
        end: node.end,
    };
}

/**
 * Parses user setup code with the plugins required by the declared script language.
 *
 * @param {string} script
 * @param {string} lang
 * @param {number} scriptOffset
 * @returns {BabelFile}
 */
function parseScript(script, lang, scriptOffset) {
    const plugins = [
        'importMeta',
    ];

    if (lang === 'ts' || lang === 'tsx') {
        plugins.push('typescript');
    }

    if (lang === 'jsx' || lang === 'tsx') {
        plugins.push('jsx');
    }

    try {
        return parse(script, {
            sourceType: 'module',
            plugins,
            errorRecovery: false,
            allowReturnOutsideFunction: false,
            ranges: true,
        });
    } catch (error) {
        const offset = typeof error.pos === 'number' ? scriptOffset + error.pos : scriptOffset;
        throw new ShopwareSetupTransformError(`Unable to parse Shopware setup script: ${error.message}`, offset);
    }
}

/**
 * Identifies scopes where `await` is no longer top-level for this transform.
 *
 * @param {BabelNode} node
 * @returns {boolean}
 */
function isFunctionNode(node) {
    return [
        'FunctionDeclaration',
        'FunctionExpression',
        'ArrowFunctionExpression',
        'ObjectMethod',
        'ClassMethod',
        'ClassPrivateMethod',
        'TSDeclareFunction',
    ].includes(node.type);
}

/**
 * Small AST walker used to avoid taking a heavier traversal dependency.
 *
 * @param {BabelNode | null | undefined} node
 * @param {(node: BabelNode, ancestors: BabelNode[]) => void} visitor
 * @param {BabelNode[]} [ancestors]
 * @returns {void}
 */
function walk(node, visitor, ancestors = []) {
    if (!node || typeof node.type !== 'string') {
        return;
    }

    visitor(node, ancestors);

    Object.entries(node).forEach(
        ([
            key,
            value,
        ]) => {
            if (
                key === 'loc' ||
                key === 'range' ||
                key === 'leadingComments' ||
                key === 'trailingComments' ||
                key === 'innerComments'
            ) {
                return;
            }

            if (Array.isArray(value)) {
                value.forEach((child) => {
                    if (child && typeof child.type === 'string') {
                        walk(child, visitor, [
                            ...ancestors,
                            node,
                        ]);
                    }
                });
                return;
            }

            if (value && typeof value.type === 'string') {
                walk(value, visitor, [
                    ...ancestors,
                    node,
                ]);
            }
        },
    );
}

/**
 * Rejects syntax that would require native `<script setup>` semantics we do not emulate.
 * Meaning: Unsupported Vue macros, top-level await, and ES module exports.
 *
 * @param {BabelFile} ast
 * @param {number} scriptOffset
 * @param {Set<CallExpression>} topLevelPublicCalls
 * @returns {void}
 */
function assertNoUnsupportedSyntax(ast, scriptOffset, topLevelPublicCalls) {
    walk(ast.program, (node, ancestors) => {
        // Reject unsupported Vue macros:
        //  Vue only treats these calls as compiler macros in supported top-level setup positions.
        //  Shopware setup rejects every occurrence in v1 so nested calls cannot silently become missing runtime imports after lowering.
        if (
            node.type === 'CallExpression' &&
            node.callee.type === 'Identifier' &&
            UNSUPPORTED_VUE_MACROS.has(node.callee.name)
        ) {
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
                    scriptOffset + node.start,
                );
            }
        }

        // Same difference as AwaitExpression: Vue can make setup async, but the Shopware override pipeline is sync.
        if (node.type === 'ForOfStatement' && node.await) {
            const isInsideFunction = ancestors.some(isFunctionNode);

            if (!isInsideFunction) {
                throw new ShopwareSetupTransformError(
                    'Top-level await is not supported inside Shopware setup blocks.',
                    scriptOffset + node.start,
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
 * Restricts v1 runtime state to declarations with simple identifier bindings.
 *
 * @param {BabelNode} idNode
 * @param {number} scriptOffset
 * @returns {void}
 */
function assertIdentifierPattern(idNode, scriptOffset) {
    if (idNode.type !== 'Identifier') {
        // Vue supports object/array patterns and returns every extracted binding. Shopware setup is stricter in v1
        // because public/private override metadata needs stable local binding names before lowering.
        throw new ShopwareSetupTransformError(
            'Shopware setup only supports top-level runtime declarations with identifier bindings in v1.',
            scriptOffset + getNodeRange(idNode, scriptOffset).start,
        );
    }
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
    if (statement.type === 'VariableDeclaration') {
        if (statement.declare) {
            // Vue preserves/hoists TypeScript declare declarations and does not return them from setup. Shopware setup
            // rejects them because this transform only models runtime bindings that can enter public/private state.
            throw new ShopwareSetupTransformError(
                'TypeScript declare declarations are not runtime Shopware setup bindings.',
                scriptOffset + getNodeRange(statement, scriptOffset).start,
            );
        }

        statement.declarations.forEach((declaration) => {
            assertIdentifierPattern(declaration.id, scriptOffset);

            if (isRuntimeInputAlias(declaration, mode)) {
                return;
            }

            addRuntimeBinding(runtimeBindings, runtimeBindingNames, declaration.id.name, declaration.id, scriptOffset);
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

/**
 * Resolves the stable public API key from `swDefinePublic({ key: local })`.
 *
 * @param {ObjectProperty} property
 * @param {number} scriptOffset
 * @returns {PublicEntry['key']}
 */
function getPublicKey(property, scriptOffset) {
    if (property.computed) {
        throw new ShopwareSetupTransformError(
            'Computed keys in swDefinePublic() are intentionally unsupported because transform, lint, and type layers need a stable compile-time key.',
            scriptOffset + getNodeRange(property, scriptOffset).start,
        );
    }

    if (property.key.type === 'Identifier') {
        return {
            type: 'identifier',
            value: property.key.name,
        };
    }

    if (property.key.type === 'StringLiteral') {
        return {
            type: 'string',
            value: property.key.value,
        };
    }

    throw new ShopwareSetupTransformError(
        'swDefinePublic() only supports identifier keys and string-literal keys.',
        scriptOffset + getNodeRange(property.key, scriptOffset).start,
    );
}

/**
 * Enforces the single object-literal shape of `swDefinePublic({...})`.
 *
 * @param {CallExpression} callNode
 * @param {number} scriptOffset
 * @returns {ObjectExpression}
 */
function assertSingleArgument(callNode, scriptOffset) {
    if (callNode.arguments.length !== 1 || callNode.arguments[0].type !== 'ObjectExpression') {
        throw new ShopwareSetupTransformError(
            'swDefinePublic() requires exactly one object-literal argument.',
            scriptOffset + getNodeRange(callNode, scriptOffset).start,
        );
    }

    return callNode.arguments[0];
}

/**
 * Extracts public entries from the top-level `swDefinePublic()` marker.
 *
 * @param {ExpressionStatement & { expression: CallExpression }} statement
 * @param {number} scriptOffset
 * @returns {PublicEntry[]}
 */
function extractPublicMarker(statement, scriptOffset) {
    const callNode = statement.expression;
    const publicObject = assertSingleArgument(callNode, scriptOffset);
    const seenKeys = new Set();

    return publicObject.properties.map((property) => {
        if (property.type === 'SpreadElement') {
            throw new ShopwareSetupTransformError(
                'Spread properties are not supported inside swDefinePublic().',
                scriptOffset + getNodeRange(property, scriptOffset).start,
            );
        }

        if (property.type !== 'ObjectProperty') {
            throw new ShopwareSetupTransformError(
                'swDefinePublic() only supports plain object properties.',
                scriptOffset + getNodeRange(property, scriptOffset).start,
            );
        }

        const key = getPublicKey(property, scriptOffset);

        if (seenKeys.has(key.value)) {
            throw new ShopwareSetupTransformError(
                `Duplicate public Shopware setup binding key "${key.value}".`,
                scriptOffset + getNodeRange(property, scriptOffset).start,
            );
        }

        seenKeys.add(key.value);

        if (property.value.type !== 'Identifier') {
            throw new ShopwareSetupTransformError(
                'swDefinePublic() values must be local identifiers.',
                scriptOffset + getNodeRange(property.value, scriptOffset).start,
            );
        }

        return {
            key,
            localName: property.value.name,
        };
    });
}

/**
 * Captures exact import source text so lowering can preserve import formatting.
 *
 * @param {string} script
 * @param {ImportDeclaration[]} imports
 * @param {number} scriptOffset
 * @returns {ImportBlock[]}
 */
function getImportRangesAndCode(script, imports, scriptOffset) {
    return imports.map((importNode) => {
        const range = getNodeRange(importNode, scriptOffset);

        return {
            ...range,
            code: script.slice(range.start, range.end),
        };
    });
}

/**
 * Removes imports and public markers from the code that moves into the runtime callback.
 *
 * @param {string} script
 * @param {SourceRange[]} ranges
 * @returns {string}
 */
function removeRanges(script, ranges) {
    const sortedRanges = [...ranges].sort((a, b) => a.start - b.start);
    let cursor = 0;
    let output = '';

    sortedRanges.forEach((range) => {
        output += script.slice(cursor, range.start);
        cursor = range.end;
    });

    output += script.slice(cursor);

    return output.trim();
}

/**
 * Prevents user bindings from shadowing generated composable-style helper names.
 *
 * @param {RuntimeBinding[]} bindings
 * @param {'base' | 'override'} mode
 * @param {number} scriptOffset
 * @returns {void}
 */
function assertReservedMacroNames(bindings, mode, scriptOffset) {
    const helpers = mode === 'base' ? BASE_HELPERS : OVERRIDE_HELPERS;

    bindings.forEach((binding) => {
        if (!helpers.has(binding.name)) {
            return;
        }

        throw new ShopwareSetupTransformError(
            `"${binding.name}" is reserved by the Shopware setup transform and must not be declared or imported.`,
            scriptOffset + getNodeRange(binding.node, scriptOffset).start,
        );
    });
}

/**
 * Ensures public entries refer to local runtime bindings, not imports or missing names.
 *
 * @param {PublicEntry[]} publicEntries
 * @param {Set<string>} runtimeBindingNames
 * @param {Set<string>} importedBindings
 * @param {number} scriptOffset
 * @returns {void}
 */
function assertPublicEntries(publicEntries, runtimeBindingNames, importedBindings, scriptOffset) {
    publicEntries.forEach((entry) => {
        if (importedBindings.has(entry.localName)) {
            throw new ShopwareSetupTransformError(
                `Imported binding "${entry.localName}" cannot be exposed with swDefinePublic().`,
                scriptOffset,
            );
        }

        if (!runtimeBindingNames.has(entry.localName)) {
            throw new ShopwareSetupTransformError(
                `swDefinePublic() references unknown local binding "${entry.localName}".`,
                scriptOffset,
            );
        }
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
 * Produces the semantic model used by the lowering step.
 *
 * @param {string} script
 * @param {{ mode: 'base' | 'override', lang: string | null, scriptOffset: number }} options
 * @returns {ShopwareSetupScriptAnalysis}
 */
function analyzeShopwareSetupScript(script, options) {
    const lang = options.lang ?? 'js';
    const mode = options.mode;
    const scriptOffset = options.scriptOffset;
    const ast = parseScript(script, lang, scriptOffset);
    const imports = [];
    const importedBindings = new Set();
    const runtimeBindings = [];
    const runtimeBindingNames = new Set();
    const publicMarkerStatements = [];
    const topLevelPublicCalls = new Set();

    ast.program.body.forEach((statement) => {
        if (statement.type === 'ImportDeclaration') {
            imports.push(statement);
            collectImportBindings(statement, importedBindings);
            return;
        }

        if (isStatementCompilerMacro(statement, 'swDefinePublic')) {
            publicMarkerStatements.push(statement);
            topLevelPublicCalls.add(statement.expression);
            return;
        }

        collectRuntimeBinding(statement, runtimeBindings, runtimeBindingNames, scriptOffset, mode);
    });

    assertNoUnsupportedSyntax(ast, scriptOffset, topLevelPublicCalls);

    if (mode === 'override' && publicMarkerStatements.length > 0) {
        throw new ShopwareSetupTransformError(
            'swDefinePublic() is only valid in base Shopware setup blocks.',
            scriptOffset + getNodeRange(publicMarkerStatements[0], scriptOffset).start,
        );
    }

    if (publicMarkerStatements.length > 1) {
        throw new ShopwareSetupTransformError(
            'Only one swDefinePublic() call is allowed in a base Shopware setup block.',
            scriptOffset + getNodeRange(publicMarkerStatements[1], scriptOffset).start,
        );
    }

    const publicEntries =
        publicMarkerStatements.length > 0 ? extractPublicMarker(publicMarkerStatements[0], scriptOffset) : [];

    assertPublicEntries(publicEntries, runtimeBindingNames, importedBindings, scriptOffset);

    const importedBindingsAsObjects = Array.from(importedBindings).map((name) => ({
        name,
        node: imports.find((importNode) => importNode.specifiers.some((specifier) => specifier.local?.name === name)),
    }));

    assertReservedMacroNames(
        [
            ...runtimeBindings,
            ...importedBindingsAsObjects,
        ],
        mode,
        scriptOffset,
    );

    return {
        imports: getImportRangesAndCode(script, imports, scriptOffset),
        body: removeRanges(script, [
            ...imports.map((importNode) => getNodeRange(importNode, scriptOffset)),
            ...publicMarkerStatements.map((statement) => getNodeRange(statement, scriptOffset)),
        ]),
        runtimeBindings,
        runtimeBindingNames,
        importedBindings,
        publicEntries,
    };
}

module.exports = {
    UNSUPPORTED_VUE_MACROS,
    analyzeShopwareSetupScript,
};
