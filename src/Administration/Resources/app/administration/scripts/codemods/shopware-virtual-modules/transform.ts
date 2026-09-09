/**
 * @sw-package framework
 *
 * Rewrites direct reads of the global `Shopware` object into `shopware:*` imports.
 *
 * Which specifiers exist and what each publishes comes from `shopware-modules.json`, the same file the
 * Vite plugin reads, so a rewrite can never name an export the generated module does not have.
 */

import { Project, QuoteKind, SyntaxKind, type Node, type SourceFile, type VariableStatement } from 'ts-morph';
import { readRegistry } from '../../../build/vite-plugins/virtual-shopware-modules/index';

export type Registry = ReturnType<typeof readRegistry>;

export type Rewrite = {
    /** The expression as it appeared, e.g. `Shopware.Store.get('swOrderDetail')`. */
    readonly from: string;
    /** What replaced it, e.g. `useSwOrderDetailStore()`. */
    readonly to: string;
    readonly specifier: string;
};

export type Skip = {
    readonly expression: string;
    readonly reason: string;
    /** The `shopware:*` family the access would have come from. */
    readonly specifier: string;
};

export type TransformResult = {
    readonly code: string;
    readonly rewrites: Rewrite[];
    readonly skips: Skip[];
};

/** The import a rewrite needs. A registry entry arrives as a default import, a branch member as a named one. */
type NeededImport = {
    readonly specifier: string;
    readonly local: string;
    /** The exported name, or `undefined` for a default import. */
    readonly imported?: string;
};

/** One migratable access: the import it needs and the expression that replaces it. */
type Candidate = NeededImport & {
    readonly replacement: string;
    /**
     * Whether the enclosing call goes away too.
     *
     * `Shopware.Mixin.getByName('x')` collapses to `xMixin` and `Shopware.Store.get('x')` to
     * `useXStore()`, so both replace the call. A branch member read replaces only the property chain.
     */
    readonly replacesCall: boolean;
};

/** Which families this run may rewrite. One family per run keeps each commit reviewable on its own. */
export type EnabledFamilies = ReadonlySet<string>;

export const ALL_FAMILIES: EnabledFamilies = new Set([
    'shopware:utils',
    'shopware:data',
    'shopware:mixins',
    'shopware:stores',
]);

export function loadRegistry(administrationRoot: string): Registry {
    return readRegistry(administrationRoot);
}

function upperFirst(value: string): string {
    return value.charAt(0).toUpperCase() + value.slice(1);
}

function camelCase(value: string): string {
    const [
        head = '',
        ...rest
    ] = value.split('-').filter((part) => part.length > 0);

    return `${head}${rest.map(upperFirst).join('')}`;
}

/**
 * The local name for a mixin import: `sw-form-field` becomes `swFormFieldMixin`.
 *
 * The suffix keeps `mixins: [swFormFieldMixin]` readable and keeps mixin names from colliding with the
 * same-named composables and stores.
 */
export function mixinLocalName(mixinName: string): string {
    return `${camelCase(mixinName)}Mixin`;
}

/** The local name for a store import: `swOrderDetail` becomes `useSwOrderDetailStore`, Pinia's convention. */
export function storeLocalName(storeId: string): string {
    return `use${upperFirst(storeId)}Store`;
}

/** The branch each `Shopware.<name>` property belongs to, and its module family. */
const FAMILY_BY_BRANCH: Record<string, string> = {
    Utils: 'shopware:utils',
    Data: 'shopware:data',
};

function singleStringArgument(node: Node): string | undefined {
    const call = node.getParentIfKind(SyntaxKind.CallExpression);

    if (!call || call.getExpression() !== node || call.getTypeArguments().length > 0) {
        return undefined;
    }

    const [argument] = call.getArguments();

    return argument?.isKind(SyntaxKind.StringLiteral) ? argument.getLiteralValue() : undefined;
}

function skipAtCall(access: Node, reason: string, specifier: string): Skip {
    return { expression: access.getParent()?.getText() ?? access.getText(), reason, specifier };
}

/**
 * The rewrite for one registry lookup, or a reason it has none.
 *
 * The key has to be a literal: `Shopware.Store.get(name)` stays as it is, because the specifier it would
 * import from is only known at runtime.
 */
function classifyRegistryLookup(
    access: Node,
    family: string,
    localNameOf: (key: string) => string,
    registry: Registry,
    enabled: EnabledFamilies,
): Candidate | Skip | undefined {
    if (!enabled.has(family)) {
        return undefined;
    }

    const key = singleStringArgument(access);
    const declaredIn = family === 'shopware:mixins' ? 'MixinContainer' : 'PiniaRootState';

    if (key === undefined) {
        return skipAtCall(access, `${family === 'shopware:mixins' ? 'mixin name' : 'store id'} is not a literal`, family);
    }

    if (!(key in registry[family].subpaths)) {
        return skipAtCall(access, `"${key}" is not declared in ${declaredIn}`, family);
    }

    const local = localNameOf(key);

    return {
        specifier: `${family}/${key}`,
        local,
        replacement: family === 'shopware:stores' ? `${local}()` : local,
        replacesCall: true,
    };
}

/** The rewrite for one `Shopware.<branch>.<member>` access, or a reason it has none. */
function classify(access: Node, registry: Registry, enabled: EnabledFamilies): Candidate | Skip | undefined {
    if (!access.isKind(SyntaxKind.PropertyAccessExpression)) {
        return undefined;
    }

    const branch = access.getExpression();

    if (!branch.isKind(SyntaxKind.PropertyAccessExpression) || branch.getExpression().getText() !== 'Shopware') {
        return undefined;
    }

    const branchName = branch.getName();
    const member = access.getName();

    if (branchName === 'Mixin' && member === 'getByName') {
        return classifyRegistryLookup(access, 'shopware:mixins', mixinLocalName, registry, enabled);
    }

    if (branchName === 'Store' && member === 'get') {
        return classifyRegistryLookup(access, 'shopware:stores', storeLocalName, registry, enabled);
    }

    const family = FAMILY_BY_BRANCH[branchName];

    if (!family || !enabled.has(family)) {
        return undefined;
    }

    if (!registry[family].exports.includes(member)) {
        return { expression: access.getText(), reason: `"${member}" is not an export of ${family}`, specifier: family };
    }

    return { specifier: family, local: member, imported: member, replacement: member, replacesCall: false };
}

function isSkip(value: Candidate | Skip): value is Skip {
    return 'reason' in value;
}

/**
 * A module-level destructuring that a named import replaces one-for-one.
 *
 * Two shapes qualify. `const { Criteria } = Shopware.Data;` becomes an import from the barrel, and
 * `const { warn } = Shopware.Utils.debug;` becomes one from that member's own subpath, which is what the
 * subpaths exist for.
 */
type Destructuring = {
    readonly statement: VariableStatement;
    readonly specifier: string;
    /** How the branch was written, e.g. `Shopware.Utils.debug`. Only used to describe the rewrite. */
    readonly branch: string;
    readonly names: { member: string; local: string }[];
};

/** The specifier a destructuring's initialiser stands for, and the names it may publish. */
function destructuringTarget(
    initializer: Node,
    registry: Registry,
    enabled: EnabledFamilies,
): { specifier: string; available: string[] } | undefined {
    if (!initializer.isKind(SyntaxKind.PropertyAccessExpression)) {
        return undefined;
    }

    const parent = initializer.getExpression();

    // `Shopware.Utils` - the barrel.
    if (parent.getText() === 'Shopware') {
        const family = FAMILY_BY_BRANCH[initializer.getName()];

        return family && enabled.has(family) ? { specifier: family, available: registry[family].exports } : undefined;
    }

    // `Shopware.Utils.debug` - one member's subpath.
    if (!parent.isKind(SyntaxKind.PropertyAccessExpression) || parent.getExpression().getText() !== 'Shopware') {
        return undefined;
    }

    const family = FAMILY_BY_BRANCH[parent.getName()];
    const member = initializer.getName();
    const available = family ? registry[family].subpaths[member] : undefined;

    if (!family || !enabled.has(family) || !available || available.length === 0) {
        return undefined;
    }

    return { specifier: `${family}/${member}`, available };
}

/**
 * The destructurings a named import replaces.
 *
 * Only module-level statements qualify, so the import lands in the scope the bindings already had. A rest
 * element, a default value or a nested pattern has no import equivalent, and neither has a name the
 * specifier does not publish.
 */
function collectDestructurings(sourceFile: SourceFile, registry: Registry, enabled: EnabledFamilies): Destructuring[] {
    const found: Destructuring[] = [];

    sourceFile.getVariableStatements().forEach((statement) => {
        const [declaration] = statement.getDeclarations();
        const initializer = declaration?.getInitializer();

        if (statement.getDeclarations().length !== 1 || !initializer) {
            return;
        }

        const target = destructuringTarget(initializer, registry, enabled);
        const pattern = declaration.getNameNode();

        if (!target || !pattern.isKind(SyntaxKind.ObjectBindingPattern)) {
            return;
        }

        const names: { member: string; local: string }[] = [];

        for (const element of pattern.getElements()) {
            const local = element.getNameNode();
            const member = element.getPropertyNameNode()?.getText() ?? element.getName();

            if (!local.isKind(SyntaxKind.Identifier) || element.getDotDotDotToken() || element.getInitializer()) {
                return;
            }

            if (!target.available.includes(member)) {
                return;
            }

            names.push({ member, local: local.getText() });
        }

        if (names.length > 0) {
            found.push({ statement, specifier: target.specifier, branch: initializer.getText(), names });
        }
    });

    return found;
}

/**
 * The comments and whitespace in front of a statement, verbatim.
 *
 * ts-morph counts leading trivia as part of the node, so both `remove()` and `replaceWithText()` take a
 * statement's comments with it. A file header such as `@sw-package` is often attached to exactly the
 * statement being replaced, so it has to be written back out.
 */
function leadingTrivia(statement: VariableStatement): string {
    const [firstComment] = statement.getLeadingCommentRanges();

    if (!firstComment) {
        return '';
    }

    return statement.getSourceFile().getFullText().slice(firstComment.getPos(), statement.getStart());
}

/**
 * Replaces each destructuring with the import that supersedes it.
 *
 * A statement that owns comments keeps its place, so a file header such as `@sw-package` stays where it
 * was: ts-morph counts leading trivia as part of the node, and a comment on its own is not a statement
 * it can be replaced by. Everything else is removed and the import goes into the file's import block,
 * where it cannot end up below a statement that already uses another of its names.
 */
function replaceDestructuringsWithImports(sourceFile: SourceFile, destructurings: Destructuring[]): boolean {
    let replacedInPlace = false;

    destructurings.forEach((entry) => {
        const trivia = leadingTrivia(entry.statement);

        if (!trivia.trim()) {
            entry.statement.remove();

            return;
        }

        const named = entry.names.map(({ member, local }) => (member === local ? member : `${member} as ${local}`));

        entry.statement.replaceWithText(`${trivia}import { ${named.join(', ')} } from '${entry.specifier}';`);
        replacedInPlace = true;
    });

    return replacedInPlace;
}

/**
 * Every name the file binds, ignoring the statements that are about to become imports.
 *
 * Excluding by span, not by name: a statement on its way out must not hide a same-named binding
 * elsewhere in the file, which is exactly the collision worth catching.
 */
function boundNames(sourceFile: SourceFile, ignoredStatements: VariableStatement[]): Set<string> {
    const names = new Set<string>();
    const ignoredSpans = ignoredStatements.map((statement) => [
        statement.getStart(),
        statement.getEnd(),
    ]);
    const isIgnored = (position: number): boolean =>
        ignoredSpans.some(
            ([
                from,
                to,
            ]) => position >= from && position < to,
        );

    const add = (name: string | undefined, position: number): void => {
        if (name && !isIgnored(position)) {
            names.add(name);
        }
    };

    // Imports carry three shapes and only the named one has `getName()`, so they are read explicitly. A
    // type-only import binds the name just as much as a value import does.
    sourceFile.getImportDeclarations().forEach((declaration) => {
        const position = declaration.getStart();

        add(declaration.getDefaultImport()?.getText(), position);
        add(declaration.getNamespaceImport()?.getText(), position);
        declaration
            .getNamedImports()
            .forEach((named) => add((named.getAliasNode() ?? named.getNameNode()).getText(), position));
    });

    [
        SyntaxKind.VariableDeclaration,
        SyntaxKind.FunctionDeclaration,
        SyntaxKind.ClassDeclaration,
        SyntaxKind.Parameter,
        SyntaxKind.BindingElement,
        SyntaxKind.TypeAliasDeclaration,
        SyntaxKind.InterfaceDeclaration,
        SyntaxKind.EnumDeclaration,
    ].forEach((kind) =>
        sourceFile.getDescendantsOfKind(kind).forEach((declaration) => {
            add((declaration as { getName?: () => string | undefined }).getName?.(), declaration.getStart());
        }),
    );

    return names;
}

/** Adds one import, reusing an existing declaration for the same specifier. */
function addImport(sourceFile: SourceFile, needed: NeededImport): void {
    const existing = sourceFile.getImportDeclaration(
        (declaration) => declaration.getModuleSpecifierValue() === needed.specifier,
    );

    if (needed.imported === undefined) {
        if (existing) {
            existing.setDefaultImport(needed.local);

            return;
        }

        insert(sourceFile, { moduleSpecifier: needed.specifier, defaultImport: needed.local });

        return;
    }

    const named = needed.local === needed.imported ? needed.imported : { name: needed.imported, alias: needed.local };

    if (existing) {
        const present = existing
            .getNamedImports()
            .some((entry) => (entry.getAliasNode() ?? entry.getNameNode()).getText() === needed.local);

        if (!present) {
            existing.addNamedImport(named);
        }

        return;
    }

    insert(sourceFile, { moduleSpecifier: needed.specifier, namedImports: [named] });
}

function insert(sourceFile: SourceFile, declaration: Parameters<SourceFile['addImportDeclaration']>[0]): void {
    if (sourceFile.getImportDeclarations().length > 0) {
        sourceFile.addImportDeclaration(declaration);

        return;
    }

    sourceFile.insertImportDeclaration(0, declaration);
}

/**
 * Rewrites one module's source. Returns the original code unchanged when nothing is migratable.
 *
 * `fileName` only steers ts-morph's parser (`.ts` versus `.js`); nothing is read from disk.
 */
export function transformSource(
    code: string,
    fileName: string,
    registry: Registry,
    enabled: EnabledFamilies = ALL_FAMILIES,
): TransformResult {
    const project = new Project({
        useInMemoryFileSystem: true,
        skipAddingFilesFromTsConfig: true,
        // ts-morph writes double quotes by default, which prettier would then rewrite in every touched file.
        manipulationSettings: { quoteKind: QuoteKind.Single },
    });
    const sourceFile = project.createSourceFile(fileName, code);

    const rewrites: Rewrite[] = [];
    const skips: Skip[] = [];
    const imports: NeededImport[] = [];

    const candidates = collectDestructurings(sourceFile, registry, enabled);
    const boundElsewhere = boundNames(
        sourceFile,
        candidates.map((entry) => entry.statement),
    );

    // A destructuring whose binding the file also gets from somewhere else cannot become an import
    // without colliding with it, so it stays as it is.
    const destructurings = candidates.filter((entry) => entry.names.every(({ local }) => !boundElsewhere.has(local)));

    candidates
        .filter((entry) => !destructurings.includes(entry))
        .forEach((entry) =>
            entry.names
                .filter(({ local }) => boundElsewhere.has(local))
                .forEach(({ local }) =>
                    skips.push({
                        expression: `const { ${local} } = ${entry.branch}`,
                        reason: `"${local}" is already bound in this file`,
                        specifier: entry.specifier.split('/')[0],
                    }),
                ),
        );

    const destructured = new Map<string, string>();

    destructurings.forEach((entry) =>
        entry.names.forEach(({ member, local }) => {
            destructured.set(`${entry.specifier} ${member}`, local);

            if (!leadingTrivia(entry.statement).trim()) {
                imports.push({ specifier: entry.specifier, local, imported: member });
            }

            rewrites.push({
                from: `const { ${member} } = ${entry.branch}`,
                to: `import { ${member} } from '${entry.specifier}'`,
                specifier: entry.specifier.split('/')[0],
            });
        }),
    );

    const taken = boundNames(
        sourceFile,
        destructurings.map((entry) => entry.statement),
    );

    // The initialiser of a destructuring that is about to become an import is itself a branch read, so
    // rewriting it too would import a name nothing goes on to use.
    const replacedSpans = destructurings.map((entry) => [
        entry.statement.getStart(),
        entry.statement.getEnd(),
    ]);

    sourceFile
        .getDescendantsOfKind(SyntaxKind.PropertyAccessExpression)
        .sort((left, right) => left.getStart() - right.getStart())
        .forEach((access) => {
            if (access.wasForgotten()) {
                return;
            }

            const start = access.getStart();

            if (
                replacedSpans.some(
                    ([
                        from,
                        to,
                    ]) => start >= from && start < to,
                )
            ) {
                return;
            }

            const classified = classify(access, registry, enabled);

            if (!classified) {
                return;
            }

            if (isSkip(classified)) {
                skips.push(classified);

                return;
            }

            // The file already destructured this member, so reuse that binding instead of importing twice.
            const existingLocal = destructured.get(`${classified.specifier} ${classified.imported ?? 'default'}`);

            if (!existingLocal && taken.has(classified.local)) {
                skips.push({
                    expression: access.getText(),
                    reason: `"${classified.local}" is already bound in this file`,
                    specifier: classified.specifier.split('/')[0],
                });

                return;
            }

            const local = existingLocal ?? classified.local;
            const target = classified.replacesCall ? access.getParentOrThrow() : access;
            const from = target.getText();
            const replacement = classified.replacement.replace(classified.local, local);

            target.replaceWithText(replacement);

            rewrites.push({ from, to: replacement, specifier: classified.specifier.split('/')[0] });

            if (!existingLocal) {
                imports.push({ specifier: classified.specifier, local, imported: classified.imported });
            }
        });

    if (rewrites.length === 0) {
        return { code, rewrites, skips };
    }

    replaceDestructuringsWithImports(sourceFile, destructurings);

    [...imports]
        .sort(
            (left, right) =>
                left.specifier.localeCompare(right.specifier) || (left.imported ?? '').localeCompare(right.imported ?? ''),
        )
        .forEach((needed) => addImport(sourceFile, needed));

    return { code: sourceFile.getFullText(), rewrites, skips };
}
