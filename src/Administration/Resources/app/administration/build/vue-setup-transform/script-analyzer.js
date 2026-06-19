/**
 * @sw-package framework
 */

const { ShopwareSetupTransformError } = require('./utils/transform-error');
const {
    collectTopLevelSetupMacroCalls,
    extractStaticObjectMarker,
    getStatementCompilerMacroCall,
    isCompilerMacroCall,
    isStatementCompilerMacro,
    UNSUPPORTED_VUE_MACROS,
    WRONG_MODE_SW_DEFINE_OVERRIDE_MESSAGE,
    WRONG_MODE_SW_DEFINE_PUBLIC_MESSAGE,
} = require('./script-analyzer/macros');
const {
    getNodeRange,
    parseScript,
    walk,
} = require('./script-analyzer/utils');
const {
    collectImportBindings,
    collectRuntimeBinding,
} = require('./script-analyzer/runtime-bindings');
const {
    assertNoUnsupportedSyntax,
    assertReservedMacroNames,
    assertStaticObjectEntries,
} = require('./script-analyzer/validation');
const { analyzeSetupInputs } = require('./script-analyzer/setup-inputs');

/**
 * @typedef {import('@babel/types').ImportDeclaration} ImportDeclaration
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
 * @property {import('@babel/types').Node} node
 *
 * @typedef {object} ShopwareSetupScriptAnalysis
 * @property {string} source
 * @property {ImportBlock[]} imports
 * @property {SourceRange[]} bodyRemovals
 * @property {SetupInputReplacement[]} setupInputReplacements
 * @property {RuntimeBinding[]} runtimeBindings
 * @property {Set<string>} runtimeBindingNames
 * @property {Set<string>} importedBindings
 * @property {string[]} publicEntries
 * @property {string[]} overrideEntries
 * @property {{ code: string, macroName: 'defineProps' | 'withDefaults', ranges: SourceRange[] } | null} propsMacro
 * @property {{ code: string, macroName: 'defineEmits', ranges: SourceRange[] } | null} emitsMacro
 * @property {{ code: string, macroName: 'defineSlots', ranges: SourceRange[] } | null} slotsMacro
 * @property {{ code: string, macroName: 'defineOptions', ranges: SourceRange[] } | null} optionsMacro
 * @property {Set<string>} overridePrivateBindings
 * @property {string | null} overridePrivateNamespace
 */

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
 * Validates Shopware exposure macros.
 *
 * @param {object} params
 * @param {'base' | 'override'} params.mode
 * @param {number} params.scriptOffset
 * @param {import('@babel/types').ExpressionStatement[]} params.publicMarkerStatements
 * @param {import('@babel/types').ExpressionStatement[]} params.overrideMarkerStatements
 * @returns {void}
 */
function validateShopwareMarkers({
    mode,
    scriptOffset,
    publicMarkerStatements,
    overrideMarkerStatements,
}) {
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

        const defineOptionsCall = getStatementCompilerMacroCall(statement, 'defineOptions');

        if (defineOptionsCall) {
            defineOptionsStatements.push({
                statement,
                call: defineOptionsCall,
            });
            return;
        }

        const defineExposeCall = getStatementCompilerMacroCall(statement, 'defineExpose');

        if (defineExposeCall) {
            defineExposeStatements.push({
                statement,
                call: defineExposeCall,
            });
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

    const {
        setupInputReplacements,
        propsMacro,
        emitsMacro,
        slotsMacro,
        optionsMacro,
    } = analyzeSetupInputs(script, {
        mode,
        scriptOffset,
        definePropsCalls,
        withDefaultsCalls,
        defineEmitsCalls,
        defineExposeStatements,
        defineExposeCalls,
        defineSlotsCalls,
        defineOptionsStatements,
        defineOptionsCalls,
        useSwPropsCalls,
    });

    validateShopwareMarkers({
        mode,
        scriptOffset,
        publicMarkerStatements,
        overrideMarkerStatements,
    });

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
        ...defineOptionsStatements.map((entry) => getNodeRange(entry.statement, scriptOffset)),
        ...publicMarkerStatements.map((statement) => getNodeRange(statement, scriptOffset)),
        ...overrideMarkerStatements.map((statement) => getNodeRange(statement, scriptOffset)),
    ];
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
        overridePrivateBindings: new Set(),
        overridePrivateNamespace: null,
    };
}

module.exports = {
    UNSUPPORTED_VUE_MACROS,
    analyzeShopwareSetupScript,
};
