/**
 * @sw-package framework
 *
 * The `shopware:*` module contract, in one place.
 *
 * Every `shopware:*` specifier is sugar over a branch of the global `Shopware` object: the Vite plugin
 * generates an ES module whose named exports read that branch, and the Jest shims resolve the same names
 * through {@link resolveVirtualExport}. Nothing here changes runtime behaviour - the exports are the very
 * objects the global already holds, so component overrides and the plugin system keep working unchanged.
 *
 * This file stays free of Node imports so the Jest shims can load it in jsdom. Which export names a
 * module publishes is discovered from the Administration sources in `source-keys.ts`.
 */

/**
 * @private
 *
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

/**
 * How one `shopware:*` module maps between export names and the global object.
 *
 * `expression` and `read` are the same rule in two forms - a code string for the module the Vite plugin
 * generates, and a function for the Jest shims. `definitions.spec.ts` drives one through the other so
 * the pair cannot drift.
 */
type VirtualModuleDefinition = {
    /** The branch of the global object this module publishes. Used in error messages. */
    readonly globalPath: string;
    /** The export name a source key is published under, e.g. `sw-form-field` -> `swFormFieldMixin`. */
    readonly exportName: (sourceKey: string) => string;
    /**
     * The source keys an export name could have come from, best guess first.
     *
     * Registry keys are not spelled uniformly - most mixins are kebab-case but `ruleContainer` is not -
     * so an export name can map back to more than one candidate. {@link resolveVirtualExport} tries them
     * in order, which is what lets the runtime side work without a build-time key list.
     */
    readonly sourceKeyCandidates: (exportName: string) => string[];
    /** The generated module's initialiser for an export, evaluated against a `shopware` binding. */
    readonly expression: (sourceKey: string) => string;
    /** Reads one source key off the global object, or throws when it does not exist. */
    readonly read: (shopware: VirtualModuleGlobal, sourceKey: string) => unknown;
};

function upperFirst(value: string): string {
    return value.charAt(0).toUpperCase() + value.slice(1);
}

function lowerFirst(value: string): string {
    return value.charAt(0).toLowerCase() + value.slice(1);
}

function camelCase(value: string): string {
    const [
        head = '',
        ...rest
    ] = value.split('-').filter((part) => part.length > 0);

    return `${head}${rest.map(upperFirst).join('')}`;
}

function kebabCase(value: string): string {
    return value.replace(/(?<=[a-z0-9])(?=[A-Z])/g, '-').toLowerCase();
}

/**
 * @private
 *
 * The export name of a registered mixin: `sw-form-field` becomes `swFormFieldMixin`.
 *
 * The `Mixin` suffix keeps `mixins: [swFormFieldMixin]` readable at the use site and keeps mixin names
 * from colliding with the same-named composables and stores.
 */
export function mixinExportName(mixinName: string): string {
    return `${camelCase(mixinName)}Mixin`;
}

/**
 * @private
 *
 * The export name of a registered store: `swOrderDetail` becomes `useSwOrderDetailStore`.
 *
 * Matches Pinia's own `useXStore` convention, so the import reads like any other composable.
 */
export function storeExportName(storeId: string): string {
    return `use${upperFirst(storeId)}Store`;
}

function identity(value: string): string[] {
    return [value];
}

/**
 * @private
 *
 * Every `shopware:*` module, keyed by its import specifier.
 *
 * The Vite plugin, the Jest shims and the drift guards all read this object, so adding a module means
 * adding an entry here, a key source in `source-keys.ts`, and ambient types in
 * `src/shopware-virtual-modules.d.ts`.
 */
export const VIRTUAL_MODULES: Record<string, VirtualModuleDefinition> = {
    'shopware:utils': {
        globalPath: 'Shopware.Utils',
        exportName: (key) => key,
        sourceKeyCandidates: identity,
        expression: (key) => `shopware.Utils[${JSON.stringify(key)}]`,
        read: (shopware, key) => readOwn(shopware.Utils, key, 'Shopware.Utils'),
    },
    'shopware:data': {
        globalPath: 'Shopware.Data',
        exportName: (key) => key,
        sourceKeyCandidates: identity,
        expression: (key) => `shopware.Data[${JSON.stringify(key)}]`,
        read: (shopware, key) => readOwn(shopware.Data, key, 'Shopware.Data'),
    },
    'shopware:mixins': {
        globalPath: 'Shopware.Mixin',
        exportName: mixinExportName,
        sourceKeyCandidates: (name) => {
            const base = name.replace(/Mixin$/, '');

            return [
                kebabCase(base),
                base,
            ];
        },
        // Annotated pure so Rollup drops the lookups an importer does not use. Without it every mixin is
        // resolved on import, and resolving one that is not registered throws.
        expression: (key) => `/*@__PURE__*/ shopware.Mixin.getByName(${JSON.stringify(key)})`,
        read: (shopware, key) => shopware.Mixin.getByName(key),
    },
    'shopware:stores': {
        globalPath: 'Shopware.Store',
        exportName: storeExportName,
        sourceKeyCandidates: (name) => [lowerFirst(name.replace(/^use/, '').replace(/Store$/, ''))],
        // A store is looked up per call, not at import time, so importing the module never depends on a
        // store already being registered.
        expression: (key) => `() => shopware.Store.get(${JSON.stringify(key)})`,
        read: (shopware, key) => () => shopware.Store.get(key),
    },
};

/** @private The import specifiers the plugin answers, e.g. `shopware:utils`. */
export const VIRTUAL_MODULE_SPECIFIERS = Object.keys(VIRTUAL_MODULES);

/** @private Whether `specifier` is one of the `shopware:*` modules. */
export function isVirtualModule(specifier: string): boolean {
    return Object.prototype.hasOwnProperty.call(VIRTUAL_MODULES, specifier);
}

function readOwn(branch: Record<string, unknown>, key: string, globalPath: string): unknown {
    if (!(key in branch)) {
        throw new Error(`"${key}" does not exist on ${globalPath}.`);
    }

    return branch[key];
}

/**
 * @private
 *
 * Resolves one export of a `shopware:*` module against a live global object.
 *
 * The Jest shims call this because a `moduleNameMapper` target is a real file, not generated code: they
 * are handed a property name and need the value the generated module would have exported for it.
 */
export function resolveVirtualExport(specifier: string, exportName: string, shopware: VirtualModuleGlobal): unknown {
    const definition = VIRTUAL_MODULES[specifier];

    if (!definition) {
        throw new Error(`"${specifier}" is not a Shopware virtual module.`);
    }

    const candidates = definition.sourceKeyCandidates(exportName);
    const failures: string[] = [];

    for (const candidate of candidates) {
        try {
            return definition.read(shopware, candidate);
        } catch (error) {
            failures.push(error instanceof Error ? error.message : String(error));
        }
    }

    throw new Error(
        `"${specifier}" has no export "${exportName}". Tried ${definition.globalPath} with ` +
            `${candidates.map((candidate) => `"${candidate}"`).join(', ')}: ${failures.join(' ')}`,
    );
}
