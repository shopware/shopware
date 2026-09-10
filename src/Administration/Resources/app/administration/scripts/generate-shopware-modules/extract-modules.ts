/**
 * @sw-package framework
 *
 * Reads the `shopware:*` module registry out of the sources that already define it.
 *
 * `Shopware.Utils` and `Shopware.Data` are `export default { ... }` object literals, so their keys are
 * the barrel's exports and each key is also a subpath. `Shopware.Mixin` and `Shopware.Store` are runtime
 * registries whose declared contract is the `MixinContainer` and `PiniaRootState` interfaces, so those
 * give the subpaths for the two registry-backed modules.
 *
 * Only the generator reads source. The Vite plugin reads the generated `shopware-modules.json`, which is
 * checked in, so a new specifier shows up in a pull request diff instead of appearing at build time.
 */

import fs from 'node:fs';
import path from 'node:path';
import ts from 'typescript';

/**
 * What one `shopware:*` module publishes.
 *
 * `exports` are the barrel's named exports, empty for the registry-backed modules, which have no barrel.
 * `subpaths` maps a subpath key to the names that subpath publishes alongside its default export. A key
 * with an empty list is default-only: a class, a mixin, or a store, none of which may be destructured.
 */
export type ModuleRegistryEntry = {
    exports: string[];
    subpaths: Record<string, string[]>;
};

export type ModuleRegistry = Record<string, ModuleRegistryEntry>;

const UTILS_SOURCE = 'src/core/service/util.service.ts';
const DATA_SOURCE = 'src/core/data/index.js';
const GLOBAL_TYPES_SOURCE = 'src/global.types.ts';

function parse(administrationRoot: string, relativePath: string): ts.SourceFile {
    const filePath = path.join(administrationRoot, relativePath);

    return ts.createSourceFile(filePath, fs.readFileSync(filePath, 'utf8'), ts.ScriptTarget.Latest, true);
}

function memberName(name: ts.PropertyName | undefined): string | undefined {
    if (name && (ts.isIdentifier(name) || ts.isStringLiteral(name))) {
        return name.text;
    }

    return undefined;
}

function objectLiteralKeys(literal: ts.ObjectLiteralExpression): string[] {
    return literal.properties
        .map((property) =>
            ts.isPropertyAssignment(property) || ts.isShorthandPropertyAssignment(property)
                ? memberName(property.name)
                : undefined,
        )
        .filter((name): name is string => name !== undefined);
}

/** The `export default { ... }` literal of a module, which is what the global object holds. */
function defaultExportLiteral(sourceFile: ts.SourceFile, relativePath: string): ts.ObjectLiteralExpression {
    const literal = sourceFile.statements.find(ts.isExportAssignment)?.expression;

    if (!literal || !ts.isObjectLiteralExpression(literal)) {
        throw new Error(`Expected "${relativePath}" to have an "export default { ... }" object literal.`);
    }

    return literal;
}

/**
 * The members of every top-level `export const <name> = { ... }` in a module.
 *
 * A utility namespace such as `debug` is declared that way, and its members are what
 * `shopware:utils/debug` publishes as named exports.
 */
function namedObjectExports(sourceFile: ts.SourceFile): Record<string, string[]> {
    const namespaces: Record<string, string[]> = {};

    sourceFile.statements.forEach((statement) => {
        const isExported = statement.modifiers?.some((modifier) => modifier.kind === ts.SyntaxKind.ExportKeyword);

        if (!ts.isVariableStatement(statement) || !isExported) {
            return;
        }

        statement.declarationList.declarations.forEach((declaration) => {
            const initializer = declaration.initializer;

            if (ts.isIdentifier(declaration.name) && initializer && ts.isObjectLiteralExpression(initializer)) {
                namespaces[declaration.name.text] = objectLiteralKeys(initializer);
            }
        });
    });

    return namespaces;
}

/** The member names of a declared `interface`, e.g. `MixinContainer` or `PiniaRootState`. */
function interfaceKeys(sourceFile: ts.SourceFile, interfaceName: string): string[] {
    const keys: string[] = [];

    const visit = (node: ts.Node): void => {
        if (ts.isInterfaceDeclaration(node) && node.name.text === interfaceName) {
            node.members.forEach((member) => {
                const name = ts.isPropertySignature(member) ? memberName(member.name) : undefined;

                if (name !== undefined) {
                    keys.push(name);
                }
            });
        }

        ts.forEachChild(node, visit);
    };

    visit(sourceFile);

    if (keys.length === 0) {
        throw new Error(`Found no members of "interface ${interfaceName}".`);
    }

    return keys;
}

function defaultOnlySubpaths(keys: string[]): Record<string, string[]> {
    return Object.fromEntries(
        keys.map((key) => [
            key,
            [],
        ]),
    );
}

/** Builds the whole registry from the Administration sources. */
export function extractModuleRegistry(administrationRoot: string): ModuleRegistry {
    const utilsSource = parse(administrationRoot, UTILS_SOURCE);
    const utilsKeys = objectLiteralKeys(defaultExportLiteral(utilsSource, UTILS_SOURCE));
    const utilsNamespaces = namedObjectExports(utilsSource);

    const dataSource = parse(administrationRoot, DATA_SOURCE);
    const dataKeys = objectLiteralKeys(defaultExportLiteral(dataSource, DATA_SOURCE));

    const globalTypes = parse(administrationRoot, GLOBAL_TYPES_SOURCE);

    return {
        'shopware:utils': {
            exports: utilsKeys,
            subpaths: Object.fromEntries(
                utilsKeys.map((key) => [
                    key,
                    utilsNamespaces[key] ?? [],
                ]),
            ),
        },
        'shopware:data': {
            exports: dataKeys,
            subpaths: defaultOnlySubpaths(dataKeys),
        },
        'shopware:mixins': {
            exports: [],
            subpaths: defaultOnlySubpaths(interfaceKeys(globalTypes, 'MixinContainer')),
        },
        'shopware:stores': {
            exports: [],
            subpaths: defaultOnlySubpaths(interfaceKeys(globalTypes, 'PiniaRootState')),
        },
    };
}
