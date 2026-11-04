/**
 * @sw-package framework
 *
 * Extracts the `shopware:*` registry from the sources that define each global branch.
 *
 * The branches are written down in four different shapes, so each needs its own matcher.
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
 * `Shopware.Composables` — src/app/composables/index.ts
 * The same literal shape and output as the data branch:
 *
 *     export default { useCmsElement, useListing, … };
 *
 *     → shopware:composables             named exports { useCmsElement, useListing, … }
 *       shopware:composables/useListing  default only, like every composable file
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
 * `Shopware.Public` — the `index.ts` of every mini-module below `src/`
 * Each `index.ts` is the public contract of its module, so its value exports are the subpath's exports.
 * A component module is of the kind `components`; any other module names its kind with `@shopware-kind`:
 *
 *     // src/module/sw-settings-shipping/component/sw-settings-shipping-price-matrix/index.ts
 *     export default definePublicComponent({ name: 'sw-settings-shipping-price-matrix', … });
 *
 *     → shopware:components/sw-settings-shipping-price-matrix  named exports { default }
 *
 *     // src/app/service-names/index.ts, with `@shopware-kind framework` in the doc comment of the export
 *     export { repositoryFactory } from './src/service-names';
 *
 *     → shopware:framework/service-names  named exports { repositoryFactory }
 *
 * The subpath of a component module is the name it declares, so a CMS element in `component/` publishes
 * as `sw-cms-el-text`. A store or mixin module keeps the name it registers under, see `registeredName`,
 * and `shopware:stores` and `shopware:mixins` keep reading the live registry, see `registryFamily`.
 * Any other module has no other name than its directory, such as `order-delivery`.
 *
 * Only this generator parses source. Vite reads the checked-in `shopware-modules.json`.
 */

import fs from 'node:fs';
import path from 'node:path';
import ts from 'typescript';
import type { ModuleRegistry } from '../../build/vite-plugins/virtual-shopware-modules/definitions';
import { PUBLIC_KINDS, REGISTRY_KINDS, type PublicKind } from '../../src/core/mini-module-registry';

const UTILS_SOURCE = 'src/core/service/util.service.ts';
const DATA_SOURCE = 'src/core/data/index.js';
const COMPOSABLES_SOURCE = 'src/app/composables/index.ts';
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

/**
 * The mini-modules below `src/` whose directory holds the given entry file, its own `src/` and `tests/`, as
 * `eslint-rules/core-rules/mini-module.js` defines them. Paths are relative to the Administration root.
 */
function findModulesWith(administrationRoot: string, entry: 'index.ts' | 'private.ts'): string[] {
    const srcDir = path.join(administrationRoot, 'src');

    return (fs.readdirSync(srcDir, { recursive: true }) as string[])
        .filter((file) => path.basename(file) === entry)
        .map((file) => path.join(srcDir, path.dirname(file)))
        .filter((dir) => fs.existsSync(path.join(dir, 'src')) && fs.existsSync(path.join(dir, 'tests')))
        .map((dir) => path.relative(administrationRoot, dir).split(path.sep).join('/'))
        .sort((a, b) => (path.posix.basename(a) < path.posix.basename(b) ? -1 : 1));
}

/**
 * @private
 *
 * Every mini-module below `src/`: a directory with an `index.ts`, its own `src/` and `tests/`, as
 * `eslint-rules/core-rules/mini-module.js` defines it. Paths are relative to the Administration root.
 */
export function findMiniModules(administrationRoot: string): string[] {
    return findModulesWith(administrationRoot, 'index.ts');
}

function hasModifier(statement: ts.Statement, kind: ts.SyntaxKind): boolean {
    return ts.canHaveModifiers(statement) && (ts.getModifiers(statement) ?? []).some((modifier) => modifier.kind === kind);
}

/**
 * The value exports of a mini-module's `index.ts`, which are what reaches the global object at runtime.
 *
 *     export default definePublicComponent({ … });       → "default"
 *     export { repositoryFactory } from './src/…';       → "repositoryFactory"
 *     export type { PublicInstance } from './src/…';     → nothing, a type has no runtime value
 *
 * `export *` throws, because syntax alone cannot tell which names it publishes.
 */
function indexExports(sourceFile: ts.SourceFile, relativePath: string): string[] {
    const names: string[] = [];

    sourceFile.statements.forEach((statement) => {
        if (ts.isExportAssignment(statement) && !statement.isExportEquals) {
            names.push('default');
            return;
        }

        if (ts.isExportDeclaration(statement)) {
            if (statement.isTypeOnly) {
                return;
            }

            if (!statement.exportClause || !ts.isNamedExports(statement.exportClause)) {
                throw new Error(`"${relativePath}" uses "export *". List the public exports by name.`);
            }

            statement.exportClause.elements
                .filter((element) => !element.isTypeOnly)
                .forEach((element) => names.push(element.name.text));
            return;
        }

        if (!hasModifier(statement, ts.SyntaxKind.ExportKeyword) || hasModifier(statement, ts.SyntaxKind.DeclareKeyword)) {
            return;
        }

        if (hasModifier(statement, ts.SyntaxKind.DefaultKeyword)) {
            names.push('default');
        } else if (ts.isVariableStatement(statement)) {
            statement.declarationList.declarations
                .filter((declaration) => ts.isIdentifier(declaration.name))
                .forEach((declaration) => names.push((declaration.name as ts.Identifier).text));
        } else if (
            (ts.isFunctionDeclaration(statement) || ts.isClassDeclaration(statement) || ts.isEnumDeclaration(statement)) &&
            statement.name
        ) {
            names.push(statement.name.text);
        }
    });

    return names;
}

/**
 * The `name` of a component that `index.ts` declares with `definePublicComponent({ name: '…' })`, if any.
 */
function declaredComponentName(sourceFile: ts.SourceFile): string | undefined {
    const declaration = sourceFile.statements.find(ts.isExportAssignment)?.expression;

    if (
        !declaration ||
        !ts.isCallExpression(declaration) ||
        !ts.isIdentifier(declaration.expression) ||
        declaration.expression.text !== 'definePublicComponent'
    ) {
        return undefined;
    }

    const options = declaration.arguments[0];
    const name =
        options && ts.isObjectLiteralExpression(options)
            ? options.properties.find(
                  (property): property is ts.PropertyAssignment =>
                      ts.isPropertyAssignment(property) && memberName(property.name) === 'name',
              )?.initializer
            : undefined;

    return name && ts.isStringLiteral(name) ? name.text : undefined;
}

const KIND_TAG = 'shopware-kind';

/**
 * The kinds the `@shopware-kind` tags in the doc comments of an `index.ts` name, e.g. `["framework"]`.
 */
function taggedKinds(sourceFile: ts.SourceFile): string[] {
    return sourceFile.statements.flatMap((statement) =>
        ts
            .getJSDocTags(statement)
            .filter((tag) => tag.tagName.text === KIND_TAG)
            .map((tag) => ts.getTextOfJSDocComment(tag.comment)?.trim() ?? ''),
    );
}

/**
 * The kind a mini-module is published under: `components` for a component module, otherwise the one kind
 * its `@shopware-kind` tags name.
 *
 * Throws when a module names no kind, an unknown one or more than one, because its specifier would be
 * unclear.
 */
function moduleKind(sourceFile: ts.SourceFile, moduleDir: string, isComponent: boolean): PublicKind {
    const kinds = [...new Set(taggedKinds(sourceFile))];
    const allowed = PUBLIC_KINDS.join(', ');

    if (isComponent) {
        if (kinds.some((kind) => kind !== 'components')) {
            throw new Error(
                `"${moduleDir}" declares a component, so its kind is "components". Remove the @${KIND_TAG} tag.`,
            );
        }

        return 'components';
    }

    if (kinds.length !== 1) {
        throw new Error(
            `"${moduleDir}/index.ts" needs exactly one kind. Add "@${KIND_TAG} <kind>" to the doc comment of its exports, with one of: ${allowed}.`,
        );
    }

    const [kind] = kinds;

    if (!(PUBLIC_KINDS as readonly string[]).includes(kind)) {
        throw new Error(`"${moduleDir}/index.ts" names the unknown kind "${kind}". Use one of: ${allowed}.`);
    }

    return kind as PublicKind;
}

const STORE_ID_EXPORT = /^(.+)StoreId$/;

/**
 * The name a store or mixin module registers under, which is its subpath in `shopware:stores` or
 * `shopware:mixins`, so a specifier resolves as it did before the module existed.
 *
 *     // stores: index.ts exports the id as `<id>StoreId`
 *     export { swProductDetailStoreId, useSwProductDetailStore } from './src/sw-product-detail.store';
 *     → "swProductDetail"
 *
 *     // mixins: the directory is named after the mixin
 *     src/module/sw-settings/mixin/sw-settings-list → "sw-settings-list"
 *
 * Throws when a store module exports no id or more than one, because its specifier would be unclear.
 */
function registeredName(kind: PublicKind, moduleDir: string, exports: string[], entry: string): string {
    if (kind !== 'stores') {
        return path.posix.basename(moduleDir);
    }

    const ids = exports.map((name) => STORE_ID_EXPORT.exec(name)?.[1]).filter((id): id is string => id !== undefined);

    if (ids.length !== 1) {
        throw new Error(
            `"${moduleDir}/${entry}" needs exactly one "<id>StoreId" export, which names the store it registers.`,
        );
    }

    return ids[0];
}

const isRegistryKind = (kind: string): boolean => (REGISTRY_KINDS as readonly string[]).includes(kind);

/**
 * @private
 *
 * The names the `@private` store and mixin modules register under: a module with only a `private.ts`, whose
 * doc comment names the kind. Their names stay out of `shopware:stores` and `shopware:mixins`, also when the
 * global interface still lists them.
 */
export function privateRegistryNames(administrationRoot: string): Set<string> {
    const names = findModulesWith(administrationRoot, 'private.ts')
        .filter((moduleDir) => !fs.existsSync(path.join(administrationRoot, moduleDir, 'index.ts')))
        .flatMap((moduleDir) => {
            const entry = `${moduleDir}/private.ts`;
            const sourceFile = parse(administrationRoot, entry);
            const kind = [...new Set(taggedKinds(sourceFile))].find(isRegistryKind);

            return kind
                ? [registeredName(kind as PublicKind, moduleDir, indexExports(sourceFile, entry), 'private.ts')]
                : [];
        });

    return new Set(names);
}

/**
 * @private
 *
 * The `shopware:<kind>` families: one family per kind, with one subpath per mini-module, named after the
 * component it declares, the store or mixin it registers, or else after its directory. Every kind has a
 * family, even one no module uses yet.
 *
 * Throws when two modules share a name, because one specifier would reach both.
 */
export function extractPublicModules(administrationRoot: string): ModuleRegistry {
    const families = Object.fromEntries(
        PUBLIC_KINDS.map((kind) => [
            kind,
            { exports: [] as string[], subpaths: {} as Record<string, string[]>, sources: {} as Record<string, string> },
        ]),
    ) as Record<PublicKind, Required<ModuleRegistry[string]>>;
    const seen: Record<string, string> = {};

    findMiniModules(administrationRoot).forEach((moduleDir) => {
        const indexFile = `${moduleDir}/index.ts`;
        const sourceFile = parse(administrationRoot, indexFile);
        const componentName = declaredComponentName(sourceFile);
        const kind = moduleKind(sourceFile, moduleDir, componentName !== undefined);
        const exports = indexExports(sourceFile, indexFile);
        const name = componentName ?? registeredName(kind, moduleDir, exports, 'index.ts');
        const key = `${kind}/${name}`;

        if (Object.hasOwn(seen, key)) {
            throw new Error(`The mini-modules "${seen[key]}" and "${moduleDir}" share the name "${key}".`);
        }

        families[kind].subpaths[name] = exports;
        families[kind].sources[name] = moduleDir;
        seen[key] = moduleDir;
    });

    return Object.fromEntries(
        PUBLIC_KINDS.map((kind) => [
            `shopware:${kind}`,
            families[kind],
        ]),
    );
}

/**
 * A family that reads a live registry, from the names its global interface lists and the names its
 * `@public` modules register under. Each subpath is default-only, like before the modules existed,
 * and a `@private` module's name is left out.
 *
 *     interface PiniaRootState { cmsPage: …; swProductDetail: … }   + stores/sw-product-detail-store
 *     → subpaths { cmsPage: [], swProductDetail: [] }, sources { swProductDetail: '…/store' }
 */
function registryFamily(
    interfaceNames: string[],
    modules: ModuleRegistry[string],
    privateNames: Set<string>,
): ModuleRegistry[string] {
    const names = [...new Set([...interfaceNames, ...Object.keys(modules.subpaths)])].filter(
        (name) => !privateNames.has(name),
    );

    const sources = modules.sources ?? {};

    return {
        exports: [],
        subpaths: defaultOnlySubpaths(names),
        ...(Object.keys(sources).length > 0 ? { sources } : {}),
    };
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

    const composablesSource = parse(administrationRoot, COMPOSABLES_SOURCE);
    const composableKeys = objectLiteralKeys(defaultExportLiteral(composablesSource, COMPOSABLES_SOURCE));

    const globalTypes = parse(administrationRoot, GLOBAL_TYPES_SOURCE);
    const publicModules = extractPublicModules(administrationRoot);
    const privateNames = privateRegistryNames(administrationRoot);

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
        'shopware:composables': {
            exports: composableKeys,
            subpaths: defaultOnlySubpaths(composableKeys),
        },
        'shopware:mixins': registryFamily(centralMixinKeys(globalTypes), publicModules['shopware:mixins'], privateNames),
        'shopware:stores': registryFamily(
            interfaceKeys(globalTypes, 'PiniaRootState'),
            publicModules['shopware:stores'],
            privateNames,
        ),
        ...Object.fromEntries(
            Object.entries(publicModules).filter(([family]) => !isRegistryKind(family.slice('shopware:'.length))),
        ),
    };
}
