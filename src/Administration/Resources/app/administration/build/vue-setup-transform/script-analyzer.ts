/**
 * @sw-package framework
 */

/**
 * Builds the normalized script model consumed by the Shopware setup lowerers.
 *
 * This analysis keeps author source ranges for hoisted declarations, validates compiler-macro
 * placement, and classifies top-level runtime bindings before template analysis adds
 * override-private bindings.
 */

import type { CallExpression, ImportDeclaration, Statement } from '@babel/types';
import { ShopwareSetupTransformError } from './utils/transform-error';
import type { ShopwareSetupMode } from './utils/shopware-setup-block';
import {
    type SetupMacroBuckets,
    type StatementMacroCall,
    collectTopLevelSetupMacroCalls,
    extractStaticObjectMarker,
    getStatementCompilerMacroCall,
    isStatementCompilerMacro,
    UNSUPPORTED_VUE_MACROS,
    WRONG_MODE_SW_DEFINE_OVERRIDE_MESSAGE,
    WRONG_MODE_SW_DEFINE_PUBLIC_MESSAGE,
} from './script-analyzer/macros';
import { type SourceRange, getNodeRange, parseScript } from './script-analyzer/utils';
import { type RuntimeBinding, collectImportBindings, collectRuntimeBinding } from './script-analyzer/runtime-bindings';
import {
    assertNoUnsupportedSyntax,
    assertReservedMacroNames,
    assertStaticObjectEntries,
} from './script-analyzer/validation';
import { assertHoistedMacroArgumentsDoNotUseLocalSetup } from './script-analyzer/hoisted-macro-arguments';
import {
    analyzeSetupInputs,
    type DefineExposeStatement,
    type DefineOptionsStatement,
    type SetupInputReplacement,
    type SetupMacroSummary,
} from './script-analyzer/setup-inputs';

type ImportBlock = SourceRange & { code: string };
type TypeDeclarationBlock = SourceRange & { code: string };
type AnalyzerOptions = {
    mode: ShopwareSetupMode;
    lang: string | null;
    scriptOffset: number;
};

type StatementWithCall = {
    statement: Statement;
    call: CallExpression;
};

/**
 * Describes one parsed Shopware setup script after macro validation and binding classification.
 *
 * Lowering uses this shape as its only script-side input: imports/type declarations are hoisted,
 * body ranges are removed or replaced, runtime bindings become setup state, and override template
 * analysis later fills the private binding namespace fields.
 */
type ShopwareSetupScriptAnalysis = {
    source: string;
    imports: ImportBlock[];
    typeDeclarations: TypeDeclarationBlock[];
    bodyRemovals: SourceRange[];
    setupInputReplacements: SetupInputReplacement[];
    runtimeBindings: RuntimeBinding[];
    runtimeBindingNames: Set<string>;
    runtimeInputAliasNames: Set<string>;
    importedBindings: Set<string>;
    publicEntries: string[];
    overrideEntries: string[];
    propsMacro: SetupMacroSummary | null;
    emitsMacro: SetupMacroSummary | null;
    slotsMacro: SetupMacroSummary | null;
    optionsMacro: SetupMacroSummary | null;
    overridePrivateBindings: Set<string>;
    overridePrivateNamespace: string | null;
};

/**
 * Captures exact import source text so lowering can preserve import formatting.
 */
function getImportRangesAndCode(script: string, imports: ImportDeclaration[], scriptOffset: number): ImportBlock[] {
    return imports.map((importNode) => {
        const range = getNodeRange(importNode, scriptOffset);

        return {
            ...range,
            code: script.slice(range.start, range.end),
        };
    });
}

/**
 * Captures type-only declarations that hoisted Vue macros may reference.
 */
function getTypeDeclarationRangesAndCode(
    script: string,
    typeDeclarations: Statement[],
    scriptOffset: number,
): TypeDeclarationBlock[] {
    return typeDeclarations.map((declaration) => {
        const range = getNodeRange(declaration, scriptOffset);

        return {
            ...range,
            code: script.slice(range.start, range.end),
        };
    });
}

/**
 * Type-only declarations have no runtime output. They are hoisted to the generated script root
 * (and removed from the setup callback) so hoisted macros can still resolve their names, matching
 * how Vue keeps them at the module root. This covers type aliases and interfaces as well as ambient
 * `declare` statements, which describe runtime values provided from elsewhere and are invalid inside
 * the callback function body.
 */
function isHoistableTypeDeclaration(statement: Statement): boolean {
    // e.g. `interface Props { ... }`, `type Emits = { ... }`, `declare const injected: number;`
    return (
        statement.type === 'TSInterfaceDeclaration' ||
        statement.type === 'TSTypeAliasDeclaration' ||
        Boolean((statement as Statement & { declare?: boolean }).declare)
    );
}

/**
 * Validates Shopware exposure macros.
 */
function validateShopwareMarkers({
    mode,
    scriptOffset,
    publicMarkerStatements,
    overrideMarkerStatements,
}: {
    mode: ShopwareSetupMode;
    scriptOffset: number;
    publicMarkerStatements: StatementMacroCall[];
    overrideMarkerStatements: StatementMacroCall[];
}): void {
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
 */
function analyzeShopwareSetupScript(script: string, options: AnalyzerOptions): ShopwareSetupScriptAnalysis {
    const lang = options.lang ?? 'js';
    const mode = options.mode;
    const scriptOffset = options.scriptOffset;
    const ast = parseScript(script, lang, scriptOffset);
    const imports: ImportDeclaration[] = [];
    const typeDeclarations: Statement[] = [];
    const importedBindings = new Set<string>();
    const runtimeBindings: RuntimeBinding[] = [];
    const runtimeBindingNames = new Set<string>();
    const runtimeInputAliasNames = new Set<string>();
    const publicMarkerStatements: StatementMacroCall[] = [];
    const overrideMarkerStatements: StatementMacroCall[] = [];
    const definePropsCalls: CallExpression[] = [];
    const defineEmitsCalls: CallExpression[] = [];
    const defineExposeStatements: (DefineExposeStatement & StatementWithCall)[] = [];
    const defineSlotsCalls: CallExpression[] = [];
    const defineOptionsStatements: (DefineOptionsStatement & StatementWithCall)[] = [];
    const withDefaultsCalls: CallExpression[] = [];
    const topLevelPublicCalls = new Set<CallExpression>();
    const topLevelOverrideCalls = new Set<CallExpression>();
    const topLevelUnsupportedMacroCalls = new Set<CallExpression>();

    ast.program.body.forEach((statement) => {
        collectTopLevelSetupMacroCalls(statement, {
            definePropsCalls,
            defineEmitsCalls,
            defineSlotsCalls,
            withDefaultsCalls,
            topLevelUnsupportedMacroCalls,
        } satisfies SetupMacroBuckets);

        if (statement.type === 'ImportDeclaration') {
            imports.push(statement);
            collectImportBindings(statement, importedBindings);
            return;
        }

        if (isHoistableTypeDeclaration(statement)) {
            typeDeclarations.push(statement);
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

        collectRuntimeBinding(statement, runtimeBindings, runtimeBindingNames, runtimeInputAliasNames, scriptOffset, mode);
    });

    assertNoUnsupportedSyntax(
        ast,
        mode,
        scriptOffset,
        topLevelPublicCalls,
        topLevelOverrideCalls,
        topLevelUnsupportedMacroCalls,
    );

    assertHoistedMacroArgumentsDoNotUseLocalSetup({
        scriptOffset,
        runtimeBindingNames,
        macroCalls: [
            ...definePropsCalls.map((call) => ({
                name: 'defineProps',
                call,
            })),
            ...withDefaultsCalls.map((call) => ({
                name: 'withDefaults',
                call,
            })),
            ...defineEmitsCalls.map((call) => ({
                name: 'defineEmits',
                call,
            })),
            ...defineOptionsStatements.map(({ call }) => ({
                name: 'defineOptions',
                call,
            })),
        ],
    });

    const { setupInputReplacements, propsMacro, emitsMacro, slotsMacro, optionsMacro } = analyzeSetupInputs(script, {
        mode,
        scriptOffset,
        definePropsCalls,
        withDefaultsCalls,
        defineEmitsCalls,
        defineExposeStatements,
        defineSlotsCalls,
        defineOptionsStatements,
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

    const importedBindingsAsObjects: RuntimeBinding[] = Array.from(importedBindings).flatMap((name) => {
        const node = imports.find((importNode) => importNode.specifiers.some((specifier) => specifier.local?.name === name));

        return node
            ? [
                  {
                      name,
                      node,
                  },
              ]
            : [];
    });

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
        ...typeDeclarations.map((declaration) => getNodeRange(declaration, scriptOffset)),
        ...defineOptionsStatements.map((entry) => getNodeRange(entry.statement, scriptOffset)),
        ...publicMarkerStatements.map((statement) => getNodeRange(statement, scriptOffset)),
        ...overrideMarkerStatements.map((statement) => getNodeRange(statement, scriptOffset)),
    ];
    return {
        source: script,
        imports: getImportRangesAndCode(script, imports, scriptOffset),
        typeDeclarations: getTypeDeclarationRangesAndCode(script, typeDeclarations, scriptOffset),
        bodyRemovals,
        setupInputReplacements,
        runtimeBindings,
        runtimeBindingNames,
        runtimeInputAliasNames,
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

export { type ImportBlock, type ShopwareSetupScriptAnalysis, UNSUPPORTED_VUE_MACROS, analyzeShopwareSetupScript };
