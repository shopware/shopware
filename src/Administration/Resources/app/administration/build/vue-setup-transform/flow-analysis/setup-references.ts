/**
 * @sw-package framework
 */

/**
 * Locates every identifier occurrence of a set of top-level setup names, for the base-mode rename pass.
 *
 * Base lowering renames each top-level runtime binding to its `__swSetupAuthor_<name>` alias so the
 * generated footer can re-declare the original name. That means finding *all* occurrences of those
 * names - reads, writes, declaration ids, `typeof` queries, and type references to runtime bindings such
 * as an `enum` - while leaving alone the places where the same text is not that binding: shadowed
 * scopes, member-access property names, static object keys, and purely type-level names.
 *
 * Built on the generic traversal primitives in utils/.
 *
 * Vocabulary used below:
 * - **`names`**: the top-level setup bindings being renamed.
 * - **`shadowedBindings`**: names re-declared locally within the subtree walked so far (a function
 *   parameter, or a `const`/`function`/`catch` in its body). An occurrence of a shadowed name refers to
 *   that local rather than the top-level binding, so it must not be renamed.
 * - **entity name**: a (possibly qualified) type name - `A`, or `A.B.C` (a Babel `TSQualifiedName`);
 *   its *leftmost* identifier (`A`) is the one that resolves to a binding.
 */

import type { Identifier, JSXIdentifier, Node as BabelNode, Statement } from '@babel/types';
import { forEachPatternIdentifier } from '../utils/babel-patterns';
import { childBabelEntries, childBabelNodes, isFunctionLikeNode, isTypeKey } from '../utils/ast-traversal';
import { isValueReadPosition } from './identifier-position';

/**
 * Returns the leftmost identifier of a type **entity name** (`A` in `A`, `A.B`, or `A.B.C`).
 *
 * That leftmost identifier is the one that resolves to a value/type binding; the rest are property
 * accesses on it. Returns null for entity names that are not a plain identifier chain.
 */
function getLeftmostEntityIdentifier(entity: BabelNode | null | undefined): Identifier | null {
    if (!entity) {
        return null;
    }

    if (entity.type === 'Identifier') {
        return entity;
    }

    if (entity.type === 'TSQualifiedName') {
        return getLeftmostEntityIdentifier(entity.left);
    }

    return null;
}

/**
 * Whether every identifier below this node lives in type space.
 *
 * Type-declaration statements (`interface X {…}`, `type Y = …`) are reached through ordinary child
 * keys (`declaration`/`body`), not the `typeAnnotation`-style keys `isTypeKey` recognises, so their
 * members would otherwise be walked as value positions and their keys wrongly renamed. Once inside
 * such a node the only value reference is a `typeof` query, which is matched before recursion.
 */
function isTypeDeclarationContainer(node: BabelNode): boolean {
    return node.type === 'TSInterfaceDeclaration' || node.type === 'TSTypeAliasDeclaration';
}

/**
 * Collects the only names hoisted to the enclosing *function* scope: `var` declarations, wherever they
 * appear in the body.
 *
 * Descends through the body but stops at nested function boundaries. `let`/`const`/`class` and - in
 * strict module code - function declarations are block-scoped, so they are tracked per-block by the walk
 * instead; collecting them function-wide would shadow the same name in a sibling block.
 */
function collectHoistedDeclarations(node: BabelNode | null | undefined, into: Set<string>, isRoot = true): void {
    if (!node) {
        return;
    }

    // Nested functions open their own scope; do not pull their locals into this one.
    if (!isRoot && isFunctionLikeNode(node)) {
        return;
    }

    if (node.type === 'VariableDeclaration' && node.kind === 'var') {
        node.declarations.forEach((declaration) =>
            forEachPatternIdentifier(declaration.id, (identifier) => into.add(identifier.name)),
        );
    }

    childBabelNodes(node, isTypeKey).forEach((child) => collectHoistedDeclarations(child, into, false));
}

/**
 * Adds the names a statement list block-scopes: `let`/`const`/`class` and function declarations declared
 * *directly* in it.
 *
 * Only the direct statements - nested blocks scope their own declarations, tracked when the walk descends
 * into them. Function declarations are hoisted within their block, so scanning the whole list up front
 * shadows a call that textually precedes the declaration too.
 */
function collectBlockScopedDeclarations(statements: Statement[], into: Set<string>): void {
    statements.forEach((statement) => {
        if (statement.type === 'VariableDeclaration' && (statement.kind === 'let' || statement.kind === 'const')) {
            statement.declarations.forEach((declaration) =>
                forEachPatternIdentifier(declaration.id, (identifier) => into.add(identifier.name)),
            );
        }

        if ((statement.type === 'ClassDeclaration' || statement.type === 'FunctionDeclaration') && statement.id) {
            into.add(statement.id.name);
        }
    });
}

/**
 * One identifier a rename pass must rewrite, together with the exact text to put in its place.
 *
 * For most occurrences `replacement` is just the alias, but a shorthand object property (`{ foo }`)
 * must expand to `{ foo: alias }` - renaming the single shared identifier in place would silently
 * rewrite the property *key* as well, so the collector emits the expanded replacement here.
 */
/**
 * How much of the surrounding syntax an occurrence's replacement text has to reproduce.
 *
 * A plain occurrence can be swapped for the alias, but two forms share one source range with a name
 * that must survive, so the replacement has to spell both out. Which form an occurrence is, is an
 * analysis fact; what the alias looks like is the lowerer's business.
 */
type SetupRenameExpansion = 'plain' | 'shorthand-property' | 'shorthand-export';

type SetupRenameTarget = {
    // A JSXIdentifier tag (`<Foo />`) is renamed too; only its source range is used, so both node kinds fit.
    node: Identifier | JSXIdentifier;
    localName: string;
    expansion: SetupRenameExpansion;
};

/**
 * Whether an identifier is the value of a shorthand object property without a default (`{ foo }`).
 *
 * The property key and value share one source range, so a plain rename would rewrite the key too; the
 * replacement must expand the shorthand to `key: alias`.
 *
 * The defaulted form (`{ foo = 1 }`) shares that range too, but Babel nests the identifier as an
 * `AssignmentPattern`'s `left`, so the leaf cannot recognise it from its parent alone. It is handled
 * by the dedicated branch in `collectSetupRenameTargets`, which reports the same expansion.
 */
function isShorthandPropertyValue(node: Identifier, parent: BabelNode | null): boolean {
    return parent?.type === 'ObjectProperty' && parent.shorthand === true && parent.value === node;
}

/**
 * Whether an identifier is the local side of a shorthand type export (`export type { C }`).
 *
 * `local` and `exported` share one source range there, so renaming the local in place would drop the
 * public export name; the replacement must expand to `alias as C`. A non-shorthand `export type { C as
 * Public }` needs no expansion - the local and public names already occupy separate ranges.
 */
function isShorthandExportSpecifier(node: Identifier, parent: BabelNode | null): boolean {
    return (
        parent?.type === 'ExportSpecifier' &&
        parent.local === node &&
        parent.exported.type === 'Identifier' &&
        parent.exported.name === node.name
    );
}

/**
 * Collects EVERY identifier occurrence of the given top-level names that a rename pass must touch,
 * each paired with the name it refers to and the syntax its replacement has to reproduce.
 *
 * A rename must cover more than reads: it also has to catch declaration ids, write targets, `typeof name`
 * type queries, and type references to a runtime binding — while still skipping shadowed occurrences,
 * member-access property names, static object keys, and purely type-level names. Occurrences inside
 * scopes that re-declare the name are left untouched (they refer to the local, not the renamed top-level
 * binding).
 *
 * No replacement text is produced here: the alias scheme belongs to the lowerer that declares the
 * aliases, so this reports `expansion` and lets the lowerer render it.
 */
function collectSetupRenameTargets(root: BabelNode | null | undefined, names: Set<string>): SetupRenameTarget[] {
    const targets: SetupRenameTarget[] = [];

    /** Whether this occurrence refers to the top-level binding rather than a local of the same name. */
    function renames(name: string, shadowedBindings: Set<string>): boolean {
        return names.has(name) && !shadowedBindings.has(name);
    }

    /**
     * `shadowedBindings` and `inTypePosition` are the only genuine recursion state; `names` and
     * `targets` are closed over so they are not threaded through every call site.
     */
    function visit(
        node: BabelNode | null | undefined,
        shadowedBindings: Set<string>,
        parent: BabelNode | null,
        inTypePosition: boolean,
    ): void {
        if (!node) {
            return;
        }

        // Inside type positions only `typeof name` reads the value binding; every other identifier there
        // is a type name or member key and must not be renamed.
        if (!inTypePosition && node.type === 'Identifier' && renames(node.name, shadowedBindings)) {
            // Value reads/writes plus declaration ids; static keys and member property names still excluded.
            const isDeclarationId =
                parent !== null &&
                'id' in parent &&
                (parent as { id?: BabelNode }).id === node &&
                (parent.type === 'VariableDeclarator' ||
                    parent.type === 'FunctionDeclaration' ||
                    parent.type === 'ClassDeclaration');

            if (isDeclarationId || isValueReadPosition(node, parent)) {
                targets.push({
                    node,
                    localName: node.name,
                    expansion: (() => {
                        if (isShorthandPropertyValue(node, parent)) {
                            return 'shorthand-property';
                        }

                        return isShorthandExportSpecifier(node, parent) ? 'shorthand-export' : 'plain';
                    })(),
                });
            }
        }

        // JSX component tags: `<Foo />`, `</Foo>`, `<Foo.Bar />`. The tag name is a JSXIdentifier (or the
        // root of a JSXMemberExpression), not a plain Identifier, so it needs its own branch. Intrinsic
        // lowercase tags (`<div>`) are string elements, not bindings, and are left alone; a capitalized
        // standalone tag or any member-expression root resolves to a binding and must be renamed.
        if (!inTypePosition && node.type === 'JSXIdentifier' && renames(node.name, shadowedBindings)) {
            const isStandaloneTag = parent?.type === 'JSXOpeningElement' || parent?.type === 'JSXClosingElement';
            const isMemberRoot = parent?.type === 'JSXMemberExpression' && parent.object === node;

            if ((isStandaloneTag && /^[A-Z]/.test(node.name)) || isMemberRoot) {
                targets.push({ node, localName: node.name, expansion: 'plain' });
            }

            return;
        }

        // `typeof count` reads the value binding from type space and must be renamed with it.
        if (node.type === 'TSTypeQuery') {
            const identifier = getLeftmostEntityIdentifier(node.exprName);

            if (identifier && renames(identifier.name, shadowedBindings)) {
                targets.push({ node: identifier, localName: identifier.name, expansion: 'plain' });
            }

            return;
        }

        // A type reference naming a *runtime* binding must be renamed with it. Only enums (and classes)
        // land in `names` while also being usable as a type, so `defineProps<{ kind: Kind }>()` next to
        // `enum Kind` has to follow the declaration to `__swSetupAuthor_Kind` - otherwise the alias is
        // declared and the type reference is left dangling. Purely type-level names (interfaces, type
        // aliases) are never runtime bindings, so they are never in `names` and stay untouched.
        if (node.type === 'TSTypeReference') {
            const identifier = getLeftmostEntityIdentifier(node.typeName);

            if (identifier && renames(identifier.name, shadowedBindings)) {
                targets.push({ node: identifier, localName: identifier.name, expansion: 'plain' });
            }

            return;
        }

        const childShadowedBindings = new Set(shadowedBindings);

        // A function scope introduces its parameters and its hoisted (`var` / function) declarations for
        // the whole body. Block-scoped declarations are added per block below, so an inner-block `const`
        // no longer shadows the same name in a sibling block.
        if (isFunctionLikeNode(node)) {
            node.params.forEach((param) =>
                forEachPatternIdentifier(param, (identifier) => childShadowedBindings.add(identifier.name)),
            );

            collectHoistedDeclarations(node.body, childShadowedBindings);
        }

        // A named function/class *expression* binds its own name only inside its body (self-reference),
        // so shadow it for the subtree - unlike a declaration, whose name is the renamed enclosing
        // binding. Without this, `function helper() { return helper; }` next to a top-level `helper`
        // rewrites the self-reference to the outer alias.
        if ((node.type === 'FunctionExpression' || node.type === 'ClassExpression') && node.id) {
            childShadowedBindings.add(node.id.name);
        }

        // A block scopes its own `let`/`const`/`class`; a `catch` its param; a `for` its loop bindings -
        // each visible only within that node's subtree.
        if (node.type === 'BlockStatement' || node.type === 'StaticBlock') {
            collectBlockScopedDeclarations(node.body, childShadowedBindings);
        }

        if (node.type === 'CatchClause' && node.param) {
            forEachPatternIdentifier(node.param, (identifier) => childShadowedBindings.add(identifier.name));
        }

        if (node.type === 'ForStatement' || node.type === 'ForInStatement' || node.type === 'ForOfStatement') {
            const loopInit = node.type === 'ForStatement' ? node.init : node.left;

            if (loopInit?.type === 'VariableDeclaration' && (loopInit.kind === 'let' || loopInit.kind === 'const')) {
                loopInit.declarations.forEach((declaration) =>
                    forEachPatternIdentifier(declaration.id, (identifier) => childShadowedBindings.add(identifier.name)),
                );
            }
        }

        const childInTypePosition = inTypePosition || isTypeDeclarationContainer(node);

        // A shorthand property with a default (`{ foo = 1 }`) nests the declared identifier one level
        // deeper, as the AssignmentPattern's `left` - but that identifier still shares its source range
        // with the property KEY, exactly like the plain `{ foo }` form. So it needs the same expansion
        // to `foo: alias`; renaming it in place would rewrite the key too and the destructure would read
        // a property that does not exist, silently yielding the default forever.
        //
        // Handled here rather than at the leaf because the leaf's parent is the AssignmentPattern, which
        // carries no hint that it sits inside a shorthand property. The `key` child is skipped
        // deliberately: it covers the same source range as `left`, so visiting it could only duplicate
        // the edit. Only `right` (the default expression) still needs the ordinary walk.
        if (node.type === 'ObjectProperty' && node.shorthand && node.value.type === 'AssignmentPattern') {
            const declared = node.value.left;

            if (!inTypePosition && declared.type === 'Identifier' && renames(declared.name, shadowedBindings)) {
                targets.push({
                    node: declared,
                    localName: declared.name,
                    expansion: 'shorthand-property',
                });
            }

            visit(node.value.right, childShadowedBindings, node.value, childInTypePosition);

            return;
        }

        childBabelEntries(node).forEach(({ node: child, key }) =>
            visit(child, childShadowedBindings, node, childInTypePosition || isTypeKey(key)),
        );
    }

    visit(root, new Set<string>(), null, false);

    return targets;
}

/**
 * @private
 */
export { type SetupRenameExpansion, type SetupRenameTarget, collectSetupRenameTargets };
