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
 * This file stays free of Node imports so the Jest shims can load it in jsdom. Which keys exist comes
 * from the checked-in `shopware-modules.json`.
 * 
 * Types of imports:
 * - import utils from "shopware:utils";            // root import, family 'utils'
 * - import { debug } from "shopware:utils";        // same
 * - import debug from "shopware:utils/debug";      // subpath import, family 'utils', subpath 'debug'
 * - import { warn } from "shopware:utils/debug";   // same
 *
 */

/**
 * A global object shaped like the branches the virtual modules read.
 *
 * Narrow on purpose: a full `ShopwareClass` type would pull the Administration program in, and this file
 * has to load in Node and in jsdom alike.
 */
export type VirtualModuleGlobal = {
    Utils: Record<string, unknown>;
    Data: Record<string, unknown>;
    Mixin: { getByName: (name: string) => unknown };
    Store: { get: (id: string) => unknown };
};

/** How one module family reads its branch of the global object. */
type Branch = {
    /** The property this family reads on the global, e.g. `Utils`. */
    readonly property: string;
    /** The value expression for one key, evaluated against a `shopware` binding. */
    readonly exportSubpathExpression: (key: string) => string;
    /** The same value at runtime. Used by the Jest shims. */
    readonly jestRead: (shopware: VirtualModuleGlobal, key: string) => unknown;
    /** Whether a bare import of the family resolves, i.e. whether the family has a barrel. */
    readonly hasBarrel: boolean;
};

function readOwn(branch: Record<string, unknown>, subpath: string, globalPath: string): unknown {
    if (!(subpath in branch)) {
        throw new Error(`"${subpath}" does not exist on ${globalPath}.`);
    }

    return branch[subpath];
}

const BRANCHES: Record<string, Branch> = {
    'shopware:utils': {
        property: 'Utils',
        exportSubpathExpression: (key) => `shopware.Utils[${JSON.stringify(key)}]`,
        jestRead: (shopware, key) => readOwn(shopware.Utils, key, 'Shopware.Utils'),
        hasBarrel: true,
    },
    'shopware:data': {
        property: 'Data',
        exportSubpathExpression: (key) => `shopware.Data[${JSON.stringify(key)}]`,
        jestRead: (shopware, key) => readOwn(shopware.Data, key, 'Shopware.Data'),
        hasBarrel: true,
    },
    'shopware:mixins': {
        property: 'Mixin',
        // Annotated pure so Rollup drops the lookup when the importer's binding is unused.
        exportSubpathExpression: (key) => `/*@__PURE__*/ shopware.Mixin.getByName(${JSON.stringify(key)})`,
        jestRead: (shopware, key) => shopware.Mixin.getByName(key),
        hasBarrel: false,
    },
    'shopware:stores': {
        property: 'Store',
        // A store is looked up per call, so importing never depends on it being registered yet.
        exportSubpathExpression: (key) => `() => shopware.Store.get(${JSON.stringify(key)})`,
        jestRead: (shopware, key) => () => shopware.Store.get(key),
        hasBarrel: false,
    },
};

/** The module families, e.g. `shopware:utils`. */
export const MODULE_FAMILIES = Object.keys(BRANCHES);

/** A parsed `shopware:*` import: the family it belongs to and the subpath key, if any. */
export type ParsedSpecifier = {
    readonly family: string;
    /** The subpath key, or `undefined` for a bare import of the family. */
    readonly subpath?: string;
};

/**
 * Splits a `shopware:*` import into its family and subpath key.
 * 
 * `specifier` is the * here: `utils` or `mixins/myCoolMixin`
 *
 * Returns `undefined` for anything this plugin does not serve, including a bare import of a family that
 * has no barrel. A store key may contain no slash, so the first slash always separates the two parts.
 */
export function parseSpecifier(specifier: string): ParsedSpecifier | undefined {
    const separator = specifier.indexOf('/');

    if (separator === -1) {
        return BRANCHES[specifier]?.hasBarrel ? { family: specifier } : undefined;
    }

    const family = specifier.slice(0, separator);
    const subpath = specifier.slice(separator + 1);

    return BRANCHES[family] && subpath.length > 0 ? { family, subpath } : undefined;
}

/** The value expression for a parsed specifier's default export. */
export function defaultExpression(parsed: ParsedSpecifier): string | undefined {
    const branch = BRANCHES[parsed.family];

    if (!branch) {
        return undefined;
    }

    return parsed.subpath === undefined ? `shopware.${branch.property}` : branch.exportSubpathExpression(parsed.subpath);
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
export function resolveVirtualExport(specifier: string, exportName: string, shopware: VirtualModuleGlobal): unknown {
    const parsed = parseSpecifier(specifier);
    const branch = parsed && BRANCHES[parsed.family];

    if (!parsed || !branch) {
        throw new Error(`"${specifier}" is not a Shopware virtual module.`);
    }

    // A bare family import publishes its branch's members directly, and its default export is the
    // whole branch, mirroring the `export =` in the generated declarations.
    if (parsed.subpath === undefined) {
        const wholeBranch = shopware[branch.property as keyof VirtualModuleGlobal];

        return exportName === 'default' ? wholeBranch : branch.jestRead(shopware, exportName);
    }

    const own = branch.jestRead(shopware, parsed.subpath);

    if (exportName === 'default') {
        return own;
    }

    if (own === null || typeof own !== 'object' || !(exportName in own)) {
        throw new Error(`"${specifier}" has no export "${exportName}".`);
    }

    return (own as Record<string, unknown>)[exportName];
}
