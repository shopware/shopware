/**
 * @sw-package framework
 */

/**
 * Where does the module-level code around the component options go?
 *
 * `<script setup>` runs once per instance: a shared `Map` would become one per instance, a listener
 * would register on every mount. And the build transform accepts no second `<script>`. So code that
 * has to run once per module load moves into a sibling module that exports what the setup body
 * reads. Imports stay in the SFC, which Vue hoists out of `setup()` anyway. A `const { X } =
 * Shopware…` nothing reads anymore (the `Component` the conversion made obsolete) is dropped.
 */

import { traverse } from '@babel/core';
import { parse } from '@babel/parser';
import type * as t from '@babel/types';
import { getBindingIdentifiers, traverseFast } from '@babel/types';
import { packageName } from './ast';

type PreludeStatement = {
    node: t.Statement;
    /** Comments and blank lines between the previous statement and this one. */
    leading: string;
};

type PreludeSplit = {
    header: string;
    /** Author imports (and type declarations), placed after the generated imports. */
    declarations: string;
    /** `<source>:<name>` of every named import `declarations` carries unaliased. */
    namedImports: Set<string>;
    moduleScript: string | null;
};

type Placement = 'setup' | 'module' | 'both' | 'drop';

const SHOPWARE_READ = /^Shopware(?:\.[A-Za-z_$][\w$]*)*$/;

function isTypeOnly(node: t.Node): boolean {
    switch (node.type) {
        case 'TSTypeAliasDeclaration':
        case 'TSInterfaceDeclaration':
        case 'TSDeclareFunction':
            return true;
        case 'VariableDeclaration':
        case 'TSModuleDeclaration':
        case 'TSEnumDeclaration':
        case 'ClassDeclaration':
            return node.declare === true;
        case 'ExportNamedDeclaration':
            return (
                node.exportKind === 'type' ||
                (node.declaration !== null && node.declaration !== undefined && isTypeOnly(node.declaration))
            );
        default:
            return false;
    }
}

function declaredNames(node: t.Statement): { values: string[]; types: string[] } {
    const declaration = node.type === 'ExportNamedDeclaration' ? node.declaration : node;

    if (!declaration) {
        return { values: [], types: [] };
    }

    if (declaration.type === 'TSTypeAliasDeclaration' || declaration.type === 'TSInterfaceDeclaration') {
        return { values: [], types: [declaration.id.name] };
    }

    const values =
        declaration.type === 'TSEnumDeclaration'
            ? [declaration.id.name]
            : Object.keys(getBindingIdentifiers(declaration as t.Node));

    // A class or enum is a type as well as a value.
    const types = declaration.type === 'ClassDeclaration' || declaration.type === 'TSEnumDeclaration' ? values : [];

    return { values, types };
}

/** Every identifier name, types included — a deliberate over-approximation. */
function mentionedNames(nodes: t.Node[]): Set<string> {
    const names = new Set<string>();

    for (const node of nodes) {
        traverseFast(node, (descendant) => {
            if (descendant.type === 'Identifier') {
                names.add(descendant.name);
            }
        });
    }

    return names;
}

/** Free value references of the rendered setup body, plus every identifier it mentions (for types). */
function setupReferences(setupScript: string): { values: Set<string>; mentions: Set<string> } {
    const ast = parse(setupScript, { sourceType: 'module', plugins: ['typescript'] });
    const values = new Set<string>();

    traverse(ast, {
        Program(path) {
            Object.keys(path.scope.globals).forEach((name) => values.add(name));
            path.stop();
        },
    });

    return { values, mentions: mentionedNames([ast.program]) };
}

function isUnreadShopwareRead(statement: PreludeStatement, source: string, read: ReadonlySet<string>): boolean {
    const { node } = statement;

    if (node.type !== 'VariableDeclaration' || node.kind !== 'const') {
        return false;
    }

    return node.declarations.every(
        (declarator) =>
            declarator.init !== null &&
            declarator.init !== undefined &&
            SHOPWARE_READ.test(source.slice(declarator.init.start as number, declarator.init.end as number)) &&
            Object.keys(getBindingIdentifiers(declarator.id)).every((name) => !read.has(name)),
    );
}

function packageDocblock(source: string): string {
    const domain = packageName(source);

    return domain ? `/**\n * @sw-package ${domain}\n */` : '';
}

function namedList(values: string[], types: string[]): string {
    return [...values, ...types.filter((name) => !values.includes(name)).map((name) => `type ${name}`)].join(', ');
}

function splitPrelude(input: {
    source: string;
    statements: PreludeStatement[];
    header: string[];
    setupScript: string;
    moduleSpecifier: string;
}): PreludeSplit {
    const { source, statements } = input;
    const setupRefs = setupReferences(input.setupScript);
    const text = (node: t.Node): string => source.slice(node.start as number, node.end as number);

    const declarations = statements.filter(({ node }) => node.type !== 'ImportDeclaration');
    const otherMentions = (except: PreludeStatement): Set<string> =>
        mentionedNames(declarations.filter((statement) => statement !== except).map(({ node }) => node));

    const kept = declarations.filter(
        (statement) => !isUnreadShopwareRead(statement, source, new Set([...setupRefs.values, ...otherMentions(statement)])),
    );
    const runtime = kept.filter(({ node }) => !isTypeOnly(node));
    const hasModule = runtime.length > 0;
    const moduleMentions = mentionedNames(kept.map(({ node }) => node));

    const placement = (statement: PreludeStatement): Placement => {
        if (statement.node.type === 'ImportDeclaration') {
            const locals = statement.node.specifiers.map((specifier) => specifier.local.name);
            const inModule = hasModule && locals.some((name) => moduleMentions.has(name));
            const inSetup = !inModule || locals.some((name) => setupRefs.mentions.has(name));

            return inModule && inSetup ? 'both' : inModule ? 'module' : 'setup';
        }

        if (!kept.includes(statement)) {
            return 'drop';
        }

        return hasModule ? 'module' : 'setup';
    };

    let setupCode = '';
    let moduleCode = '';

    // Raw leading text keeps the authored spacing; the comments of a dropped statement go with it.
    for (const statement of statements) {
        const where = placement(statement);

        if (where === 'setup' || where === 'both') {
            setupCode += `${statement.leading}${text(statement.node)}`;
        }

        if (where === 'module' || where === 'both') {
            moduleCode += `${where === 'both' ? '\n' : statement.leading}${text(statement.node)}`;
        }
    }

    const header = input.header.join('\n\n');
    const namedImports = new Set(
        statements
            .filter((statement) => ['setup', 'both'].includes(placement(statement)))
            .flatMap(({ node }) =>
                node.type === 'ImportDeclaration'
                    ? node.specifiers
                          .filter(
                              (specifier): specifier is t.ImportSpecifier =>
                                  specifier.type === 'ImportSpecifier' &&
                                  specifier.importKind !== 'type' &&
                                  specifier.imported.type === 'Identifier' &&
                                  specifier.imported.name === specifier.local.name,
                          )
                          .map((specifier) => `${node.source.value}:${specifier.local.name}`)
                    : [],
            ),
    );

    if (!hasModule) {
        return { header, declarations: setupCode.trim(), namedImports, moduleScript: null };
    }

    const exported = new Set(
        kept.flatMap(({ node }) => {
            if (node.type !== 'ExportNamedDeclaration') {
                return [];
            }

            if (node.declaration) {
                const names = declaredNames(node);

                return [...names.values, ...names.types];
            }

            return node.source
                ? []
                : node.specifiers.map(({ exported: name }) => (name.type === 'Identifier' ? name.name : name.value));
        }),
    );
    const values = runtime.flatMap(({ node }) => declaredNames(node).values).filter((name) => setupRefs.values.has(name));
    const types = kept.flatMap(({ node }) => declaredNames(node).types).filter((name) => setupRefs.mentions.has(name));
    const toExport = {
        values: values.filter((name) => !exported.has(name)),
        types: types.filter((name) => !exported.has(name)),
    };
    const imported = namedList(values, types);
    const exportList = namedList(toExport.values, toExport.types);

    return {
        header,
        namedImports,
        declarations: [
            setupCode.trim(),
            imported ? `import { ${imported} } from '${input.moduleSpecifier}';` : `import '${input.moduleSpecifier}';`,
        ]
            .filter(Boolean)
            .join('\n'),
        moduleScript: [
            packageDocblock(source),
            moduleCode.trim(),
            exportList ? `/**\n * @private\n */\nexport { ${exportList} };` : '',
        ]
            .filter(Boolean)
            .join('\n\n'),
    };
}

export { splitPrelude, type PreludeSplit, type PreludeStatement };
