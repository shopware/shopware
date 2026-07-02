/**
 * @sw-package framework
 */

import type { CallExpression, Identifier, ImportDeclaration, Node as BabelNode, Statement } from '@babel/types';
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
import { type SourceRange, getNodeRange, parseScript, walk } from './script-analyzer/utils';
import { type RuntimeBinding, collectImportBindings, collectRuntimeBinding } from './script-analyzer/runtime-bindings';
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
type AnalyzerOptions = {
    mode: ShopwareSetupMode;
    lang: string | null;
    scriptOffset: number;
};

type StatementWithCall = {
    statement: Statement;
    call: CallExpression;
};

type ShopwareSetupScriptAnalysis = {
    source: string;
    imports: ImportBlock[];
    typeDeclarations: TypeDeclarationBlock[];
    bodyRemovals: SourceRange[];
    setupInputReplacements: SetupInputReplacement[];
    runtimeBindings: RuntimeBinding[];
    runtimeBindingNames: Set<string>;
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
 * Type aliases and interfaces have no runtime output, but base setup macros are hoisted
 * to the generated script root and may need these names for type resolution.
 */
function isHoistableTypeDeclaration(statement: Statement): boolean {
    return statement.type === 'TSInterfaceDeclaration' || statement.type === 'TSTypeAliasDeclaration';
}

/**
 * Adds all identifiers declared by a binding pattern to a set.
 */
function collectBindingPatternNames(pattern: BabelNode | null | undefined, names: Set<string>): void {
    if (!pattern) {
        return;
    }

    if (pattern.type === 'Identifier') {
        names.add(pattern.name);
        return;
    }

    if (pattern.type === 'RestElement') {
        collectBindingPatternNames(pattern.argument, names);
        return;
    }

    if (pattern.type === 'AssignmentPattern') {
        collectBindingPatternNames(pattern.left, names);
        return;
    }

    if (pattern.type === 'ArrayPattern') {
        pattern.elements.forEach((element) => collectBindingPatternNames(element, names));
        return;
    }

    if (pattern.type === 'ObjectPattern') {
        pattern.properties.forEach((property) => {
            if (property.type === 'RestElement') {
                collectBindingPatternNames(property.argument, names);
                return;
            }

            collectBindingPatternNames(property.value, names);
        });
    }
}

/**
 * Checks whether an identifier reads a runtime value instead of declaring or naming one.
 */
function isRuntimeIdentifierReference(node: Identifier, parent: BabelNode | null): boolean {
    if (!parent) {
        return true;
    }

    if (parent.type === 'MemberExpression' || parent.type === 'OptionalMemberExpression') {
        return parent.property !== node || Boolean(parent.computed);
    }

    if (parent.type === 'ObjectProperty') {
        return parent.value === node || Boolean(parent.computed);
    }

    if (parent.type === 'ObjectMethod') {
        return parent.key !== node || Boolean(parent.computed);
    }

    if (
        parent.type === 'VariableDeclarator' ||
        parent.type === 'FunctionDeclaration' ||
        parent.type === 'FunctionExpression' ||
        parent.type === 'ClassDeclaration' ||
        parent.type === 'ClassExpression'
    ) {
        return parent.id !== node;
    }

    return true;
}

function isBabelNodeLike(value: unknown): value is BabelNode {
    return Boolean(value && typeof value === 'object' && 'type' in value && typeof value.type === 'string');
}

function shouldSkipReferenceChild(key: string): boolean {
    return [
        'loc',
        'range',
        'start',
        'end',
        'leadingComments',
        'trailingComments',
        'innerComments',
        'typeAnnotation',
        'typeParameters',
        'typeArguments',
        'returnType',
    ].includes(key);
}

/**
 * Finds the first local setup binding read inside a macro argument that is moved to the generated script root.
 */
function findLocalSetupReference(
    node: BabelNode | null | undefined,
    localBindings: Set<string>,
    shadowedBindings = new Set<string>(),
    parent: BabelNode | null = null,
): Identifier | null {
    if (!node) {
        return null;
    }

    if (
        node.type === 'Identifier' &&
        localBindings.has(node.name) &&
        !shadowedBindings.has(node.name) &&
        isRuntimeIdentifierReference(node, parent)
    ) {
        return node;
    }

    const childShadowedBindings = new Set(shadowedBindings);

    if (
        node.type === 'FunctionDeclaration' ||
        node.type === 'FunctionExpression' ||
        node.type === 'ArrowFunctionExpression' ||
        node.type === 'ObjectMethod' ||
        node.type === 'ClassMethod' ||
        node.type === 'ClassPrivateMethod'
    ) {
        node.params.forEach((param) => collectBindingPatternNames(param, childShadowedBindings));
    }

    for (const [
        key,
        value,
    ] of Object.entries(node as unknown as Record<string, unknown>)) {
        if (shouldSkipReferenceChild(key)) {
            continue;
        }

        if (Array.isArray(value)) {
            for (const child of value) {
                if (!isBabelNodeLike(child)) {
                    continue;
                }

                const reference = findLocalSetupReference(child, localBindings, childShadowedBindings, node);

                if (reference) {
                    return reference;
                }
            }

            continue;
        }

        if (isBabelNodeLike(value)) {
            const reference = findLocalSetupReference(value, localBindings, childShadowedBindings, node);

            if (reference) {
                return reference;
            }
        }
    }

    return null;
}

/**
 * Hoisted Vue macros run outside the generated Shopware setup callback.
 * Their runtime arguments must therefore stay independent from setup-local values.
 */
function assertHoistedMacroArgumentsDoNotUseLocalSetup({
    scriptOffset,
    runtimeBindingNames,
    macroCalls,
}: {
    scriptOffset: number;
    runtimeBindingNames: Set<string>;
    macroCalls: { name: string; call: CallExpression }[];
}): void {
    macroCalls.forEach(({ name, call }) => {
        call.arguments.forEach((argument) => {
            const reference = findLocalSetupReference(argument, runtimeBindingNames);

            if (!reference) {
                return;
            }

            throw new ShopwareSetupTransformError(
                `${name}() arguments are hoisted outside the Shopware setup callback and must not reference local setup bindings. Use inline literals or imported constants instead.`,
                scriptOffset + getNodeRange(reference, scriptOffset).start,
            );
        });
    });
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

export { type ImportBlock, type ShopwareSetupScriptAnalysis, UNSUPPORTED_VUE_MACROS, analyzeShopwareSetupScript };
