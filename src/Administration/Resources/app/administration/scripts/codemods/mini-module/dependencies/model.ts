/**
 * @sw-package framework
 * @private
 */

import type ts from 'typescript';

/**
 * A name dependencies.ts re-exports from another module, for `src/` to import.
 * `module` is a package, a `src/…` path, or a path relative to dependencies.ts, as the export writes it.
 * `imported` is `default`, `*` for the whole module, or a named export.
 *
 * @example
 * { kind: 'reexport', module: 'src/core/data/criteria.data', imported: 'default', exported: 'Criteria' }
 * // → export { default as Criteria } from 'src/core/data/criteria.data';
 */
export type Reexport = {
    kind: 'reexport';
    module: string;
    imported: string;
    exported: string;
    typeOnly?: boolean;
};

/** A name dependencies.ts imports for its own accessors, such as the id of a store. */
export type AccessorImport = { module: string; imported: string };

/**
 * A function dependencies.ts exports, because what it returns is looked up or changes at runtime.
 * `order` groups the functions like the reference module: services, stores, filters, context, feature flags.
 *
 * @example
 * { kind: 'accessor', name: 'getApiContext', order: 3, body: '() => Shopware.Context.api', imports: [] }
 * // → export const getApiContext = () => Shopware.Context.api;
 */
export type Accessor = {
    kind: 'accessor';
    name: string;
    order: number;
    body: string;
    imports: AccessorImport[];
};

/** What dependencies.ts exports. */
export type Dependency = Reexport | Accessor;

/**
 * A change outside the module that a rewrite needs, applied once after all files of the module are routed:
 * a new constant in `src/app/service-names`, or an exported store id or filter name in the file that registers it.
 */
export type OutsideEdit =
    | { kind: 'service-name'; name: string }
    | { kind: 'store-id'; file: string; id: string; exportName: string }
    | { kind: 'filter-name'; file: string; name: string; exportName: string };

/**
 * One rewrite of a piece of `src/`: the source range it replaces and with what, the names `src/` then imports
 * from `../dependencies`, what dependencies.ts must export for them, and what must change outside the module.
 * `imports` maps the local name in `src/` to the name dependencies.ts exports; mostly they are the same.
 */
export type Rewrite = {
    start: number;
    end: number;
    text: string;
    imports: Record<string, string>;
    dependencies: Dependency[];
    outsideEdits: OutsideEdit[];
};

/**
 * The file that registers a store or filter: `module` as dependencies.ts imports it, `file` as an absolute path,
 * and the name the file already exports for the registered string, if any.
 */
export type Registered = { module: string; file: string; exportName: string | null };

/**
 * Where a part of the `Shopware` global comes from: `import` names the module and export that hold the same
 * value, `function` a function that dependencies.ts must reach through the global when it is called, because
 * code may replace or spy on it there. `consumed` counts the segments of the path it covers, `name` is the
 * name dependencies.ts gives it. `fromPackage` says the value comes from a package, so the resolver cannot
 * tell a function from a class.
 *
 * @example
 * // Shopware.Data.Criteria
 * { kind: 'import', module: 'src/core/data/criteria.data', imported: 'default', name: 'Criteria', consumed: 2 }
 * // Shopware.Utils.format.fileSize
 * { kind: 'function', name: 'fileSize', consumed: 3 }
 */
export type GlobalSource =
    | { kind: 'import'; module: string; imported: string; name: string; consumed: number; fromPackage?: boolean }
    | { kind: 'function'; name: string; consumed: number };

/**
 * How the global types know a name: `typed`, `any` when they type it as `$TSFixMe` or `any`,
 * or null when they do not list it, so TypeScript would reject a lookup of it in dependencies.ts.
 */
export type Typing = 'typed' | 'any' | null;

/**
 * What the rewrites know about the Administration outside the module: which names are services,
 * which file registers a mixin, store or filter, and where parts of the `Shopware` global come from.
 * `index.ts` builds it from the files; specs pass a small fake.
 */
export type AdminIndex = {
    isService: (name: string) => boolean;
    hasServiceName: (name: string) => boolean;
    /** How `ServiceContainer`, `PiniaRootState` or `FilterTypes` in the global types know a name. */
    typing: (container: 'ServiceContainer' | 'PiniaRootState' | 'FilterTypes', name: string) => Typing;
    /** The module, as dependencies.ts imports it, whose default export is the registered mixin. */
    findMixin: (name: string) => string | null;
    findStore: (id: string) => Registered | null;
    findFilter: (name: string) => Registered | null;
    findComponentHelper: (name: string) => { module: string; imported: string } | null;
    resolveGlobal: (path: string[]) => GlobalSource | null;
};

/**
 * A place in `src/` where the code reaches the `Shopware` global, directly or through a destructured name:
 * `node` is the longest static chain, `path` its segments after `Shopware`, and `call` the call it is the callee of.
 *
 * @example
 * // new Shopware.Data.Criteria(1, 25)
 * { path: ['Data', 'Criteria'], node: <Shopware.Data.Criteria>, call: undefined }
 */
export type GlobalUse = {
    node: ts.Expression;
    path: string[];
    call?: ts.CallExpression;
};

/** Everything a rewrite may look at: the file, its module, the Administration, and the names the file declares. */
export type RewriteContext = {
    source: ts.SourceFile;
    index: AdminIndex;
    /** The absolute paths of the file, of the module root, and of the module's dependencies.ts. */
    fileName: string;
    moduleRoot: string;
    dependenciesFile: string;
    /** How often the file declares a name, in any scope. */
    declared: Map<string, number>;
    /** Whether `src/` may import a dependency under this local name without clashing with a name the file declares. */
    isFree: (name: string) => boolean;
};

/**
 * Rewrites one use of the `Shopware` global, or returns null to leave it for the sweep.
 * Each kind of use has its own function, so each can be read and tested on its own.
 */
export type UseRewrite = (use: GlobalUse, context: RewriteContext) => Rewrite | null;

/** Rewrites what a file says outside a `Shopware` use, such as the names in `inject`, or its imports. */
export type FileRewrite = (context: RewriteContext) => Rewrite[];

/** Upper-cases the first letter: `notification` → `Notification`. */
export const upperFirst = (text: string) => `${text.charAt(0).toUpperCase()}${text.slice(1)}`;

/** Turns a registered name into an identifier part: `decode-idn-email` → `decodeIdnEmail`. */
export const camelCase = (text: string) =>
    text.replace(/[-_.\s]+([a-zA-Z0-9])/g, (_, letter: string) => letter.toUpperCase());

/** Whether a string can be written as an identifier. */
export const isIdentifierName = (text: string) => /^[A-Za-z_$][\w$]*$/.test(text);
