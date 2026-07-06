/**
 * @sw-package framework
 */

import type { CallExpression, Identifier, ImportDeclaration, Node as BabelNode, Statement } from '@babel/types';
import { ShopwareSetupTransformError } from './utils/transform-error';
import {
    type SetupMacroBuckets,
    type StatementMacroCall,
    collectTopLevelSetupMacroCalls,
    extractStaticObjectMarker,
    getStatementCompilerMacroCall,
    isCompilerMacroCall,
    isStatementCompilerMacro,
    UNSUPPORTED_VUE_MACROS,
} from './script-analyzer/macros';
import { type SourceRange, getNodeRange, isBabelNodeLike, parseScript, walk } from './script-analyzer/utils';
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
    propsMacro: SetupMacroSummary | null;
    emitsMacro: SetupMacroSummary | null;
    slotsMacro: SetupMacroSummary | null;
    optionsMacro: SetupMacroSummary | null;
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
    return (
        statement.type === 'TSInterfaceDeclaration' ||
        statement.type === 'TSTypeAliasDeclaration' ||
        Boolean((statement as Statement & { declare?: boolean }).declare)
    );
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
 *
 * TODO: the override transform adds its own marker macro and the mode-dependent marker rules here.
 */
function validateShopwareMarkers({
    scriptOffset,
    publicMarkerStatements,
}: {
    scriptOffset: number;
    publicMarkerStatements: StatementMacroCall[];
}): void {
    if (publicMarkerStatements.length > 1) {
        throw new ShopwareSetupTransformError(
            'Only one swDefinePublic() call is allowed in a base Shopware setup block.',
            scriptOffset + getNodeRange(publicMarkerStatements[1], scriptOffset).start,
        );
    }
}

/**
 * Produces the semantic model used by the lowering step.
 */
function analyzeShopwareSetupScript(script: string, options: AnalyzerOptions): ShopwareSetupScriptAnalysis {
    const lang = options.lang ?? 'js';
    const scriptOffset = options.scriptOffset;
    const ast = parseScript(script, lang, scriptOffset);
    const imports: ImportDeclaration[] = [];
    const typeDeclarations: Statement[] = [];
    const importedBindings = new Set<string>();
    const runtimeBindings: RuntimeBinding[] = [];
    const runtimeBindingNames = new Set<string>();
    const publicMarkerStatements: StatementMacroCall[] = [];
    const definePropsCalls: CallExpression[] = [];
    const defineEmitsCalls: CallExpression[] = [];
    const defineExposeCalls: CallExpression[] = [];
    const defineExposeStatements: (DefineExposeStatement & StatementWithCall)[] = [];
    const defineSlotsCalls: CallExpression[] = [];
    const defineOptionsCalls: CallExpression[] = [];
    const defineOptionsStatements: (DefineOptionsStatement & StatementWithCall)[] = [];
    const withDefaultsCalls: CallExpression[] = [];
    const topLevelPublicCalls = new Set<CallExpression>();
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

        // TODO: the override transform collects its own marker macro (swDefineOverride) here.
        if (isStatementCompilerMacro(statement, 'swDefinePublic')) {
            publicMarkerStatements.push(statement);
            topLevelPublicCalls.add(statement.expression);
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

        collectRuntimeBinding(statement, runtimeBindings, runtimeBindingNames, scriptOffset);
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
        scriptOffset,
        topLevelPublicCalls,
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
        scriptOffset,
        publicMarkerStatements,
    });

    const publicEntries =
        publicMarkerStatements.length > 0
            ? extractStaticObjectMarker(publicMarkerStatements[0], scriptOffset, 'swDefinePublic', 'public')
            : [];

    assertStaticObjectEntries(publicEntries, runtimeBindingNames, importedBindings, scriptOffset, 'swDefinePublic');

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
        scriptOffset,
    );

    const bodyRemovals = [
        ...imports.map((importNode) => getNodeRange(importNode, scriptOffset)),
        ...typeDeclarations.map((declaration) => getNodeRange(declaration, scriptOffset)),
        ...defineOptionsStatements.map((entry) => getNodeRange(entry.statement, scriptOffset)),
        ...publicMarkerStatements.map((statement) => getNodeRange(statement, scriptOffset)),
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
        propsMacro,
        emitsMacro,
        slotsMacro,
        optionsMacro,
    };
}

export { type ImportBlock, type ShopwareSetupScriptAnalysis, UNSUPPORTED_VUE_MACROS, analyzeShopwareSetupScript };
