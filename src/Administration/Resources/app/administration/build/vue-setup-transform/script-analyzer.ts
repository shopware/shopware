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
import type { ShopwareSetupMode } from './utils/shopware-setup-block';
import { ShopwareSetupTransformError } from './utils/transform-error';
import { extractStaticObjectMarker } from './script-analyzer/macros';
import {
    type MacroCallEntry,
    assertMacroRules,
    collectMacroCallEntries,
    getHoistedArgumentMacroNames,
    getMacroEntries,
    getMacroEntry,
} from './script-analyzer/macro-registry';
import { type SourceRange, getNodeRange, parseScript, walk } from './script-analyzer/utils';
import { type RuntimeBinding, collectImportBindings, collectRuntimeBinding } from './script-analyzer/runtime-bindings';
import {
    assertNoRuntimeBindingPropCollision,
    assertNoUnsupportedSyntax,
    assertReservedMacroNames,
    assertStaticObjectEntries,
} from './script-analyzer/validation';
import { assertHoistedMacroArgumentsDoNotUseLocalSetup } from './script-analyzer/hoisted-macro-arguments';
import { analyzeSetupInputs, type SetupInputReplacement, type SetupMacroSummary } from './script-analyzer/setup-inputs';

const SUPPORTED_SCRIPT_LANGS = new Set([
    'js',
    'jsx',
    'ts',
    'tsx',
]);

type ImportBlock = SourceRange & { code: string };
type TypeDeclarationBlock = SourceRange & { code: string };
type AnalyzerOptions = {
    mode: ShopwareSetupMode;
    lang: string | null;
    scriptOffset: number;
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
    // Absolute source ranges of template literals, so the renderer can skip re-indenting their interior
    // lines (indenting would rewrite the runtime string contents).
    templateLiteralRanges: [number, number][];
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
    exposeMacro: SetupMacroSummary | null;
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
    if (
        statement.type === 'TSInterfaceDeclaration' ||
        statement.type === 'TSTypeAliasDeclaration' ||
        Boolean((statement as Statement & { declare?: boolean }).declare)
    ) {
        return true;
    }

    // Type-only exports (`export type X`, `export interface X`, `export type { A }`) produce no runtime
    // output. Like Vue, they are hoisted whole to the generated script root, where an export is legal -
    // leaving them in the setup callback body would be a syntax error.
    if (statement.type === 'ExportNamedDeclaration') {
        return (
            statement.exportKind === 'type' ||
            statement.declaration?.type === 'TSInterfaceDeclaration' ||
            statement.declaration?.type === 'TSTypeAliasDeclaration'
        );
    }

    return false;
}

/**
 * Produces the semantic model used by the lowering step.
 */
function analyzeShopwareSetupScript(script: string, options: AnalyzerOptions): ShopwareSetupScriptAnalysis {
    const lang = options.lang ?? 'js';
    const mode = options.mode;
    const scriptOffset = options.scriptOffset;

    // The transform only understands the Babel-parseable script languages Vue uses. Anything else
    // (e.g. `lang="coffee"`) would otherwise be mis-parsed as plain JS or fail with an opaque Babel
    // error, so reject it up front, matching the fail-loudly philosophy.
    if (!SUPPORTED_SCRIPT_LANGS.has(lang)) {
        throw new ShopwareSetupTransformError(
            `Unsupported <script setup lang="${lang}"> in a Shopware setup block. Supported languages are js, jsx, ts, and tsx.`,
            scriptOffset,
        );
    }

    const ast = parseScript(script, lang, scriptOffset);

    // Collect template-literal ranges (absolute) up front so the renderer can leave their interior
    // lines un-indented.
    const templateLiteralRanges: [number, number][] = [];
    walk(ast.program, (node) => {
        if (node.type === 'TemplateLiteral') {
            const range = getNodeRange(node, scriptOffset);
            templateLiteralRanges.push([
                scriptOffset + range.start,
                scriptOffset + range.end,
            ]);
        }
    });

    const imports: ImportDeclaration[] = [];
    const typeDeclarations: Statement[] = [];
    const importedBindings = new Set<string>();
    const runtimeBindings: RuntimeBinding[] = [];
    const runtimeBindingNames = new Set<string>();
    const runtimeInputAliasNames = new Set<string>();
    const macroEntries: MacroCallEntry[] = [];

    ast.program.body.forEach((statement) => {
        const statementEntries = collectMacroCallEntries(statement);
        macroEntries.push(...statementEntries);

        if (statement.type === 'ImportDeclaration') {
            imports.push(statement);
            collectImportBindings(statement, importedBindings);
            return;
        }

        if (isHoistableTypeDeclaration(statement)) {
            typeDeclarations.push(statement);
            return;
        }

        // Marker, expose, and options statements are consumed here: markers and kept-at-root options
        // are removed from the callback body, expose statements stay but get their call replaced.
        const statementMacroName = statementEntries.find((entry) => entry.form === 'statement')?.name;

        if (
            statementMacroName === 'swDefinePublic' ||
            statementMacroName === 'swDefineOverride' ||
            statementMacroName === 'defineOptions' ||
            statementMacroName === 'defineExpose'
        ) {
            return;
        }

        collectRuntimeBinding(statement, runtimeBindings, runtimeBindingNames, runtimeInputAliasNames, scriptOffset, mode);
    });

    // Deliberately plural: the top-level walk runs before assertMacroRules, so even duplicate marker
    // statements (rejected right after) must be recognized as top-level calls here.
    const publicMarkerEntries = getMacroEntries(macroEntries, 'swDefinePublic', 'statement');
    const overrideMarkerEntries = getMacroEntries(macroEntries, 'swDefineOverride', 'statement');
    const topLevelMarkerCalls = new Map<string, Set<CallExpression>>([
        [
            'swDefinePublic',
            new Set(publicMarkerEntries.map((entry) => entry.call)),
        ],
        [
            'swDefineOverride',
            new Set(overrideMarkerEntries.map((entry) => entry.call)),
        ],
    ]);

    assertNoUnsupportedSyntax(ast, mode, scriptOffset, topLevelMarkerCalls);

    assertMacroRules(macroEntries, mode, scriptOffset);

    assertHoistedMacroArgumentsDoNotUseLocalSetup({
        scriptOffset,
        // Runtime input aliases (`const ctx = useSwContext()`) also live inside the setup callback, so
        // a hoisted macro argument referencing one is just as unreachable as a plain runtime binding.
        localSetupNames: new Set([
            ...runtimeBindingNames,
            ...runtimeInputAliasNames,
        ]),
        macroCalls: getHoistedArgumentMacroNames()
            .flatMap((name) => getMacroEntries(macroEntries, name))
            // defineOptions() is only hoisted in its statement form; a declaration is normal code.
            .filter((entry) => entry.name !== 'defineOptions' || entry.form === 'statement')
            .map((entry) => ({
                name: entry.name,
                call: entry.call,
            })),
    });

    const { setupInputReplacements, declaredPropNames, propsMacro, emitsMacro, slotsMacro, optionsMacro, exposeMacro } =
        analyzeSetupInputs(script, {
            scriptOffset,
            entries: macroEntries,
        });

    assertNoRuntimeBindingPropCollision(declaredPropNames, runtimeBindings, scriptOffset);

    const publicEntries =
        publicMarkerEntries.length > 0
            ? extractStaticObjectMarker(publicMarkerEntries[0].call, scriptOffset, 'swDefinePublic', 'public')
            : [];
    const overrideEntries =
        overrideMarkerEntries.length > 0
            ? extractStaticObjectMarker(overrideMarkerEntries[0].call, scriptOffset, 'swDefineOverride', 'override')
            : [];

    assertStaticObjectEntries(publicEntries, runtimeBindingNames, importedBindings, scriptOffset, 'swDefinePublic');
    assertStaticObjectEntries(overrideEntries, runtimeBindingNames, importedBindings, scriptOffset, 'swDefineOverride');

    const importedNamedBindings = Array.from(importedBindings).flatMap((name) => {
        const node = imports.find((importNode) => importNode.specifiers.some((specifier) => specifier.local?.name === name));

        return node
            ? [
                  {
                      name,
                      node,
                      importSource: node.source.value,
                  },
              ]
            : [];
    });

    assertReservedMacroNames(
        [
            ...runtimeBindings,
            ...importedNamedBindings,
        ],
        scriptOffset,
    );

    // Post-assert there is at most one kept-at-root defineOptions(). The marker arrays stay plural
    // because they predate the assert (see above); at this point they also hold at most one entry.
    const keptAtRootEntry = getMacroEntry(macroEntries, 'defineOptions', 'statement');
    // defineExpose is removed from the callback body and re-emitted as a real macro at the script-setup
    // footer (see setup-inputs exposeMacro / base lowering).
    const exposeStatementEntry = getMacroEntry(macroEntries, 'defineExpose', 'statement');
    const bodyRemovals = [
        ...imports.map((importNode) => getNodeRange(importNode, scriptOffset)),
        ...typeDeclarations.map((declaration) => getNodeRange(declaration, scriptOffset)),
        ...(keptAtRootEntry ? [getNodeRange(keptAtRootEntry.statement, scriptOffset)] : []),
        ...(exposeStatementEntry ? [getNodeRange(exposeStatementEntry.statement, scriptOffset)] : []),
        ...publicMarkerEntries.map((entry) => getNodeRange(entry.statement, scriptOffset)),
        ...overrideMarkerEntries.map((entry) => getNodeRange(entry.statement, scriptOffset)),
    ];
    return {
        source: script,
        imports: getImportRangesAndCode(script, imports, scriptOffset),
        typeDeclarations: getTypeDeclarationRangesAndCode(script, typeDeclarations, scriptOffset),
        bodyRemovals,
        setupInputReplacements,
        templateLiteralRanges,
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
        exposeMacro,
        overridePrivateBindings: new Set(),
        overridePrivateNamespace: null,
    };
}

export { type ImportBlock, type ShopwareSetupScriptAnalysis, analyzeShopwareSetupScript };
