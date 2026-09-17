/**
 * @sw-package framework
 *
 * The `shopware:*` module contract, in one place.
 *
 * Every specifier is sugar over a branch of the global `Shopware` object. `shopware:utils` and
 * `shopware:data` publish a branch's members, and a subpath such as `shopware:utils/debug` publishes one
 * member plus that member's own names. `shopware:mixins` and `shopware:stores` are subpath-only, because
 * a barrel over a runtime registry has to resolve every entry the moment anything imports it.
 *
 * Nothing here changes runtime behaviour: the exports are the very objects the global already holds, so
 * component overrides and the plugin system keep working unchanged.
 *
 * Which keys exist is not decided here. Every function that answers "does this resolve?" takes the
 * checked-in `shopware-modules.json` as an argument; this file only describes how a resolved specifier
 * reads the global.
 *
 * Types of imports:
 * - import utils from "shopware:utils";            // root import, family 'utils'
 * - import { debug } from "shopware:utils";        // same
 * - import debug from "shopware:utils/debug";      // subpath import, family 'utils', subpath 'debug'
 * - import { warn } from "shopware:utils/debug";   // same
 */

import type { ShopwareClass } from 'src/core/shopware';

/**
 * The branches of the global `Shopware` object that the virtual modules read.
 *
 * `Utils` and `Data` come from `ShopwareClass`, so renaming a branch or a member breaks here rather than
 * at runtime. The two registries are widened instead: `Mixin.getByName` and `Store.get` are keyed by
 * `keyof MixinContainer` and `keyof PiniaRootState`, and a key read out of `shopware-modules.json` is a
 * plain string.
 */
export type VirtualModuleGlobal = Pick<ShopwareClass, 'Utils' | 'Data'> & {
    Mixin: { getByName: (name: string) => unknown };
    Store: { get: (id: string) => unknown };
};

/**
 * What one `shopware:*` module family publishes.
 *
 * `exports` are the root import's named exports, empty for a family that has none. `subpaths` maps a
 * subpath key to the names it publishes alongside its default export; an empty list is default-only.
 */
export type ModuleRegistryEntry = {
    exports: string[];
    subpaths: Record<string, string[]>;
};

/** The checked-in registry of every `shopware:*` specifier, keyed by family. */
export type ModuleRegistry = Record<string, ModuleRegistryEntry>;

/**
 * How one module family reads its branch of the global object, at each kind of address.
 *
 * `emit` is the code the build puts in the generated module; `read` is the same value off a live global.
 * The two must always describe the same value, which is why they are declared side by side:
 * `definitions.spec.ts` evaluates every `emit` and compares it against the matching `read`.
 *
 * `root` is only reached for a family that publishes root exports — `exportNames` refuses the others
 * before it could be called — but it is spelled out for all four, because it names the branch.
 */
type Branch = {
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
};

/** The module families, e.g. `shopware:utils`. */
export const MODULE_FAMILIES = Object.keys(BRANCHES);

/** A parsed `shopware:*` import: the family it belongs to and the subpath key, if any. */
export type ParsedSpecifier = {
    readonly family: string;
    /** The subpath, or `undefined` for a root import of the family. */
    readonly subpath?: string;
};

/**
 * Splits a `shopware:*` import into its family and subpath.
 *
 * `specifier` includes the prefix, for example `shopware:utils` or `shopware:mixins/myCoolMixin`.
 *
 * Returns `undefined` for a specifier no family serves. Whether the parsed specifier actually resolves
 * is `exportNames`' answer, not this one: a family may publish no root import. A store key may contain
 * no slash, so the first slash always separates the two parts.
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

/** Every specifier the registry publishes: each family's root import where it has one, plus every subpath. */
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

/** The value expression for a parsed specifier's default export. */
export function defaultExpression(parsed: ParsedSpecifier): string | undefined {
    if (!Object.hasOwn(BRANCHES, parsed.family)) {
        return undefined;
    }

    const branch = BRANCHES[parsed.family];

    return parsed.subpath === undefined ? branch.root.emit() : branch.subpath.emit(parsed.subpath);
}

/** The value expression for one named export of a parsed specifier. */
export function memberExpression(parsed: ParsedSpecifier, member: string): string {
    const own = defaultExpression(parsed);

    return `${own}[${JSON.stringify(member)}]`;
}

/**
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

    const own = branch.subpath.read(shopware, parsed.subpath);

    if (exportName === 'default') {
        return own;
    }

    if (own === null || typeof own !== 'object' || !Object.hasOwn(own, exportName)) {
        throw new Error(`"${specifier}" has no export "${exportName}".`);
    }

    return (own as Record<string, unknown>)[exportName];
}
