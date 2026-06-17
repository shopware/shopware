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
 * @typedef {SourceRange & { kind: 'props' | 'emits' | 'slots' | 'expose' }} SetupInputReplacement
 *
 * @typedef {object} RuntimeBinding
 * @property {string} name
 * @property {BabelNode} node
 *
 * @typedef {object} PublicEntry
 * @property {{ type: 'identifier' | 'string', value: string }} key
 * @property {string} localName
 *
 * @typedef {PublicEntry} OverrideEntry
 *
 * @typedef {object} ShopwareSetupScriptAnalysis
 * @property {string} source
 * @property {ImportBlock[]} imports
 * @property {SourceRange[]} bodyRemovals
 * @property {SetupInputReplacement[]} setupInputReplacements
 * @property {RuntimeBinding[]} runtimeBindings
 * @property {Set<string>} runtimeBindingNames
 * @property {Set<string>} importedBindings
 * @property {PublicEntry[]} publicEntries
 * @property {OverrideEntry[]} overrideEntries
 * @property {{ code: string, macroName: 'defineProps' | 'withDefaults', ranges: SourceRange[] } | null} propsMacro
 * @property {{ code: string, macroName: 'defineEmits', ranges: SourceRange[] } | null} emitsMacro
 * @property {{ code: string, macroName: 'defineSlots', ranges: SourceRange[] } | null} slotsMacro
 * @property {{ code: string, macroName: 'defineOptions', ranges: SourceRange[] } | null} optionsMacro
 * @property {Map<string, string>} overridePrivateAliases
 */

const UNSUPPORTED_VUE_MACROS = new Set([
    'defineModel',
]);

const BASE_HELPERS = new Set([
    'swDefinePublic',
    'swDefineOverride',
    'defineEmits',
    'defineExpose',
    'defineOptions',
    'defineSlots',
    'withDefaults',
    'useSwProps',
    'useSwContext',
]);

const OVERRIDE_HELPERS = new Set([
    'swDefinePublic',
    'swDefineOverride',
    'defineEmits',
    'defineExpose',
    'defineOptions',
    'defineSlots',
    'useSwPreviousState',
    'withDefaults',
    'useSwProps',
    'useSwContext',
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

const RESERVED_OVERRIDE_PRIVATE_PREFIX = '__swOverride_';

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
 * @param {Set<CallExpression>} topLevelOverrideCalls
 * @param {Set<CallExpression>} topLevelUnsupportedMacroCalls
 * @returns {void}
 */
function assertNoUnsupportedSyntax(
    ast,
    scriptOffset,
    topLevelPublicCalls,
    topLevelOverrideCalls,
    topLevelUnsupportedMacroCalls,
) {
    walk(ast.program, (node, ancestors) => {
        // Reject unsupported Vue macros:
        //  Vue only treats these calls as compiler macros in supported top-level setup positions.
        //  Nested calls are left untouched like compiler-sfc does.
        if (topLevelUnsupportedMacroCalls.has(node)) {
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

        // Ensure swDefineOverride() is only called at top level
        if (
            node.type === 'CallExpression' &&
            node.callee.type === 'Identifier' &&
            node.callee.name === 'swDefineOverride' &&
            !topLevelOverrideCalls.has(node)
        ) {
            throw new ShopwareSetupTransformError(
                'swDefineOverride() must be called once at the top level of an override Shopware setup block.',
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

/**
 * Resolves the stable public API key from `swDefinePublic({ key: local })`.
 *
 * @param {ObjectProperty} property
 * @param {number} scriptOffset
 * @param {'swDefinePublic' | 'swDefineOverride'} macroName
 * @returns {PublicEntry['key']}
 */
function getStaticMacroKey(property, scriptOffset, macroName) {
    if (property.computed) {
        throw new ShopwareSetupTransformError(
            `Computed keys in ${macroName}() are intentionally unsupported because transform, lint, and type layers need a stable compile-time key.`,
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
        `${macroName}() only supports identifier keys and string-literal keys.`,
        scriptOffset + getNodeRange(property.key, scriptOffset).start,
    );
}

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
 * Extracts public entries from the top-level `swDefinePublic()` marker.
 *
 * @param {ExpressionStatement & { expression: CallExpression }} statement
 * @param {number} scriptOffset
 * @param {'swDefinePublic' | 'swDefineOverride'} macroName
 * @param {'public' | 'override'} entryType
 * @returns {PublicEntry[]}
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

        const key = getStaticMacroKey(property, scriptOffset, macroName);

        if (seenKeys.has(key.value)) {
            throw new ShopwareSetupTransformError(
                `Duplicate ${entryType} Shopware setup binding key "${key.value}".`,
                scriptOffset + getNodeRange(property, scriptOffset).start,
            );
        }

        seenKeys.add(key.value);

        if (property.value.type !== 'Identifier') {
            throw new ShopwareSetupTransformError(
                `${macroName}() values must be local identifiers.`,
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
        if (helpers.has(binding.name)) {
            throw new ShopwareSetupTransformError(
                `"${binding.name}" is reserved by the Shopware setup transform and must not be declared or imported.`,
                scriptOffset + getNodeRange(binding.node, scriptOffset).start,
            );
        }

        if (binding.name.startsWith(RESERVED_OVERRIDE_PRIVATE_PREFIX)) {
            throw new ShopwareSetupTransformError(
                `"${binding.name}" uses the reserved Shopware override-private prefix "__swOverride_" and must not be declared or imported.`,
                scriptOffset + getNodeRange(binding.node, scriptOffset).start,
            );
        }
    });
}

/**
 * Ensures public entries refer to local runtime bindings, not imports or missing names.
 *
 * @param {PublicEntry[]} publicEntries
 * @param {Set<string>} runtimeBindingNames
 * @param {Set<string>} importedBindings
 * @param {number} scriptOffset
 * @param {'swDefinePublic' | 'swDefineOverride'} macroName
 * @returns {void}
 */
function assertStaticObjectEntries(publicEntries, runtimeBindingNames, importedBindings, scriptOffset, macroName) {
    publicEntries.forEach((entry) => {
        if (importedBindings.has(entry.localName)) {
            throw new ShopwareSetupTransformError(
                `Imported binding "${entry.localName}" cannot be exposed with ${macroName}().`,
                scriptOffset,
            );
        }

        if (!runtimeBindingNames.has(entry.localName)) {
            throw new ShopwareSetupTransformError(
                `${macroName}() references unknown local binding "${entry.localName}".`,
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
 * Returns the expression Vue treats as the compiler macro call through transparent TypeScript wrappers.
 * Example: `defineProps<Props>() as Props` is collected as the inner `defineProps<Props>()` call while the
 * replacement range still preserves `as Props` around the generated setup input.
 *
 * @param {BabelNode | null | undefined} node
 * @returns {BabelNode | null | undefined}
 */
function unwrapTransparentMacroExpression(node) {
    if (
        node?.type === 'TSAsExpression' ||
        node?.type === 'TSSatisfiesExpression' ||
        node?.type === 'TSTypeAssertion' ||
        node?.type === 'TSNonNullExpression' ||
        node?.type === 'ParenthesizedExpression'
    ) {
        return unwrapTransparentMacroExpression(node.expression);
    }

    return node;
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
 * Checks whether `inner` is fully covered by `outer`.
 *
 * @param {SourceRange} outer
 * @param {SourceRange} inner
 * @returns {boolean}
 */
function containsRange(outer, inner) {
    return outer.start <= inner.start && inner.end <= outer.end;
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
    const overrideMarkerStatements = [];
    const definePropsCalls = [];
    const defineEmitsCalls = [];
    const defineExposeCalls = [];
    const defineExposeStatements = [];
    const defineSlotsCalls = [];
    const defineOptionsCalls = [];
    const defineOptionsStatements = [];
    const withDefaultsCalls = [];
    const useSwPropsCalls = [];
    const topLevelPublicCalls = new Set();
    const topLevelOverrideCalls = new Set();
    const topLevelUnsupportedMacroCalls = new Set();

    ast.program.body.forEach((statement) => {
        collectTopLevelSetupMacroCalls(statement, {
            definePropsCalls,
            defineEmitsCalls,
            defineSlotsCalls,
            withDefaultsCalls,
            topLevelUnsupportedMacroCalls,
        });

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

        if (isStatementCompilerMacro(statement, 'swDefineOverride')) {
            overrideMarkerStatements.push(statement);
            topLevelOverrideCalls.add(statement.expression);
            return;
        }

        if (isStatementCompilerMacro(statement, 'defineOptions')) {
            defineOptionsStatements.push(statement);
            return;
        }

        if (isStatementCompilerMacro(statement, 'defineExpose')) {
            defineExposeStatements.push(statement);
            return;
        }

        collectRuntimeBinding(statement, runtimeBindings, runtimeBindingNames, scriptOffset, mode);
    });

    walk(ast.program, (node) => {
        if (isCompilerMacroCall(node, 'defineExpose')) {
            defineExposeCalls.push(node);
        }

        if (isCompilerMacroCall(node, 'defineOptions')) {
            defineOptionsCalls.push(node);
        }

        if (mode === 'base' && isCompilerMacroCall(node, 'useSwProps')) {
            useSwPropsCalls.push(node);
        }
    });

    assertNoUnsupportedSyntax(
        ast,
        scriptOffset,
        topLevelPublicCalls,
        topLevelOverrideCalls,
        topLevelUnsupportedMacroCalls,
    );

    const withDefaultsRanges = withDefaultsCalls.map((call) => getNodeRange(call, scriptOffset));
    const standaloneDefinePropsCalls = definePropsCalls.filter((call) => {
        const definePropsRange = getNodeRange(call, scriptOffset);

        return !withDefaultsRanges.some((withDefaultsRange) => containsRange(withDefaultsRange, definePropsRange));
    });
    const propsMacroCalls = [
        ...withDefaultsCalls,
        ...standaloneDefinePropsCalls,
    ].sort((a, b) => getNodeRange(a, scriptOffset).start - getNodeRange(b, scriptOffset).start);

    if (mode === 'override' && propsMacroCalls.length > 0) {
        const firstPropsMacro = propsMacroCalls[0];
        const macroName = isWithDefaultsCall(firstPropsMacro) ? 'withDefaults' : 'defineProps';

        throw new ShopwareSetupTransformError(
            `${macroName}() is only supported in base Shopware setup blocks.`,
            scriptOffset + getNodeRange(firstPropsMacro, scriptOffset).start,
        );
    }

    if (propsMacroCalls.length > 1) {
        throw new ShopwareSetupTransformError(
            'Only one props declaration macro is allowed in a base Shopware setup block.',
            scriptOffset + getNodeRange(propsMacroCalls[1], scriptOffset).start,
        );
    }

    const emitsMacroCalls = [...defineEmitsCalls].sort(
        (a, b) => getNodeRange(a, scriptOffset).start - getNodeRange(b, scriptOffset).start,
    );

    if (mode === 'override' && emitsMacroCalls.length > 0) {
        throw new ShopwareSetupTransformError(
            'defineEmits() is only supported in base Shopware setup blocks.',
            scriptOffset + getNodeRange(emitsMacroCalls[0], scriptOffset).start,
        );
    }

    if (emitsMacroCalls.length > 1) {
        throw new ShopwareSetupTransformError(
            'Only one defineEmits() call is allowed in a base Shopware setup block.',
            scriptOffset + getNodeRange(emitsMacroCalls[1], scriptOffset).start,
        );
    }

    const topLevelDefineExposeCalls = new Set(defineExposeStatements.map((statement) => statement.expression));

    defineExposeCalls.forEach((call) => {
        if (topLevelDefineExposeCalls.has(call)) {
            return;
        }

        throw new ShopwareSetupTransformError(
            'defineExpose() must be called once at the top level of a base Shopware setup block.',
            scriptOffset + getNodeRange(call, scriptOffset).start,
        );
    });

    if (mode === 'override' && defineExposeStatements.length > 0) {
        throw new ShopwareSetupTransformError(
            'defineExpose() is only supported in base Shopware setup blocks.',
            scriptOffset + getNodeRange(defineExposeStatements[0], scriptOffset).start,
        );
    }

    if (defineExposeStatements.length > 1) {
        throw new ShopwareSetupTransformError(
            'Only one defineExpose() call is allowed in a base Shopware setup block.',
            scriptOffset + getNodeRange(defineExposeStatements[1], scriptOffset).start,
        );
    }

    const slotsMacroCalls = [...defineSlotsCalls].sort(
        (a, b) => getNodeRange(a, scriptOffset).start - getNodeRange(b, scriptOffset).start,
    );

    if (mode === 'override' && slotsMacroCalls.length > 0) {
        throw new ShopwareSetupTransformError(
            'defineSlots() is only supported in base Shopware setup blocks.',
            scriptOffset + getNodeRange(slotsMacroCalls[0], scriptOffset).start,
        );
    }

    if (slotsMacroCalls.length > 1) {
        throw new ShopwareSetupTransformError(
            'Only one defineSlots() call is allowed in a base Shopware setup block.',
            scriptOffset + getNodeRange(slotsMacroCalls[1], scriptOffset).start,
        );
    }

    const topLevelDefineOptionsCalls = new Set(defineOptionsStatements.map((statement) => statement.expression));

    defineOptionsCalls.forEach((call) => {
        if (topLevelDefineOptionsCalls.has(call)) {
            return;
        }

        throw new ShopwareSetupTransformError(
            'defineOptions() must be called once at the top level of a base Shopware setup block.',
            scriptOffset + getNodeRange(call, scriptOffset).start,
        );
    });

    if (mode === 'override' && defineOptionsStatements.length > 0) {
        throw new ShopwareSetupTransformError(
            'defineOptions() is only supported in base Shopware setup blocks.',
            scriptOffset + getNodeRange(defineOptionsStatements[0], scriptOffset).start,
        );
    }

    if (defineOptionsStatements.length > 1) {
        throw new ShopwareSetupTransformError(
            'Only one defineOptions() call is allowed in a base Shopware setup block.',
            scriptOffset + getNodeRange(defineOptionsStatements[1], scriptOffset).start,
        );
    }

    if (mode === 'override' && publicMarkerStatements.length > 0) {
        throw new ShopwareSetupTransformError(
            WRONG_MODE_SW_DEFINE_PUBLIC_MESSAGE,
            scriptOffset + getNodeRange(publicMarkerStatements[0], scriptOffset).start,
        );
    }

    if (mode === 'base' && overrideMarkerStatements.length > 0) {
        throw new ShopwareSetupTransformError(
            WRONG_MODE_SW_DEFINE_OVERRIDE_MESSAGE,
            scriptOffset + getNodeRange(overrideMarkerStatements[0], scriptOffset).start,
        );
    }

    if (publicMarkerStatements.length > 1) {
        throw new ShopwareSetupTransformError(
            'Only one swDefinePublic() call is allowed in a base Shopware setup block.',
            scriptOffset + getNodeRange(publicMarkerStatements[1], scriptOffset).start,
        );
    }

    if (overrideMarkerStatements.length > 1) {
        throw new ShopwareSetupTransformError(
            'Only one swDefineOverride() call is allowed in an override Shopware setup block.',
            scriptOffset + getNodeRange(overrideMarkerStatements[1], scriptOffset).start,
        );
    }

    if (mode === 'override' && overrideMarkerStatements.length !== 1) {
        throw new ShopwareSetupTransformError(
            'swDefineOverride() must be called exactly once at the top level of an override Shopware setup block.',
            scriptOffset,
        );
    }

    const publicEntries =
        publicMarkerStatements.length > 0
            ? extractStaticObjectMarker(publicMarkerStatements[0], scriptOffset, 'swDefinePublic', 'public')
            : [];
    const overrideEntries =
        overrideMarkerStatements.length > 0
            ? extractStaticObjectMarker(overrideMarkerStatements[0], scriptOffset, 'swDefineOverride', 'override')
            : [];

    assertStaticObjectEntries(publicEntries, runtimeBindingNames, importedBindings, scriptOffset, 'swDefinePublic');
    assertStaticObjectEntries(overrideEntries, runtimeBindingNames, importedBindings, scriptOffset, 'swDefineOverride');

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

    const bodyRemovals = [
        ...imports.map((importNode) => getNodeRange(importNode, scriptOffset)),
        ...defineOptionsStatements.map((statement) => getNodeRange(statement, scriptOffset)),
        ...publicMarkerStatements.map((statement) => getNodeRange(statement, scriptOffset)),
        ...overrideMarkerStatements.map((statement) => getNodeRange(statement, scriptOffset)),
    ];
    const setupInputReplacements = [
        ...propsMacroCalls.map((call) => ({
            ...getNodeRange(call, scriptOffset),
            kind: 'props',
        })),
        ...useSwPropsCalls.map((call) => ({
            ...getNodeRange(call, scriptOffset),
            kind: 'props',
        })),
        ...emitsMacroCalls.map((call) => ({
            ...getNodeRange(call, scriptOffset),
            kind: 'emits',
        })),
        ...defineExposeStatements.map((statement) => ({
            ...getNodeRange(statement.expression.callee, scriptOffset),
            kind: 'expose',
        })),
        ...slotsMacroCalls.map((call) => ({
            ...getNodeRange(call, scriptOffset),
            kind: 'slots',
        })),
    ];
    const propsMacroCall = propsMacroCalls[0];
    const emitsMacroCall = emitsMacroCalls[0];
    const slotsMacroCall = slotsMacroCalls[0];
    const optionsMacroStatement = defineOptionsStatements[0];
    const propsMacroRange = propsMacroCall ? getNodeRange(propsMacroCall, scriptOffset) : null;
    const emitsMacroRange = emitsMacroCall ? getNodeRange(emitsMacroCall, scriptOffset) : null;
    const slotsMacroRange = slotsMacroCall ? getNodeRange(slotsMacroCall, scriptOffset) : null;
    const optionsMacroRange = optionsMacroStatement ? getNodeRange(optionsMacroStatement, scriptOffset) : null;
    const propsMacro = propsMacroRange
        ? {
              code: script.slice(propsMacroRange.start, propsMacroRange.end),
              macroName: isWithDefaultsCall(propsMacroCall) ? 'withDefaults' : 'defineProps',
              ranges: [
                  propsMacroRange,
              ],
          }
        : null;
    const emitsMacro = emitsMacroRange
        ? {
              code: script.slice(emitsMacroRange.start, emitsMacroRange.end),
              macroName: 'defineEmits',
              ranges: [
                  emitsMacroRange,
              ],
          }
        : null;
    const slotsMacro = slotsMacroRange
        ? {
              code: script.slice(slotsMacroRange.start, slotsMacroRange.end),
              macroName: 'defineSlots',
              ranges: [
                  slotsMacroRange,
              ],
          }
        : null;
    const optionsMacro = optionsMacroRange
        ? {
              code: script.slice(optionsMacroRange.start, optionsMacroRange.end),
              macroName: 'defineOptions',
              ranges: [
                  optionsMacroRange,
              ],
          }
        : null;

    return {
        source: script,
        imports: getImportRangesAndCode(script, imports, scriptOffset),
        bodyRemovals,
        setupInputReplacements,
        runtimeBindings,
        runtimeBindingNames,
        importedBindings,
        publicEntries,
        overrideEntries,
        propsMacro,
        emitsMacro,
        slotsMacro,
        optionsMacro,
        overridePrivateAliases: new Map(),
    };
}

module.exports = {
    UNSUPPORTED_VUE_MACROS,
    analyzeShopwareSetupScript,
};
