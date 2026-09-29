/**
 * @sw-package framework
 *
 * Extracts the `shopware:*` registry from the sources that define each global branch.
 *
 * The four branches are written down in three different shapes, so each needs its own matcher.
 *
 * `Shopware.Utils` — src/core/service/util.service.ts
 * Every namespace is a top-level exported const, and a default-export literal re-lists them by shorthand:
 *
 *     export const debug = { warn: warn, error: error };
 *     export default { createId, throttle, object, debug, format, … };
 *
 *     → shopware:utils           named exports { createId, throttle, object, debug, format, … }
 *       shopware:utils/debug     default is the namespace, named exports { warn, error }
 *       shopware:utils/createId  default only, because a function has no const object to read
 *
 * Two passes joined by name in `extractModuleRegistry`: the literal gives the named exports and the set
 * of subpath keys, the consts give each subpath its own members.
 *
 * `Shopware.Data` — src/core/data/index.js
 * One default-export literal of imported classes and nothing else, so the first pass alone covers it:
 *
 *     export default { ChangesetGenerator, Criteria, Entity, EntityCollection, … };
 *
 *     → shopware:data           named exports { ChangesetGenerator, Criteria, Entity, … }
 *       shopware:data/Criteria  default only, like every class subpath
 *
 * `Shopware.Mixin` — src/global.types.ts, `interface MixinContainer`
 * A runtime registry, so there is no literal to read and the declared contract is the type itself:
 *
 *     declare global {
 *         interface MixinContainer {
 *             notification: typeof NotificationMixin;
 *             'sw-form-field': typeof SwFormFieldMixin;
 *         }
 *     }
 *
 *     → shopware:mixins/notification   default only
 *       shopware:mixins/sw-form-field  default only; a hyphenated key arrives as a string literal
 *
 * `Shopware.Store` — src/global.types.ts, `interface PiniaRootState`
 * The same shape for the same reason:
 *
 *     declare global {
 *         interface PiniaRootState {
 *             cmsPage: CmsPageStore;
 *             swOrderDetail: SwOrderDetailStore;
 *         }
 *     }
 *
 *     → shopware:stores/swOrderDetail  default only, and that default is a composable
 *
 * Neither registry family publishes a root import: resolving one would have to resolve every entry. And
 * both are only as honest as the interface — a key nobody registers passes here and throws on import.
 *
 * Only this generator parses source. Vite reads the checked-in `shopware-modules.json`.
 */

import fs from 'node:fs';
import path from 'node:path';
import ts from 'typescript';
import type { ModuleRegistry } from '../../build/vite-plugins/virtual-shopware-modules/definitions';

const UTILS_SOURCE = 'src/core/service/util.service.ts';
const DATA_SOURCE = 'src/core/data/index.js';
const GLOBAL_TYPES_SOURCE = 'src/global.types.ts';

/**
 * One source file as a syntax tree.
 *
 * Syntax only: no program, no type checker, so nothing here resolves an import or a type. Every matcher
 * below recognises a literal shape in the file, which is why a refactor that keeps the meaning but
 * changes the shape drops entries rather than failing.
 */
function parse(administrationRoot: string, relativePath: string): ts.SourceFile {
    const filePath = path.join(administrationRoot, relativePath);

    return ts.createSourceFile(filePath, fs.readFileSync(filePath, 'utf8'), ts.ScriptTarget.Latest, true);
}

/**
 * The key a property is written with, when it is written as a plain name.
 *
 *     debug: { … }               → "debug"           (identifier)
 *     'sw-form-field': typeof X  → "sw-form-field"   (string literal, which is how hyphenated keys look)
 *     [SOME_CONST]: { … }        → undefined         (computed: the name is only known at runtime)
 */
function memberName(name: ts.PropertyName | undefined): string | undefined {
    // Identifier covers `debug`, StringLiteral covers `'sw-form-field'`. Anything else — a computed key,
    // a numeric literal, a private name — has no key this generator can write into the registry.
    if (name && (ts.isIdentifier(name) || ts.isStringLiteral(name))) {
        return name.text;
    }

    return undefined;
}

/**
 * The keys of an object literal, in source order.
 *
 *     { warn: warn, error: error }   → ["warn", "error"]     (`export const debug` in util.service.ts)
 *     { createId, throttle, debug }  → ["createId", "throttle", "debug"]   (its `export default`)
 *
 * A spread, a method, a getter or an accessor yields no key and is skipped, so a namespace written as
 * `{ ...base, warn() {} }` would publish less than it holds.
 */
function objectLiteralKeys(literal: ts.ObjectLiteralExpression): string[] {
    return literal.properties
        .map((property) =>
            // PropertyAssignment is `warn: warn`; ShorthandPropertyAssignment is the bare `debug`.
            // Both name a key; SpreadAssignment, MethodDeclaration and accessors do not.
            ts.isPropertyAssignment(property) || ts.isShorthandPropertyAssignment(property)
                ? memberName(property.name)
                : undefined,
        )
        .filter((name): name is string => name !== undefined);
}

/**
 * The `export default { … }` literal of a module, which is the object the global branch holds.
 *
 * Matches the whole statement, and its keys are what the branch publishes:
 *
 *     export default {
 *         createId,
 *         object,
 *         debug,
 *         …
 *     };
 *
 * Throws rather than returning nothing, because a branch that stopped being a literal — reassembled at
 * runtime, say — would otherwise silently publish an empty module.
 */
function defaultExportLiteral(sourceFile: ts.SourceFile, relativePath: string): ts.ObjectLiteralExpression {
    // ExportAssignment is `export default <expression>` (and also `export = <expression>`). Only a
    // literal right-hand side can be read without a type checker.
    const literal = sourceFile.statements.find(ts.isExportAssignment)?.expression;

    if (!literal || !ts.isObjectLiteralExpression(literal)) {
        throw new Error(`Expected "${relativePath}" to have an "export default { ... }" object literal.`);
    }

    return literal;
}

/**
 * Every top-level `export const <name> = { … }` in a module, as name → its keys.
 *
 * This is the second half of the utility branch. A namespace such as `debug` is declared once as a
 * const and then listed in the default export by shorthand:
 *
 *     export const debug = {
 *         warn: warn,
 *         error: error,
 *     };
 *
 *     → { debug: ["warn", "error"], format: ["currency", "date", …], types: […], … }
 *
 * Those keys are what a subpath publishes as named exports, so `shopware:utils/debug` serves
 * `import { warn } from 'shopware:utils/debug'`. A utility that is a plain function, such as `createId`,
 * has no entry here and ends up default-only.
 *
 * Top-level only, deliberately: a const nested in a block or a namespace is not what the default export
 * re-lists.
 */
function namedObjectExports(sourceFile: ts.SourceFile): Record<string, string[]> {
    const namespaces: Record<string, string[]> = {};

    sourceFile.statements.forEach((statement) => {
        // VariableStatement is the whole `export const debug = { … };` line, declarations included.
        if (!ts.isVariableStatement(statement)) {
            return;
        }

        // The `export` keyword itself. An unexported const is an implementation detail of the module.
        const isExported = statement.modifiers?.some((modifier) => modifier.kind === ts.SyntaxKind.ExportKeyword);

        if (!isExported) {
            return;
        }

        statement.declarationList.declarations.forEach((declaration) => {
            const initializer = declaration.initializer;

            // `debug` must be a plain name, not a destructuring pattern, and the right-hand side must be
            // the literal itself — `export const debug = makeDebug()` has no keys to read from syntax.
            if (ts.isIdentifier(declaration.name) && initializer && ts.isObjectLiteralExpression(initializer)) {
                namespaces[declaration.name.text] = objectLiteralKeys(initializer);
            }
        });
    });

    return namespaces;
}

/**
 * The member names of a declared `interface`, which is where the two runtime registries write down what
 * they hold.
 *
 * `Shopware.Mixin` and `Shopware.Store` have no object literal to read — entries are registered at
 * runtime — so their contract lives in `src/global.types.ts`:
 *
 *     declare global {
 *         interface MixinContainer {
 *             notification: typeof NotificationMixin;
 *             'sw-form-field': typeof SwFormFieldMixin;
 *             …
 *         }
 *     }
 *
 *     interfaceKeys(globalTypes, 'MixinContainer') → ["notification", "sw-form-field", …]
 *
 * Each key becomes one subpath verbatim, so `shopware:mixins/sw-form-field` resolves. The registry is
 * therefore only as honest as the interface: a key here that nobody registers type-checks and then
 * throws on import.
 */
function interfaceKeys(sourceFile: ts.SourceFile, interfaceName: string): string[] {
    const keys: string[] = [];

    // Recursive, unlike the const matcher above: both interfaces sit inside `declare global { … }`
    // rather than at the top level of the file.
    const visit = (node: ts.Node): void => {
        if (ts.isInterfaceDeclaration(node) && node.name.text === interfaceName) {
            node.members.forEach((member) => {
                // PropertySignature is `notification: typeof NotificationMixin`. A method signature or
                // an index signature names no single key.
                const name = ts.isPropertySignature(member) ? memberName(member.name) : undefined;

                if (name !== undefined) {
                    keys.push(name);
                }
            });
        }

        ts.forEachChild(node, visit);
    };

    visit(sourceFile);

    // An empty result means the interface was renamed or moved, not that it has no members. Publishing
    // nothing would look like a valid registry.
    if (keys.length === 0) {
        throw new Error(`Found no members of "interface ${interfaceName}".`);
    }

    return keys;
}

/** The directory whose mixins register as one block, via the eager glob in its index. */
const CENTRAL_MIXIN_DIR = './app/mixin/';

/**
 * The mixins the central registry owns, which are the only ones a subpath can resolve safely.
 *
 * `shopware:mixins/<name>` looks the mixin up when the importing module is evaluated, so the mixin has
 * to be registered by then. `src/app/mixin/index.js` registers its whole directory in one eager glob,
 * and a generated module imports that index, so those are guaranteed. A mixin a feature module
 * registers as it loads — `cms-element`, say — has no such moment and keeps `Mixin.getByName()`.
 *
 * Membership is read from where `MixinContainer` imports each mixin's type, so moving a mixin file
 * moves it between the two groups on its own.
 */
function centralMixinKeys(sourceFile: ts.SourceFile): string[] {
    const fromCentralDir = new Set<string>();

    sourceFile.statements.forEach((statement) => {
        if (
            !ts.isImportDeclaration(statement) ||
            !ts.isStringLiteral(statement.moduleSpecifier) ||
            !statement.moduleSpecifier.text.startsWith(CENTRAL_MIXIN_DIR)
        ) {
            return;
        }

        const name = statement.importClause?.name?.text;

        if (name !== undefined) {
            fromCentralDir.add(name);
        }
    });

    const central: string[] = [];

    const visit = (node: ts.Node): void => {
        if (ts.isInterfaceDeclaration(node) && node.name.text === 'MixinContainer') {
            node.members.forEach((member) => {
                const key = ts.isPropertySignature(member) ? memberName(member.name) : undefined;
                const type = ts.isPropertySignature(member) ? member.type : undefined;

                // `notification: typeof NotificationMixin` — the query names the imported type.
                if (key === undefined || !type || !ts.isTypeQueryNode(type) || !ts.isIdentifier(type.exprName)) {
                    return;
                }

                if (fromCentralDir.has(type.exprName.text)) {
                    central.push(key);
                }
            });
        }

        ts.forEachChild(node, visit);
    };

    visit(sourceFile);

    if (central.length === 0) {
        throw new Error(`Found no MixinContainer member imported from "${CENTRAL_MIXIN_DIR}".`);
    }

    return central;
}

function defaultOnlySubpaths(keys: string[]): Record<string, string[]> {
    return Object.fromEntries(
        keys.map((key) => [
            key,
            [],
        ]),
    );
}

/**
 * @private
 *
 * Builds the registry from the Administration sources.
 *
 * The utility branch is where the two passes meet, and they meet **by name**: a key from the default
 * export is looked up among the exported consts.
 *
 *     export const debug = { warn, error };   // pass 2 → debug: ["warn", "error"]
 *     export default { createId, debug };     // pass 1 → ["createId", "debug"]
 *
 *     → subpaths: { createId: [], debug: ["warn", "error"] }
 *
 * `createId` is a function, matches no const object, and falls through to default-only. The join assumes
 * the default export uses shorthand, as `util.service.ts` does throughout: `export default { dom: other }`
 * beside an unrelated `export const dom = { … }` would pair the two.
 */
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
            subpaths: defaultOnlySubpaths(centralMixinKeys(globalTypes)),
        },
        'shopware:stores': {
            exports: [],
            subpaths: defaultOnlySubpaths(interfaceKeys(globalTypes, 'PiniaRootState')),
        },
    };
}
