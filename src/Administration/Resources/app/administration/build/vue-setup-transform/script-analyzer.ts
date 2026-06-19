/**
 * @sw-package framework
 */

import type {
    CallExpression,
    Expression,
    ImportDeclaration,
    Statement,
    VariableDeclaration,
} from '@babel/types';
import { ShopwareSetupTransformError } from './utils/transform-error';
import type { ShopwareSetupMode } from './utils/shopware-setup-block';
import {
    type SetupMacroBuckets,
    type StatementMacroCall,
    collectTopLevelSetupMacroCalls,
    extractStaticObjectMarker,
    getStatementCompilerMacroCall,
    isCompilerMacroCall,
    isStatementCompilerMacro,
    UNSUPPORTED_VUE_MACROS,
    WRONG_MODE_SW_DEFINE_OVERRIDE_MESSAGE,
    WRONG_MODE_SW_DEFINE_PUBLIC_MESSAGE,
} from './script-analyzer/macros';
import {
    type SourceRange,
    getNodeRange,
    parseScript,
    walk,
} from './script-analyzer/utils';
import {
    type RuntimeBinding,
    collectImportBindings,
    collectRuntimeBinding,
} from './script-analyzer/runtime-bindings';
import {
    assertNoUnsupportedSyntax,
    assertReservedMacroNames,
    assertStaticObjectEntries,
} from './script-analyzer/validation';
import {
    analyzeSetupInputs,
    type DefineExposeStatement,
    type DefineOptionsStatement,
    type SetupInputReplacement,
    type SetupMacroSummary,
} from './script-analyzer/setup-inputs';

type ImportBlock = SourceRange & { code: string };
type TypeDeclarationBlock = SourceRange & { code: string };
type HoistedRuntimeDeclarationBlock = SourceRange & { code: string };
type AnalyzerOptions = {
    mode: ShopwareSetupMode,
    lang: string | null,
    scriptOffset: number,
};

type StatementWithCall = {
    statement: Statement,
    call: CallExpression,
};

type ShopwareSetupScriptAnalysis = {
    source: string,
    imports: ImportBlock[],
    typeDeclarations: TypeDeclarationBlock[],
    hoistedRuntimeDeclarations: HoistedRuntimeDeclarationBlock[],
    hoistedRuntimeBindingNames: string[],
    bodyRemovals: SourceRange[],
    setupInputReplacements: SetupInputReplacement[],
    runtimeBindings: RuntimeBinding[],
    runtimeBindingNames: Set<string>,
    importedBindings: Set<string>,
    publicEntries: string[],
    overrideEntries: string[],
    propsMacro: SetupMacroSummary | null,
    emitsMacro: SetupMacroSummary | null,
    slotsMacro: SetupMacroSummary | null,
    optionsMacro: SetupMacroSummary | null,
    overridePrivateBindings: Set<string>,
    overridePrivateNamespace: string | null,
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
 * Captures static runtime declarations that Vue compiler macros may reference after hoisting.
 */
function getHoistedRuntimeDeclarationRangesAndCode(
    script: string,
    declarations: VariableDeclaration[],
    scriptOffset: number,
): HoistedRuntimeDeclarationBlock[] {
    return declarations.map((declaration) => {
        const range = getNodeRange(declaration, scriptOffset);

        return {
            ...range,
            code: script.slice(range.start, range.end),
        };
    });
}

/**
 * Type aliases and interfaces have no runtime output, but base setup macros are hoisted
 * to the generated script root and may need these names for type resolution.
 */
function isHoistableTypeDeclaration(statement: Statement): boolean {
    return statement.type === 'TSInterfaceDeclaration' || statement.type === 'TSTypeAliasDeclaration';
}

/**
 * Mirrors the small set of local constants Vue can safely hoist for compiler macro arguments.
 */
function isStaticPrimitiveExpression(expression: Expression | null | undefined): boolean {
    if (!expression) {
        return false;
    }

    if (
        expression.type === 'StringLiteral' ||
        expression.type === 'NumericLiteral' ||
        expression.type === 'BooleanLiteral' ||
        expression.type === 'NullLiteral' ||
        expression.type === 'BigIntLiteral'
    ) {
        return true;
    }

    return expression.type === 'TemplateLiteral' && expression.expressions.length === 0;
}

/**
 * Static const declarations can be shared from module scope without changing per-instance behavior.
 */
function isHoistableRuntimeDeclaration(statement: Statement): statement is VariableDeclaration {
    return (
        statement.type === 'VariableDeclaration' &&
        statement.kind === 'const' &&
        statement.declarations.length > 0 &&
        statement.declarations.every((declaration) => (
            declaration.id.type === 'Identifier' &&
            isStaticPrimitiveExpression(declaration.init)
        ))
    );
}

/**
 * Returns local names declared by runtime declarations moved to the script root.
 */
function getHoistedRuntimeBindingNames(declarations: VariableDeclaration[]): string[] {
    return declarations.flatMap((declaration) =>
        declaration.declarations.flatMap((declarator) => (
            declarator.id.type === 'Identifier' ? [declarator.id.name] : []
        )),
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
    mode: ShopwareSetupMode,
    scriptOffset: number,
    publicMarkerStatements: StatementMacroCall[],
    overrideMarkerStatements: StatementMacroCall[],
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
    const hoistedRuntimeDeclarations: VariableDeclaration[] = [];
    const importedBindings = new Set<string>();
    const runtimeBindings: RuntimeBinding[] = [];
    const runtimeBindingNames = new Set<string>();
    const publicMarkerStatements: StatementMacroCall[] = [];
    const overrideMarkerStatements: StatementMacroCall[] = [];
    const definePropsCalls: CallExpression[] = [];
    const defineEmitsCalls: CallExpression[] = [];
    const defineExposeCalls: CallExpression[] = [];
    const defineExposeStatements: (DefineExposeStatement & StatementWithCall)[] = [];
    const defineSlotsCalls: CallExpression[] = [];
    const defineOptionsCalls: CallExpression[] = [];
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

        if (isHoistableRuntimeDeclaration(statement)) {
            hoistedRuntimeDeclarations.push(statement);
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
    });

    assertNoUnsupportedSyntax(
        ast,
        mode,
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
        ...hoistedRuntimeDeclarations.map((declaration) => getNodeRange(declaration, scriptOffset)),
        ...defineOptionsStatements.map((entry) => getNodeRange(entry.statement, scriptOffset)),
        ...publicMarkerStatements.map((statement) => getNodeRange(statement, scriptOffset)),
        ...overrideMarkerStatements.map((statement) => getNodeRange(statement, scriptOffset)),
    ];
    return {
        source: script,
        imports: getImportRangesAndCode(script, imports, scriptOffset),
        typeDeclarations: getTypeDeclarationRangesAndCode(script, typeDeclarations, scriptOffset),
        hoistedRuntimeDeclarations: getHoistedRuntimeDeclarationRangesAndCode(script, hoistedRuntimeDeclarations, scriptOffset),
        hoistedRuntimeBindingNames: getHoistedRuntimeBindingNames(hoistedRuntimeDeclarations),
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

export {
    type ImportBlock,
    type ShopwareSetupScriptAnalysis,
    UNSUPPORTED_VUE_MACROS,
    analyzeShopwareSetupScript,
};
