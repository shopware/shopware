/**
 * @sw-package framework
 *
 * Build-time discovery of the export names each `shopware:*` module publishes.
 *
 * Rollup needs the named exports of a generated module up front, and only the Administration sources can
 * supply them at build time. They are read out of the files that already define the contract instead of
 * a hand-kept list, so the modules stay in step with the global object on their own:
 *
 * - `Shopware.Utils` and `Shopware.Data` are `export default { ... }` object literals; their keys are the
 *   export names.
 * - `Shopware.Mixin` and `Shopware.Store` are runtime registries whose declared contract is the
 *   `MixinContainer` and `PiniaRootState` interfaces - what is typed is what can be imported.
 */

import fs from 'node:fs';
import path from 'node:path';
import ts from 'typescript';

const UTILS_SOURCE = 'src/core/service/util.service.ts';
const DATA_SOURCE = 'src/core/data/index.js';
const GLOBAL_TYPES_SOURCE = 'src/global.types.ts';

type SourceKeyReader = {
    /** Administration-relative file the keys are read from. */
    readonly file: string;
    readonly read: (administrationRoot: string) => string[];
};

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

/** The property names of a module's `export default { ... }` object literal. */
function defaultExportKeys(relativePath: string): SourceKeyReader {
    return {
        file: relativePath,
        read: (administrationRoot) => {
            const sourceFile = parse(administrationRoot, relativePath);
            const literal = sourceFile.statements.find(ts.isExportAssignment)?.expression;

            if (!literal || !ts.isObjectLiteralExpression(literal)) {
                throw new Error(`Expected "${relativePath}" to have an "export default { ... }" object literal.`);
            }

            return literal.properties
                .map((property) =>
                    ts.isPropertyAssignment(property) || ts.isShorthandPropertyAssignment(property)
                        ? memberName(property.name)
                        : undefined,
                )
                .filter((name): name is string => name !== undefined);
        },
    };
}

/** The member names of a declared `interface`, e.g. `MixinContainer` or `PiniaRootState`. */
function interfaceKeys(relativePath: string, interfaceName: string): SourceKeyReader {
    return {
        file: relativePath,
        read: (administrationRoot) => {
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

            visit(parse(administrationRoot, relativePath));

            if (keys.length === 0) {
                throw new Error(`Found no members of "interface ${interfaceName}" in "${relativePath}".`);
            }

            return keys;
        },
    };
}

/**
 * Where each `shopware:*` module's keys come from.
 *
 * Keyed by the same specifiers as `VIRTUAL_MODULES`; `definitions.spec.ts` keeps the two in step.
 */
const SOURCE_KEY_READERS: Record<string, SourceKeyReader> = {
    'shopware:utils': defaultExportKeys(UTILS_SOURCE),
    'shopware:data': defaultExportKeys(DATA_SOURCE),
    'shopware:mixins': interfaceKeys(GLOBAL_TYPES_SOURCE, 'MixinContainer'),
    'shopware:stores': interfaceKeys(GLOBAL_TYPES_SOURCE, 'PiniaRootState'),
};

/** @private The specifiers `source-keys.ts` can read keys for. */
export const SOURCE_KEY_SPECIFIERS = Object.keys(SOURCE_KEY_READERS);

function readerFor(specifier: string): SourceKeyReader {
    const reader = SOURCE_KEY_READERS[specifier];

    if (!reader) {
        throw new Error(`No source of export names is declared for "${specifier}".`);
    }

    return reader;
}

/** @private Every source key a `shopware:*` module publishes, in declaration order. */
export function readSourceKeys(specifier: string, administrationRoot: string): string[] {
    return readerFor(specifier).read(administrationRoot);
}

/**
 * @private
 *
 * The absolute path of the file a module's export names are read from.
 *
 * The plugin watches it, so adding a store or mixin regenerates the module instead of serving a stale one.
 */
export function sourceKeyFile(specifier: string, administrationRoot: string): string {
    return path.join(administrationRoot, readerFor(specifier).file);
}
