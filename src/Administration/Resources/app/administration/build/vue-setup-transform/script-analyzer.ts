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

import type { CallExpression, File as BabelFile, ImportDeclaration, Statement } from '@babel/types';
import type { ShopwareSetupMode } from './utils/shopware-setup-block';
import { ShopwareSetupTransformError } from './utils/transform-error';
import { extractStaticObjectMarker } from './script-analyzer/macros';
import {
    type MacroCallEntry,
    assertMacroRules,
    collectMacroCallEntries,
    getMacroEntries,
} from './script-analyzer/macro-registry';
import { type SourceRange, getNodeRange, parseScript, walk } from './script-analyzer/utils';
import {
    type ImportedBinding,
    type RuntimeBinding,
    RuntimeBindingCollector,
    collectImportBindings,
    collectRuntimeBinding,
} from './script-analyzer/runtime-bindings';
import {
    assertNoUnsupportedSyntax,
    assertReservedMacroNames,
    assertStaticObjectEntries,
} from './script-analyzer/validation';
import { collectSetupRenameTargets } from './flow-analysis';

const SUPPORTED_SCRIPT_LANGS = new Set([
    'js',
    'jsx',
    'ts',
    'tsx',
]);

type AnalyzerOptions = {
    mode: ShopwareSetupMode;
    lang: string | null;
    scriptOffset: number;
};

/**
 * The parts of the analysis both lowering modes read.
 */
type SharedScriptAnalysis = {
    source: string;
    // Absolute source ranges of template literals, so the renderer can skip re-indenting their interior
    // lines (indenting would rewrite the runtime string contents).
    templateLiteralRanges: [number, number][];
    runtimeBindings: RuntimeBinding[];
    runtimeBindingNames: Set<string>;
    importedBindings: Set<string>;
};

/**
 * Base mode keeps the author body in place, so it needs no imports or type declarations lifted out.
 *
 * It removes only the marker statements, and applies the rename edits that move every top-level
 * runtime binding to its `__swSetupAuthor_` alias so the generated footer can re-declare the original
 * names from `attachOverrides(...)`.
 */
type BaseScriptAnalysis = {
    mode: 'base';
    markerRemovals: SourceRange[];
    renameEdits: (SourceRange & { replacement: string })[];
    publicEntries: string[];
};

/**
 * Override mode moves the author body into a callback, so imports and type declarations are lifted to
 * the generated script root and removed from the body, and nothing is renamed.
 *
 * The set of override-local bindings to forward is not here: it is only known after template analysis,
 * so it is passed straight to the lowerer rather than carried as a would-be-empty field on this shape.
 */
type OverrideScriptAnalysis = {
    mode: 'override';
    // Script-local ranges only: override lowering copies these through `fromSource(...)`, which keeps
    // them addressable for sourcemaps rather than materializing their text.
    imports: SourceRange[];
    typeDeclarations: SourceRange[];
    bodyRemovals: SourceRange[];
    overrideEntries: string[];
    runtimeInputAliasNames: Set<string>;
};

/**
 * Describes one parsed Shopware setup script after macro validation and binding classification.
 *
 * Lowering uses this shape as its only script-side input. It is a union on `mode` because the two
 * lowering strategies genuinely need different things: narrow on `analysis.mode` and each lowerer sees
 * only the fields it can actually use.
 */
type ShopwareSetupScriptAnalysis = SharedScriptAnalysis & (BaseScriptAnalysis | OverrideScriptAnalysis);

/** The analysis narrowed to base mode, as `buildBaseScript` consumes it. */
type BaseSetupScriptAnalysis = SharedScriptAnalysis & BaseScriptAnalysis;

/** The analysis narrowed to override mode, as `buildOverrideScript` consumes it. */
type OverrideSetupScriptAnalysis = SharedScriptAnalysis & OverrideScriptAnalysis;

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
 * The raw material one classification walk produces from the top-level statements. Every later phase
 * reads from this instead of re-walking the program.
 */
type ClassifiedStatements = {
    imports: ImportDeclaration[];
    typeDeclarations: Statement[];
    importedBindings: ImportedBinding[];
    bindings: RuntimeBindingCollector;
    macroEntries: MacroCallEntry[];
    // Deliberately collected before assertMacroRules, so even duplicate markers (rejected right after)
    // are still recognized as top-level calls here.
    publicMarkerEntries: MacroCallEntry[];
    overrideMarkerEntries: MacroCallEntry[];
    templateLiteralRanges: [number, number][];
};

/**
 * Phase 1 - rejects script languages the Babel-based analyzer cannot parse.
 *
 * Anything outside js/jsx/ts/tsx (e.g. `lang="coffee"`) would otherwise be mis-parsed as plain JS or
 * fail with an opaque Babel error, so reject it up front, matching the fail-loudly philosophy.
 */
function assertSupportedLang(lang: string, scriptOffset: number): void {
    if (!SUPPORTED_SCRIPT_LANGS.has(lang)) {
        throw new ShopwareSetupTransformError(
            `Unsupported <script setup lang="${lang}"> in a Shopware setup block. Supported languages are js, jsx, ts, and tsx.`,
            scriptOffset,
        );
    }
}

/**
 * Absolute template-literal ranges, so the renderer leaves their interior lines un-indented (indenting
 * would rewrite the runtime string contents).
 */
function collectTemplateLiteralRanges(ast: BabelFile, scriptOffset: number): [number, number][] {
    const ranges: [number, number][] = [];

    walk(ast.program, (node) => {
        if (node.type === 'TemplateLiteral') {
            const range = getNodeRange(node);
            ranges.push([
                scriptOffset + range.start,
                scriptOffset + range.end,
            ]);
        }
    });

    return ranges;
}

/**
 * Phase 2 - one walk over the top-level statements: separates imports and hoistable type declarations,
 * collects every macro/marker call, and classifies the rest into runtime bindings.
 */
function classifyTopLevelStatements(ast: BabelFile, mode: ShopwareSetupMode, scriptOffset: number): ClassifiedStatements {
    const imports: ImportDeclaration[] = [];
    const typeDeclarations: Statement[] = [];
    const importedBindings: ImportedBinding[] = [];
    const bindings = new RuntimeBindingCollector(scriptOffset);
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

        // The Shopware marker statements are compile-time only: their entries are extracted later and
        // the statements themselves are removed from the generated output, so they never contribute
        // runtime bindings. Vue's own bare macro statements need no branch here - they declare nothing
        // for collectRuntimeBinding to find either way.
        const statementMacroName = statementEntries.find((entry) => entry.form === 'statement')?.name;

        if (statementMacroName === 'swDefinePublic' || statementMacroName === 'swDefineOverride') {
            return;
        }

        collectRuntimeBinding(statement, bindings, scriptOffset, mode);
    });

    return {
        imports,
        typeDeclarations,
        importedBindings,
        bindings,
        macroEntries,
        publicMarkerEntries: getMacroEntries(macroEntries, 'swDefinePublic', 'statement'),
        overrideMarkerEntries: getMacroEntries(macroEntries, 'swDefineOverride', 'statement'),
        templateLiteralRanges: collectTemplateLiteralRanges(ast, scriptOffset),
    };
}

/** Marks each marker's own top-level call so the AST walk can tell it apart from a nested call. */
function collectTopLevelMarkerCalls(classified: ClassifiedStatements): Map<string, Set<CallExpression>> {
    return new Map<string, Set<CallExpression>>([
        [
            'swDefinePublic',
            new Set(classified.publicMarkerEntries.map((entry) => entry.call)),
        ],
        [
            'swDefineOverride',
            new Set(classified.overrideMarkerEntries.map((entry) => entry.call)),
        ],
    ]);
}

/**
 * Phase 3a - the dialect assertions that must fire before marker extraction: unsupported syntax, macro
 * placement/multiplicity, and a setup binding colliding with a declared prop.
 *
 * Macro arguments that read a top-level binding are deliberately not checked here - that is Vue's
 * business, and it handles them correctly on its own (it hoists a statically-analysable local to module
 * scope and rejects anything it cannot hoist, with a message naming the separate-`<script>` workaround).
 */
function assertScriptRules(
    ast: BabelFile,
    classified: ClassifiedStatements,
    mode: ShopwareSetupMode,
    scriptOffset: number,
): void {
    assertNoUnsupportedSyntax(ast, mode, scriptOffset, collectTopLevelMarkerCalls(classified));
    assertMacroRules(classified.macroEntries, mode, scriptOffset);

    // A setup binding sharing a declared prop's name is deliberately not rejected here: the
    // `vue/no-dupe-keys` ESLint rule flags it across all prop forms (incl. `defineProps<Props>()`,
    // which a build-time type check cannot resolve), so detection lives in lint rather than here.
}

/**
 * Phase 3b - extracts the public/override marker entry names and checks each refers to a local runtime
 * binding (never an import or a missing name).
 */
function extractMarkerEntries(
    classified: ClassifiedStatements,
    scriptOffset: number,
): { publicEntries: string[]; overrideEntries: string[] } {
    const publicEntries =
        classified.publicMarkerEntries.length > 0
            ? extractStaticObjectMarker(classified.publicMarkerEntries[0].call, scriptOffset, 'swDefinePublic', 'public')
            : [];
    const overrideEntries =
        classified.overrideMarkerEntries.length > 0
            ? extractStaticObjectMarker(
                  classified.overrideMarkerEntries[0].call,
                  scriptOffset,
                  'swDefineOverride',
                  'override',
              )
            : [];

    const importedBindingNames = new Set(classified.importedBindings.map((binding) => binding.name));
    assertStaticObjectEntries(
        publicEntries,
        classified.bindings.names,
        importedBindingNames,
        scriptOffset,
        'swDefinePublic',
    );
    assertStaticObjectEntries(
        overrideEntries,
        classified.bindings.names,
        importedBindingNames,
        scriptOffset,
        'swDefineOverride',
    );

    return { publicEntries, overrideEntries };
}

/** Both modes strip the compile-time marker statements; only their surroundings differ. */
function collectMarkerRemovals(classified: ClassifiedStatements): SourceRange[] {
    return [
        ...classified.publicMarkerEntries,
        ...classified.overrideMarkerEntries,
    ].map((entry) => getNodeRange(entry.statement));
}

/** The fields both lowering modes read. */
function buildSharedAnalysis(script: string, classified: ClassifiedStatements): SharedScriptAnalysis {
    return {
        source: script,
        templateLiteralRanges: classified.templateLiteralRanges,
        runtimeBindings: classified.bindings.bindings,
        runtimeBindingNames: classified.bindings.names,
        importedBindings: new Set(classified.importedBindings.map((binding) => binding.name)),
    };
}

/**
 * Phase 4 (override) - lifts imports and type declarations out of the callback body and records the
 * ranges to remove from it.
 */
function buildOverrideAnalysis(
    shared: SharedScriptAnalysis,
    classified: ClassifiedStatements,
    overrideEntries: string[],
): OverrideSetupScriptAnalysis {
    const importRanges = classified.imports.map(getNodeRange);
    const typeDeclarationRanges = classified.typeDeclarations.map(getNodeRange);

    return {
        ...shared,
        mode: 'override',
        // Script-local ranges; override lowering copies them back out to the generated script root.
        imports: importRanges,
        typeDeclarations: typeDeclarationRanges,
        // The author body moves into a callback, so imports and type declarations leave it too - an
        // import is illegal there, and an ambient `declare` describes a value from elsewhere.
        bodyRemovals: [
            ...importRanges,
            ...typeDeclarationRanges,
            ...collectMarkerRemovals(classified),
        ],
        overrideEntries,
        runtimeInputAliasNames: classified.bindings.aliasNames,
    };
}

/**
 * Phase 4 (base) - keeps the author body in place and records the rename edits that move every
 * top-level runtime binding to its `__swSetupAuthor_` alias.
 */
function buildBaseAnalysis(
    shared: SharedScriptAnalysis,
    ast: BabelFile,
    classified: ClassifiedStatements,
    publicEntries: string[],
): BaseSetupScriptAnalysis {
    return {
        ...shared,
        mode: 'base',
        markerRemovals: collectMarkerRemovals(classified),
        // Only base mode renames: the body stays where the author wrote it, so every top-level runtime
        // binding moves to its alias and the footer re-declares the original name. Computing this for
        // override mode would be a wasted AST walk - nothing there reads it.
        renameEdits: collectSetupRenameTargets(
            ast.program,
            classified.bindings.names,
            (name) => `__swSetupAuthor_${name}`,
        ).map(({ node, replacement }) => ({
            ...getNodeRange(node),
            replacement,
        })),
        publicEntries,
    };
}

/**
 * Produces the semantic model used by the lowering step, as a sequence of named phases.
 */
function analyzeShopwareSetupScript(script: string, options: AnalyzerOptions): ShopwareSetupScriptAnalysis {
    const lang = options.lang ?? 'js';
    const { mode, scriptOffset } = options;

    // 1 - guard the language, parse to an AST
    assertSupportedLang(lang, scriptOffset);
    const ast = parseScript(script, lang, scriptOffset);

    // 2 - classify every top-level statement in one walk
    const classified = classifyTopLevelStatements(ast, mode, scriptOffset);

    // 3 - assert the dialect rules. Order is load-bearing: syntax/macro/collision first, then the marker
    // entries are extracted and their own checks run, then reserved-name shadowing last - so the author
    // sees the most specific error first.
    assertScriptRules(ast, classified, mode, scriptOffset);
    const { publicEntries, overrideEntries } = extractMarkerEntries(classified, scriptOffset);
    assertReservedMacroNames(
        [
            ...classified.bindings.bindings,
            ...classified.importedBindings,
        ],
        scriptOffset,
    );

    // 4 - assemble the mode-specific analysis
    const shared = buildSharedAnalysis(script, classified);
    return mode === 'override'
        ? buildOverrideAnalysis(shared, classified, overrideEntries)
        : buildBaseAnalysis(shared, ast, classified, publicEntries);
}

/**
 * @private
 */
export {
    type BaseSetupScriptAnalysis,
    type OverrideSetupScriptAnalysis,
    type ShopwareSetupScriptAnalysis,
    analyzeShopwareSetupScript,
};
