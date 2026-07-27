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
    getMacroEntries,
    getMacroGroupEntry,
} from './script-analyzer/macro-registry';
import { type SourceRange, getNodeRange, parseScript, walk } from './script-analyzer/utils';
import {
    type RuntimeBinding,
    RuntimeBindingCollector,
    collectImportBindings,
    collectRuntimeBinding,
} from './script-analyzer/runtime-bindings';
import {
    assertNoRuntimeBindingPropCollision,
    assertNoUnsupportedSyntax,
    assertReservedMacroNames,
    assertStaticObjectEntries,
} from './script-analyzer/validation';
import { collectSetupRenameTargets } from './flow-analysis';
import { collectDeclaredPropNames } from './script-analyzer/setup-inputs';

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
 * `overridePrivateBindings` is filled by template analysis, which runs after this pass.
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
    overridePrivateBindings: Set<string>;
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
            const range = getNodeRange(node);
            templateLiteralRanges.push([
                scriptOffset + range.start,
                scriptOffset + range.end,
            ]);
        }
    });

    const imports: ImportDeclaration[] = [];
    const typeDeclarations: Statement[] = [];
    const importedBindings = new Set<string>();
    const collector = new RuntimeBindingCollector(scriptOffset);
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

        // The Shopware marker statements are compile-time only: their entries are extracted below and
        // the statements themselves are removed from the generated output, so they never contribute
        // runtime bindings. Vue's own bare macro statements need no branch here - they declare nothing
        // for collectRuntimeBinding to find either way.
        const statementMacroName = statementEntries.find((entry) => entry.form === 'statement')?.name;

        if (statementMacroName === 'swDefinePublic' || statementMacroName === 'swDefineOverride') {
            return;
        }

        collectRuntimeBinding(statement, collector, scriptOffset, mode);
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

    // Macro arguments that read a top-level binding are Vue's business, and Vue handles them
    // correctly on its own: it hoists a statically-analysable local (`const d = 1`) to module scope
    // alongside the generated options, and rejects anything it cannot hoist (`ref(1)`, an expression
    // over another local, a `let`) with a clear message naming the separate-<script> workaround. A
    // guard here only produced false positives on the code Vue hoists fine.

    // assertMacroRules already enforced modes, so the props macro resolves to at most one entry here.
    const declaredPropNames = collectDeclaredPropNames(getMacroGroupEntry(macroEntries, 'props'));

    assertNoRuntimeBindingPropCollision(declaredPropNames, collector.bindings, scriptOffset);

    const publicEntries =
        publicMarkerEntries.length > 0
            ? extractStaticObjectMarker(publicMarkerEntries[0].call, scriptOffset, 'swDefinePublic', 'public')
            : [];
    const overrideEntries =
        overrideMarkerEntries.length > 0
            ? extractStaticObjectMarker(overrideMarkerEntries[0].call, scriptOffset, 'swDefineOverride', 'override')
            : [];

    assertStaticObjectEntries(publicEntries, collector.names, importedBindings, scriptOffset, 'swDefinePublic');
    assertStaticObjectEntries(overrideEntries, collector.names, importedBindings, scriptOffset, 'swDefineOverride');

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
            ...collector.bindings,
            ...importedNamedBindings,
        ],
        scriptOffset,
    );

    // Both modes strip the compile-time marker statements; only their surroundings differ.
    const markerRemovals = [
        ...publicMarkerEntries,
        ...overrideMarkerEntries,
    ].map((entry) => getNodeRange(entry.statement));

    const shared: SharedScriptAnalysis = {
        source: script,
        templateLiteralRanges,
        runtimeBindings: collector.bindings,
        runtimeBindingNames: collector.names,
        importedBindings,
    };

    if (mode === 'override') {
        return {
            ...shared,
            mode,
            // Script-local ranges; override lowering copies them back out to the generated script root.
            imports: imports.map(getNodeRange),
            typeDeclarations: typeDeclarations.map(getNodeRange),
            // The author body moves into a callback, so imports and type declarations leave it too - an
            // import is illegal there, and an ambient `declare` describes a value from elsewhere.
            bodyRemovals: [
                ...imports.map(getNodeRange),
                ...typeDeclarations.map(getNodeRange),
                ...markerRemovals,
            ],
            overrideEntries,
            runtimeInputAliasNames: collector.aliasNames,
            overridePrivateBindings: new Set(),
        };
    }

    return {
        ...shared,
        mode,
        markerRemovals,
        // Only base mode renames: the body stays where the author wrote it, so every top-level runtime
        // binding moves to its alias and the footer re-declares the original name. Computing this for
        // override mode would be a wasted AST walk - nothing there reads it.
        renameEdits: collectSetupRenameTargets(ast.program, collector.names, (name) => `__swSetupAuthor_${name}`).map(
            ({ node, replacement }) => ({
                ...getNodeRange(node),
                replacement,
            }),
        ),
        publicEntries,
    };
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
