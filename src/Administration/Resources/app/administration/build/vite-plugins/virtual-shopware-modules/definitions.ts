/**
 * @sw-package framework
 *
 * Shared resolution rules for the Vite modules and their Jest counterparts. The generated registry
 * decides which specifiers exist; the branch definitions below decide how each one reads `Shopware`.
 */

import type { ShopwareClass } from 'src/core/shopware';
import { PUBLIC_KINDS, REGISTRY_KINDS, type PublicKind } from '../../../src/core/mini-module-registry';

/**
 * @private
 *
 * `Utils`, `Data` and `Composables` keep their exact `ShopwareClass` types. The registry methods accept
 * strings because their keys come from `shopware-modules.json`.
 */
export type VirtualModuleGlobal = Pick<ShopwareClass, 'Utils' | 'Data' | 'Composables' | 'Public'> & {
    Mixin: { getByName: (name: string) => unknown };
    Store: { get: (id: string) => unknown };
};

/**
 * @private
 *
 * What one `shopware:*` module family publishes.
 *
 * `exports` are the root import's named exports, empty for a family that has none. `subpaths` maps a
 * subpath key to the names it publishes alongside its default export; an empty list is default-only.
 */
export type ModuleRegistryEntry = {
    exports: string[];
    subpaths: Record<string, string[]>;
    /** Where each subpath is declared, relative to the Administration root, for a family generated per file. */
    sources?: Record<string, string>;
};

/** @private The checked-in registry of every `shopware:*` specifier, keyed by family. */
export type ModuleRegistry = Record<string, ModuleRegistryEntry>;

/**
 * Each address keeps its generated expression next to its live-global reader. The parity test evaluates
 * every `emit` and compares it with the matching `read`.
 */
type Branch = {
    /**
     * The Shopware version that added the branch to the global object. Only a branch newer than the
     * `shopware:*` modules needs it: a prebuilt extension bundle can run on an Administration that has none.
     *
     * It covers the branch as a whole. A member added to the branch later still reads as `undefined` on
     * an older Administration that has the branch.
     */
    readonly since?: string;
    /** The expression that is truthy when the branch exists, if reading the root could already throw. */
    readonly present?: () => string;
    /**
     * Each subpath holds the exports of an ES module, like `import * as` does: its default export is the
     * `default` member, which exists only when the registry lists it, instead of the subpath object itself.
     */
    readonly esModuleSubpaths?: boolean;
    readonly root: {
        readonly emit: () => string;
        readonly read: (shopware: VirtualModuleGlobal) => unknown;
    };
    readonly subpath: {
        readonly emit: (subpath: string) => string;
        readonly read: (shopware: VirtualModuleGlobal, subpath: string) => unknown;
    };
};

function readOwn(branch: Record<string, unknown>, subpath: string, globalPath: string): unknown {
    if (!Object.hasOwn(branch, subpath)) {
        throw new Error(`"${subpath}" does not exist on ${globalPath}.`);
    }

    return branch[subpath];
}

/**
 * A `shopware:<kind>` family: one mini-module per subpath, read from `Shopware.Public[kind]`.
 *
 * @example
 * publicKindBranch('components'); // serves shopware:components/sw-settings-shipping-price-matrix
 */
function publicKindBranch(kind: PublicKind): Branch {
    return {
        since: '6.7.17.0',
        present: () => `shopware.Public?.[${JSON.stringify(kind)}]`,
        esModuleSubpaths: true,
        root: {
            emit: () => `shopware.Public[${JSON.stringify(kind)}]`,
            read: (shopware) => shopware.Public[kind],
        },
        subpath: {
            emit: (subpath) => `shopware.Public[${JSON.stringify(kind)}][${JSON.stringify(subpath)}]`,
            read: (shopware, subpath) => readOwn(shopware.Public[kind], subpath, `Shopware.Public.${kind}`),
        },
    };
}

const BRANCHES: Record<string, Branch> = {
    'shopware:utils': {
        root: {
            emit: () => 'shopware.Utils',
            read: (shopware) => shopware.Utils,
        },
        subpath: {
            emit: (subpath) => `shopware.Utils[${JSON.stringify(subpath)}]`,
            read: (shopware, subpath) => readOwn(shopware.Utils, subpath, 'Shopware.Utils'),
        },
    },
    'shopware:data': {
        root: {
            emit: () => 'shopware.Data',
            read: (shopware) => shopware.Data,
        },
        subpath: {
            emit: (subpath) => `shopware.Data[${JSON.stringify(subpath)}]`,
            read: (shopware, subpath) => readOwn(shopware.Data, subpath, 'Shopware.Data'),
        },
    },
    'shopware:composables': {
        since: '6.7.16.0',
        root: {
            emit: () => 'shopware.Composables',
            read: (shopware) => shopware.Composables,
        },
        subpath: {
            emit: (subpath) => `shopware.Composables[${JSON.stringify(subpath)}]`,
            read: (shopware, subpath) => readOwn(shopware.Composables, subpath, 'Shopware.Composables'),
        },
    },
    'shopware:mixins': {
        root: {
            emit: () => 'shopware.Mixin',
            read: (shopware) => shopware.Mixin,
        },
        subpath: {
            // Annotated pure so Rollup drops the lookup when the importer's binding is unused.
            emit: (subpath) => `/*@__PURE__*/ shopware.Mixin.getByName(${JSON.stringify(subpath)})`,
            read: (shopware, subpath) => shopware.Mixin.getByName(subpath),
        },
    },
    'shopware:stores': {
        root: {
            emit: () => 'shopware.Store',
            read: (shopware) => shopware.Store,
        },
        subpath: {
            // A store is looked up per call, so importing never depends on it being registered yet.
            emit: (subpath) => `() => shopware.Store.get(${JSON.stringify(subpath)})`,
            read: (shopware, subpath) => () => shopware.Store.get(subpath),
        },
    },
    // `shopware:mixins` and `shopware:stores` keep reading the live registry above, also for a mini-module.
    ...Object.fromEntries(
        PUBLIC_KINDS.filter((kind) => !(REGISTRY_KINDS as readonly string[]).includes(kind)).map((kind) => [
            `shopware:${kind}`,
            publicKindBranch(kind),
        ]),
    ),
};

/** @private The module families, e.g. `shopware:utils`. */
export const MODULE_FAMILIES = Object.keys(BRANCHES);

/** @private A parsed `shopware:*` import: the family and optional subpath key. */
export type ParsedSpecifier = {
    readonly family: string;
    /** The subpath, or `undefined` for a root import of the family. */
    readonly subpath?: string;
};

/**
 * @private
 *
 * Splits a `shopware:*` import into its family and subpath.
 *
 * `specifier` includes the prefix, for example `shopware:utils` or `shopware:mixins/myCoolMixin`.
 *
 * Returns `undefined` for a specifier no family serves. Whether the parsed specifier actually resolves
 * is `exportNames`' answer, not this one: a family may publish no root import. The first slash separates
 * the family from the complete subpath.
 */
export function parseSpecifier(specifier: string): ParsedSpecifier | undefined {
    const separator = specifier.indexOf('/');

    if (separator === -1) {
        return Object.hasOwn(BRANCHES, specifier) ? { family: specifier } : undefined;
    }

    const family = specifier.slice(0, separator);
    const subpath = specifier.slice(separator + 1);

    return Object.hasOwn(BRANCHES, family) && subpath.length > 0 ? { family, subpath } : undefined;
}

/**
 * @private
 *
 * The export names a specifier publishes: the root import's members, or one subpath's own names.
 *
 * `undefined` means the specifier does not resolve: an unknown key, or a root import of a family that
 * publishes no root exports. An empty list means the opposite — it resolves, publishing its default
 * export alone.
 */
export function exportNames(registry: ModuleRegistry, parsed: ParsedSpecifier): string[] | undefined {
    if (!Object.hasOwn(registry, parsed.family)) {
        return undefined;
    }

    const entry = registry[parsed.family];

    if (parsed.subpath === undefined) {
        return entry.exports.length > 0 ? entry.exports : undefined;
    }

    return Object.hasOwn(entry.subpaths, parsed.subpath) ? entry.subpaths[parsed.subpath] : undefined;
}

/** @private Every root and subpath specifier published by the registry. */
export function allSpecifiers(registry: ModuleRegistry): string[] {
    return Object.entries(registry).flatMap(
        ([
            family,
            entry,
        ]) => [
            ...(exportNames(registry, { family }) ? [family] : []),
            ...Object.keys(entry.subpaths).map((key) => `${family}/${key}`),
        ],
    );
}

/**
 * @private
 *
 * The statements an extension module runs before it reads a branch that older Administrations lack.
 * Without them, reading a member of the missing branch fails with a bare TypeError.
 *
 * Only a plugin bundle can get here, and the plugin lifecycle enforces its composer.json requirements,
 * so the message names that fix. The installed version is in the context, because the plugin bundles
 * are loaded from the same config response.
 */
export function branchGuard(parsed: ParsedSpecifier): string[] {
    const branch = Object.hasOwn(BRANCHES, parsed.family) ? BRANCHES[parsed.family] : undefined;
    const since = branch?.since;

    if (branch === undefined || since === undefined) {
        return [];
    }

    const required = `"${parsed.family}" requires Shopware ${since} or later, but the installed version is `;
    const fix = `. Require shopware/administration >=${since} in the composer.json of the extension.`;

    return [
        `if (!${branch.present?.() ?? branch.root.emit()}) {`,
        `    throw new Error(${JSON.stringify(required)} + shopware.Context?.app?.config?.version + ${JSON.stringify(fix)});`,
        '}',
    ];
}

/** The generated expression for the object a specifier reads its exports from. */
function moduleExpression(parsed: ParsedSpecifier): string | undefined {
    if (!Object.hasOwn(BRANCHES, parsed.family)) {
        return undefined;
    }

    const branch = BRANCHES[parsed.family];

    return parsed.subpath === undefined ? branch.root.emit() : branch.subpath.emit(parsed.subpath);
}

/** @private Whether a specifier is a subpath that holds the exports of an ES module. */
export function isEsModuleSubpath(parsed: ParsedSpecifier): boolean {
    return parsed.subpath !== undefined && BRANCHES[parsed.family]?.esModuleSubpaths === true;
}

/**
 * @private
 *
 * The names a specifier publishes as `export const`. An ES module subpath lists `default` among its
 * exports, but that one becomes the default export.
 */
export function namedExportNames(members: string[], parsed: ParsedSpecifier): string[] {
    return isEsModuleSubpath(parsed) ? members.filter((member) => member !== 'default') : members;
}

/**
 * @private
 *
 * Whether a resolving specifier has a default export. Only an ES module subpath can lack one, when its
 * `index.ts` has no default export.
 */
export function hasDefaultExport(members: string[], parsed: ParsedSpecifier): boolean {
    return !isEsModuleSubpath(parsed) || members.includes('default');
}

/** @private The generated expression for one named export. */
export function memberExpression(parsed: ParsedSpecifier, member: string): string {
    return `${moduleExpression(parsed)}[${JSON.stringify(member)}]`;
}

/** @private The generated expression for a specifier's default export. */
export function defaultExpression(parsed: ParsedSpecifier): string | undefined {
    return isEsModuleSubpath(parsed) ? memberExpression(parsed, 'default') : moduleExpression(parsed);
}

/**
 * @private
 *
 * Resolves one export of a `shopware:*` module against a live global object.
 *
 * The Jest shims call this because a resolver hands them a property name rather than generated code:
 * they need the value the generated module would have exported for it.
 */
export function resolveVirtualExport(
    registry: ModuleRegistry,
    specifier: string,
    exportName: string,
    shopware: VirtualModuleGlobal,
): unknown {
    const parsed = parseSpecifier(specifier);
    const branch = parsed && BRANCHES[parsed.family];

    if (!parsed || !branch || !exportNames(registry, parsed)) {
        throw new Error(`"${specifier}" is not a Shopware virtual module.`);
    }

    // A root import's default export is the whole branch. Its named exports are the branch's members,
    // and every member is also a subpath.
    if (parsed.subpath === undefined) {
        return exportName === 'default' ? branch.root.read(shopware) : branch.subpath.read(shopware, exportName);
    }

    const moduleValue = branch.subpath.read(shopware, parsed.subpath);

    if (exportName === 'default' && !branch.esModuleSubpaths) {
        return moduleValue;
    }

    if (moduleValue === null || typeof moduleValue !== 'object' || !Object.hasOwn(moduleValue, exportName)) {
        throw new Error(`"${specifier}" has no export "${exportName}".`);
    }

    return (moduleValue as Record<string, unknown>)[exportName];
}
