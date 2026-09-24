/**
 * @sw-package framework
 */

import { traverse, type NodePath } from '@babel/core';
import { parse, type ParserPlugin } from '@babel/parser';
import {
    getBindingIdentifiers,
    type File as BabelFile,
    type Identifier,
    type ImportDeclaration,
    type JSXIdentifier,
    type Node as BabelNode,
    type Program,
    type Statement,
    type VariableDeclaration,
} from '@babel/types';
import { type ShopwareSetupMode, OVERRIDE_LOCAL_STATE_KEY, RESERVED_BINDING_PREFIX } from './naming';
import {
    type MacroCall,
    MACROS,
    assertMacroRules,
    collectMacroCalls,
    extractMarkerEntries,
    getMacroCall,
    isMacroName,
    wrongModeMessage,
} from './script-analyzer/macros';
import { ShopwareSetupTransformError, nodeRange } from './utils/transform-error';

const SUPPORTED_SCRIPT_LANGS = [
    'js',
    'jsx',
    'ts',
    'tsx',
];

/** Babel opens no scope for TS signatures, so their parameter names resolve as outer references. */
const TS_SIGNATURES = new Set([
    'TSCallSignatureDeclaration',
    'TSConstructSignatureDeclaration',
    'TSConstructorType',
    'TSDeclareFunction',
    'TSDeclareMethod',
    'TSFunctionType',
    'TSIndexSignature',
    'TSMethodSignature',
]);

/** TS nodes whose identifier children are runtime values, not types. */
const TS_VALUE_PARENTS = new Set([
    'TSAsExpression',
    'TSEnumMember',
    'TSInstantiationExpression',
    'TSNonNullExpression',
    'TSSatisfiesExpression',
    'TSTypeAssertion',
]);

/** Script-local offsets into the `<script setup>` content. */
type SourceRange = { start: number; end: number };

/**
 * One occurrence of a top-level binding the base lowering rewrites. A shorthand property or export
 * shares its range with a name that must survive, so its replacement spells both out. A deferred
 * occurrence runs after setup and resolves through the override-aware state (late binding).
 */
type RenameTarget = SourceRange & {
    localName: string;
    expansion: 'plain' | 'shorthand-property' | 'shorthand-export';
    deferred: boolean;
};

type SharedScriptAnalysis = {
    marker: SourceRange;
    /** Top-level declarations that become setup state, in source order. */
    runtimeBindings: string[];
    /** Start of the first statement that stays in the body: generated setup code is inserted there. */
    bodyStart: number;
};

type BaseSetupScriptAnalysis = SharedScriptAnalysis & {
    mode: 'base';
    renameTargets: RenameTarget[];
    publicEntries: string[];
};

type OverrideSetupScriptAnalysis = SharedScriptAnalysis & {
    mode: 'override';
    /** Statements that cannot live in the override callback: imports, `declare`, type exports. */
    hoisted: SourceRange[];
    overrideEntries: string[];
    /** `const previousState = useSwPreviousState()` and friends: usable locally, never returned as state. */
    runtimeInputAliasNames: Set<string>;
};

type ShopwareSetupScriptAnalysis = BaseSetupScriptAnalysis | OverrideSetupScriptAnalysis;

type ImportedBinding = { name: string; node: ImportDeclaration; importSource: string };

type ClassifiedStatements = {
    importedBindings: ImportedBinding[];
    bindings: Map<string, Identifier>;
    /** Bindings initialized by a Vue macro, which Vue compiles and which are never late-bound. */
    macroBindings: Set<string>;
    aliases: Set<string>;
    macroCalls: MacroCall[];
};

function parseScript(script: string, lang: string, offset: number): BabelFile {
    if (!SUPPORTED_SCRIPT_LANGS.includes(lang)) {
        throw new ShopwareSetupTransformError(
            `Unsupported <script setup lang="${lang}"> in a Shopware setup block. Supported languages are js, jsx, ts, and tsx.`,
            offset,
        );
    }

    const plugins: ParserPlugin[] = ['importMeta'];

    if (lang.startsWith('ts')) {
        plugins.push('typescript');
    }

    if (lang.endsWith('x')) {
        plugins.push('jsx');
    }

    try {
        return parse(script, { sourceType: 'module', plugins, ranges: true });
    } catch (error: unknown) {
        const { pos, message } = error as { pos?: unknown; message?: unknown };

        throw new ShopwareSetupTransformError(
            `Unable to parse Shopware setup script: ${typeof message === 'string' ? message : String(error)}`,
            typeof pos === 'number' ? offset + pos : offset,
        );
    }
}

function isTypeExport(statement: Statement): boolean {
    return (
        statement.type === 'ExportNamedDeclaration' &&
        (statement.exportKind === 'type' ||
            statement.declaration?.type === 'TSInterfaceDeclaration' ||
            statement.declaration?.type === 'TSTypeAliasDeclaration')
    );
}

function isAmbient(statement: Statement): boolean {
    return Boolean((statement as Statement & { declare?: boolean }).declare);
}

/** Statements that are illegal inside a function body, so an override cannot keep them in its callback. */
function isHoistedFromOverride(statement: Statement): boolean {
    return statement.type === 'ImportDeclaration' || isAmbient(statement) || isTypeExport(statement);
}

function isTypeOnly(statement: Statement): boolean {
    return (
        statement.type === 'TSInterfaceDeclaration' ||
        statement.type === 'TSTypeAliasDeclaration' ||
        isAmbient(statement) ||
        isTypeExport(statement)
    );
}

/** The identifiers a declaration pattern declares, in source order. */
function patternIdentifiers(pattern: BabelNode): Identifier[] {
    return Object.values(getBindingIdentifiers(pattern, true))
        .flat()
        .sort((a, b) => (a.start ?? 0) - (b.start ?? 0));
}

/**
 * The base body is copied into setup state once, at the end of setup. A reassigned `let`/`var` would
 * keep changing in the body while the template, parents and overrides hold the old value.
 */
function assertConstDeclaration(statement: VariableDeclaration, identifiers: Identifier[], offset: number): void {
    if ((statement.kind === 'let' || statement.kind === 'var') && identifiers.length > 0) {
        throw new ShopwareSetupTransformError(
            `Top-level "${statement.kind} ${identifiers[0].name}" is not supported in a base Shopware setup component. ` +
                'The template, parents and overrides read the binding once, at the end of setup, so a later ' +
                'reassignment in the component would never reach them. Declare it with const and keep mutable ' +
                'state in a ref(), for example `const count = ref(0)`.',
            nodeRange(identifiers[0], offset),
        );
    }
}

function classifyTopLevelStatements(ast: BabelFile, mode: ShopwareSetupMode, offset: number): ClassifiedStatements {
    const classified: ClassifiedStatements = {
        importedBindings: [],
        bindings: new Map(),
        macroBindings: new Set(),
        aliases: new Set(),
        macroCalls: [],
    };
    const add = (identifier: Identifier) => {
        // `var` and function declarations may legally redeclare a name, which would silently overwrite
        // returned state.
        if (classified.bindings.has(identifier.name)) {
            throw new ShopwareSetupTransformError(
                `Duplicate top-level Shopware setup binding "${identifier.name}".`,
                nodeRange(identifier, offset),
            );
        }

        classified.bindings.set(identifier.name, identifier);
    };

    ast.program.body.forEach((statement) => {
        const macroCalls = collectMacroCalls(statement);
        classified.macroCalls.push(...macroCalls);

        if (statement.type === 'ImportDeclaration') {
            statement.specifiers.forEach((specifier) => {
                classified.importedBindings.push({
                    name: specifier.local.name,
                    node: statement,
                    importSource: statement.source.value,
                });
            });
            return;
        }

        if (
            isTypeOnly(statement) ||
            macroCalls.some((call) => call.form === 'statement' && call.name.startsWith('swDefine'))
        ) {
            return;
        }

        if (
            statement.type === 'FunctionDeclaration' ||
            statement.type === 'ClassDeclaration' ||
            statement.type === 'TSEnumDeclaration'
        ) {
            if (statement.id) {
                add(statement.id);
            }

            return;
        }

        if (statement.type !== 'VariableDeclaration') {
            return;
        }

        statement.declarations.forEach((declarator) => {
            const macro = getMacroCall(declarator.init);
            const kind = macro ? MACROS[macro.name].binding : undefined;

            if (kind === 'props' && declarator.id.type !== 'Identifier') {
                return;
            }

            if (kind === 'alias' && declarator.id.type === 'Identifier') {
                classified.aliases.add(declarator.id.name);
                return;
            }

            const identifiers = patternIdentifiers(declarator.id);

            if (mode === 'base') {
                assertConstDeclaration(statement, identifiers, offset);
            }

            identifiers.forEach((identifier) => {
                add(identifier);

                if (macro && MACROS[macro.name].vue) {
                    classified.macroBindings.add(identifier.name);
                }
            });
        });
    });

    return classified;
}

type TraversalResult = {
    program: NodePath<Program>;
    /** References Babel's scope does not record: enums, and most `typeof x` / type references. */
    untrackedReferences: NodePath<Identifier>[];
};

function isTypeReference(path: NodePath<Identifier>): boolean {
    const { node, parent } = path;

    return (
        (parent.type === 'TSTypeQuery' && parent.exprName === node) ||
        (parent.type === 'TSTypeReference' && parent.typeName === node) ||
        (parent.type === 'TSQualifiedName' && parent.left === node)
    );
}

/**
 * Rejects what the native body cannot express - wrong-mode helpers anywhere, top-level await, module
 * exports inside an override callback - and collects the references of `renamed` bindings that
 * Babel's scope does not track.
 */
function traverseScript(
    ast: BabelFile,
    mode: ShopwareSetupMode,
    offset: number,
    renamed: Set<string>,
    enumNames: Set<string>,
): TraversalResult {
    let program: NodePath<Program> | null = null;
    const untrackedReferences: NodePath<Identifier>[] = [];
    // Vue's compileScript rejects module exports in a base body itself; an override body moves into a
    // callback, where an export would be a syntax error.
    const rejectExport = (path: NodePath) => {
        if (mode === 'override') {
            throw new ShopwareSetupTransformError(
                '<script setup> cannot contain ES module exports.',
                nodeRange(path.node, offset),
            );
        }
    };
    const rejectTopLevelAwait = (path: NodePath) => {
        if (!path.getFunctionParent()) {
            throw new ShopwareSetupTransformError(
                'Top-level await is not supported inside Shopware setup blocks.',
                nodeRange(path.node, offset),
            );
        }
    };

    traverse(ast, {
        Program(path) {
            program = path;
        },
        CallExpression(path) {
            const { callee } = path.node;

            if (callee.type !== 'Identifier' || !isMacroName(callee.name)) {
                return;
            }

            const rule = MACROS[callee.name];

            if (rule.nested && !rule.modes.includes(mode)) {
                throw new ShopwareSetupTransformError(wrongModeMessage(callee.name, mode), nodeRange(path.node, offset));
            }
        },
        AwaitExpression: rejectTopLevelAwait,
        ForOfStatement(path) {
            if (path.node.await) {
                rejectTopLevelAwait(path);
            }
        },
        ExportNamedDeclaration(path) {
            if (!isTypeExport(path.node) && !path.findParent((parent) => parent.isTSModuleDeclaration())) {
                rejectExport(path);
            }
        },
        ExportAllDeclaration: rejectExport,
        ExportDefaultDeclaration: rejectExport,
        Identifier(path) {
            const { name } = path.node;

            if (!renamed.has(name)) {
                return;
            }

            const binding = path.scope.getBinding(name);
            const isEnum = enumNames.has(name);
            const resolvesToTopLevel = isEnum ? !binding : binding === path.scope.getProgramParent().getBinding(name);

            if (resolvesToTopLevel && (isEnum ? path.isReferencedIdentifier() : isTypeReference(path))) {
                untrackedReferences.push(path);
            }
        },
    });

    return { program: program as unknown as NodePath<Program>, untrackedReferences };
}

function isTypeSignatureParameter(path: NodePath): boolean {
    for (let current = path; current.parentPath; current = current.parentPath) {
        if (
            (current.listKey === 'parameters' || current.listKey === 'params') &&
            TS_SIGNATURES.has(current.parentPath.type)
        ) {
            return true;
        }

        if (current.parentPath.isStatement()) {
            return false;
        }
    }

    return false;
}

function isInVueMacroArgument(path: NodePath): boolean {
    return Boolean(
        path.findParent((parent) => {
            const macro = parent.isCallExpression() ? getMacroCall(parent.node) : null;

            return Boolean(macro && MACROS[macro.name].vue);
        }),
    );
}

function renameExpansion(path: NodePath<Identifier | JSXIdentifier>): RenameTarget['expansion'] {
    const { node, parent, parentPath } = path;

    if (
        (parent.type === 'ObjectProperty' && parent.shorthand && parent.value === node) ||
        (parent.type === 'AssignmentPattern' &&
            parent.left === node &&
            parentPath?.parent.type === 'ObjectProperty' &&
            parentPath.parent.shorthand)
    ) {
        return 'shorthand-property';
    }

    if (parent.type === 'ExportSpecifier' && parent.local === node && parent.exported.type === 'Identifier') {
        return parent.exported.name === node.name ? 'shorthand-export' : 'plain';
    }

    return 'plain';
}

/**
 * Every occurrence of a top-level runtime binding: its declaration, references (types, JSX tags and
 * `typeof` included) and reassignments. Babel resolves shadowing.
 *
 * A value reference inside a function is deferred - it runs after setup, when overrides are applied -
 * unless it reads a Vue macro binding or sits in a macro argument, which Vue hoists out of setup.
 */
function collectRenameTargets(
    { program, untrackedReferences }: TraversalResult,
    classified: ClassifiedStatements,
): RenameTarget[] {
    const targets = new Map<BabelNode, RenameTarget>();
    const add = (path: NodePath<Identifier | JSXIdentifier>, isReference: boolean) => {
        const { node, parent } = path;

        if (isTypeSignatureParameter(path)) {
            return;
        }

        const isValue =
            !(parent.type.startsWith('TS') && !TS_VALUE_PARENTS.has(parent.type)) && parent.type !== 'ExportSpecifier';

        targets.set(node, {
            start: node.start ?? 0,
            // A typed identifier's range includes its annotation.
            end: (node.start ?? 0) + node.name.length,
            localName: node.name,
            expansion: renameExpansion(path),
            deferred:
                isReference &&
                isValue &&
                !classified.macroBindings.has(node.name) &&
                path.getFunctionParent() !== null &&
                !isInVueMacroArgument(path),
        });
    };

    classified.bindings.forEach((_, name) => {
        const binding = program.scope.getBinding(name);

        if (!binding) {
            return;
        }

        [binding.path, ...binding.constantViolations].forEach((path) => {
            (path.getBindingIdentifierPaths(true, true)[name] ?? []).forEach((identifierPath) => add(identifierPath, false));
        });
        binding.referencePaths.forEach((path) => {
            if (!targets.has(path.node)) {
                add(path as NodePath<Identifier | JSXIdentifier>, true);
            }
        });
    });
    untrackedReferences.forEach((path) => {
        if (!targets.has(path.node)) {
            add(path, path.parent.type !== 'TSEnumDeclaration');
        }
    });

    return [...targets.values()].sort((a, b) => a.start - b.start);
}

function assertReservedNames(classified: ClassifiedStatements, offset: number): void {
    const named: { name: string; node: BabelNode; importSource?: string }[] = [
        ...[...classified.bindings].map(([name, node]) => ({ name, node })),
        ...classified.importedBindings,
    ];

    named.forEach(({ name, node, importSource }) => {
        // Vue drops an import of its own macro names; any other source would be hijacked by the macro.
        if (importSource === 'vue' && isMacroName(name) && MACROS[name].vue) {
            return;
        }

        if (isMacroName(name)) {
            throw new ShopwareSetupTransformError(
                `"${name}" is reserved by the Shopware setup transform and must not be declared or imported.`,
                nodeRange(node, offset),
            );
        }

        if (name === OVERRIDE_LOCAL_STATE_KEY) {
            throw new ShopwareSetupTransformError(
                `"${name}" is reserved for Shopware override-private state and must not be declared or imported.`,
                nodeRange(node, offset),
            );
        }

        if (name.startsWith(RESERVED_BINDING_PREFIX)) {
            throw new ShopwareSetupTransformError(
                `"${name}" uses the reserved "${RESERVED_BINDING_PREFIX}" prefix of the Shopware setup transform and must not be declared or imported.`,
                nodeRange(node, offset),
            );
        }
    });
}

function assertMarkerEntriesExist(
    entries: string[],
    classified: ClassifiedStatements,
    macroName: string,
    offset: number,
): void {
    entries.forEach((name) => {
        if (classified.importedBindings.some((binding) => binding.name === name)) {
            throw new ShopwareSetupTransformError(
                `Imported binding "${name}" cannot be exposed with ${macroName}().`,
                offset,
            );
        }

        if (!classified.bindings.has(name)) {
            throw new ShopwareSetupTransformError(`${macroName}() references unknown local binding "${name}".`, offset);
        }
    });
}

function analyzeShopwareSetupScript(
    script: string,
    { mode, lang, offset }: { mode: ShopwareSetupMode; lang: string | null; offset: number },
): ShopwareSetupScriptAnalysis {
    const ast = parseScript(script, lang ?? 'js', offset);
    const { body } = ast.program;
    const classified = classifyTopLevelStatements(ast, mode, offset);
    const enumNames = new Set(
        mode === 'base'
            ? body.flatMap((statement) => (statement.type === 'TSEnumDeclaration' ? [statement.id.name] : []))
            : [],
    );
    const traversal = traverseScript(
        ast,
        mode,
        offset,
        new Set(mode === 'base' ? classified.bindings.keys() : []),
        enumNames,
    );
    const marker = assertMacroRules(classified.macroCalls, mode, offset);
    const entries = extractMarkerEntries(marker, mode, offset);

    assertMarkerEntriesExist(entries, classified, marker.name, offset);
    assertReservedNames(classified, offset);

    const range = (node: BabelNode): SourceRange => ({ start: node.start ?? 0, end: node.end ?? 0 });
    const stays = (statement: Statement) =>
        mode === 'base'
            ? statement.type !== 'ImportDeclaration' && !isTypeOnly(statement)
            : !isHoistedFromOverride(statement);
    const shared: SharedScriptAnalysis = {
        marker: range(marker.statement),
        runtimeBindings: [...classified.bindings.keys()],
        // The marker statement always stays, so there is always a first body statement.
        bodyStart: body.find(stays)?.start ?? 0,
    };

    if (mode === 'override') {
        return {
            ...shared,
            mode,
            hoisted: body.filter(isHoistedFromOverride).map(range),
            overrideEntries: entries,
            runtimeInputAliasNames: classified.aliases,
        };
    }

    return {
        ...shared,
        mode,
        renameTargets: collectRenameTargets(traversal, classified),
        publicEntries: entries,
    };
}

/**
 * @private
 */
export {
    type BaseSetupScriptAnalysis,
    type OverrideSetupScriptAnalysis,
    type RenameTarget,
    type ShopwareSetupScriptAnalysis,
    type SourceRange,
    analyzeShopwareSetupScript,
};
