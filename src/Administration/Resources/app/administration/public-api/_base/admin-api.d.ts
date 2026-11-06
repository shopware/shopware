/**
 * @sw-package framework
 *
 * Shared base types of the Administration public API snapshot.
 * Hand-written and versioned together with the generator, see public-api/FORMAT.md.
 *
 * This file is a global script (no import or export).
 * Generated snapshot files only add entries to the open registries declared here.
 * Plugins add their own entries from module files with `declare global { namespace AdminApi { ... } }`.
 *
 * It also merges typed call signatures into the global interfaces `ComponentOverrideFn` and
 * `WrapOverrideConfigFn`, which core declares in `src/global.types.ts` with the loose signatures of
 * `Shopware.Component.override` and `Shopware.Component.wrapOverrideConfig` (D20, D21).
 * TypeScript puts the overloads of a later declaration first, and the reference below makes sure that core
 * is always loaded before this file, so the typed signatures win whenever the target name is known.
 *
 * Markers that tsc does not evaluate (deprecation, experimental, member-level private)
 * are JSDoc tags on the generated entries. The break check and `admin:check-extensions` read them with ts-morph.
 * Flag notes such as `flag-removal-undeprecated` exist only in the `.api.md` reports.
 */

/// <reference path="../../src/global.types.ts" />
/// <reference path="../../src/entity-schema-definition.d.ts" />

declare namespace AdminApi {
    // ---------------------------------------------------------------------------------------------
    // Registries
    // ---------------------------------------------------------------------------------------------

    /** Registered components by name (`Component.register` / `Component.extend` / native `.vue`). */
    interface Components {}

    /** Core-internal `Component.override` calls, keyed `<target> @ <source directory>`. */
    interface ComponentOverrides {}

    /** Registered mixins by name (`Mixin.register`). */
    interface Mixins {}

    /** Registered modules by id (`Module.register`). */
    interface Modules {}

    /** Named routes, children flattened by their full name. */
    interface Routes {}

    /** Flattened English snippet keys. Texts are never part of the snapshot. */
    interface SnippetKeys {}

    /** Services by registered name, including services missing from `ServiceContainer`. */
    interface Services {}

    /** Pinia stores by id. */
    interface Stores {}

    /**
     * Switches for plugins. A plugin opts in by augmenting this interface:
     *
     *     declare global { namespace AdminApi { interface Options { strictPublicApi: true } } }
     *
     * `strictPublicApi: true` hides `@private` components and mixins from the helpers below.
     */
    interface Options {}

    // Copies of the source types the snapshot references (D16) live in `namespace AdminApi.Src.<path>`.
    // They are generated into `public-api/types/` and only exist so that a change to them is a diff.
    // The contract itself keeps `import('src/...')` references, so plugin values keep their identity.

    // ---------------------------------------------------------------------------------------------
    // Value markers
    // ---------------------------------------------------------------------------------------------

    /**
     * The source declares no type (a JS config or an unannotated TS member).
     * Replacing it with a concrete type later is never a break.
     */
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    type Untyped = any;

    /** JS prop declared with `type: Object`. */
    type JsObject = Untyped;

    /** JS prop declared with `type: Array`. */
    type JsArray = Untyped[];

    /** JS prop declared with `type: Function`. */
    type JsFunction = (...args: Untyped[]) => Untyped;

    /** Type of a service by registered name. Names missing from `ServiceContainer` are `Untyped`. */
    type Service<K extends string> = K extends keyof ServiceContainer ? ServiceContainer[K] : Untyped;

    type Visibility = 'public' | 'private';

    /**
     * Where the effective visibility comes from.
     * A private component or mixin export makes the whole entry private, including its name and blocks (D13).
     */
    type VisibilitySource = 'default' | 'export' | 'registration' | 'member' | 'structural';

    // ---------------------------------------------------------------------------------------------
    // Component entries
    // ---------------------------------------------------------------------------------------------

    type PropDefault = 'none' | 'literal' | 'factory';

    /**
     * `Prop<string, true>` is required.
     * `Prop<boolean, false, 'literal'>` is optional with a literal default.
     */
    interface Prop<T, Required extends boolean = false, Default extends PropDefault = 'none'> {
        type: T;
        required: Required;
        default: Default;
    }

    /** A named slot from a `<slot>` element of the template, with its bound slot props. */
    interface Slot<Scope extends object = {}> {
        scope: Scope;
        block: string | null;
    }

    /**
     * Facets of a block:
     * `empty` - the block has no content, it only exists as a hook.
     * `inherited` - an extend child takes the block unchanged from its parent.
     * `replaced` - an extend child redeclares the block without `{% parent %}`.
     * `keepsParent` - an extend child redeclares the block and renders `{% parent %}`.
     * `repeated` - the template declares the block more than once under this parent.
     * `afterIf` - the block follows an open `v-if` chain, so an override may start with `v-else-if` or `v-else`.
     * `scope:<name>` - a template variable from an enclosing `v-for` or slot that an override can use.
     */
    type BlockFacet = 'empty' | 'inherited' | 'replaced' | 'keepsParent' | 'repeated' | 'afterIf' | `scope:${string}`;

    /**
     * One Twig `{% block %}` or native `<sw-block name>`.
     * `Parent` is the directly enclosing block, `null` at the root.
     * A name that occurs under several parents has a union of parents.
     */
    interface Block<Parent extends string | null, Facets extends BlockFacet = never> {
        parent: Parent;
        facets: Facets;
    }

    /**
     * An HTML element opened in one block and closed in another, or a `v-if` chain (element `v-if`) that continues in another block.
     * `(root)` stands for "outside any block".
     * Crossings that exist on trunk are part of the baseline; only new ones are findings (D18).
     */
    type Crossing = readonly [element: string, openedIn: string, closedIn: string];

    type PositionIdentifierKind = 'componentSection' | 'card' | 'tabs' | 'teaserPopover' | 'attribute';

    interface PositionLocation {
        kind: PositionIdentifierKind;
        host: string;
        block: string | null;
    }

    interface DataSet {
        path: string;
    }

    interface ComponentMeta {
        kind: 'register' | 'extend';
        lang: 'js' | 'ts' | 'vue';
        visibility: Visibility;
        visibilitySource: VisibilitySource;
        extends: string | null;
    }

    interface ComponentShape {
        $meta: ComponentMeta;
        props: Record<string, Prop<unknown, boolean, PropDefault>>;
        /** Event name to payload tuple. */
        emits: Record<string, readonly unknown[]>;
        slots: Record<string, Slot>;
        /** In declaration order. */
        mixins: readonly string[];
        /** Local key to injected key. */
        inject: Record<string, string>;
        provide: Record<string, unknown>;
        data: Record<string, unknown>;
        /** Computed name to value type. Every entry is a `$super` target. */
        computed: Record<string, unknown>;
        /** Every entry is a `$super` target. */
        methods: Record<string, (...args: Untyped[]) => unknown>;
        /** Effective blocks of this component in this flag state. */
        blocks: Record<string, Block<string | null, BlockFacet>>;
        crossings: readonly Crossing[];
        positionIdentifiers: Record<string, readonly PositionLocation[]>;
        /** Position identifiers built at runtime, keyed by the source expression. */
        dynamicPositionIdentifiers: Record<string, readonly PositionLocation[]>;
        dataSets: Record<string, readonly DataSet[]>;
    }

    /**
     * How `createExtendableSetup` merges an override's value into a setup binding.
     * A change of kind is a break, because overrides that write or return the binding behave differently.
     */
    type SetupBindingKind = 'ref' | 'model' | 'computed' | 'writable-computed' | 'reactive' | 'function' | 'value';

    /** A top-level binding of a native `<script setup>`. `T` is the binding as a native override receives it. */
    interface SetupBinding<Kind extends SetupBindingKind, T> {
        kind: Kind;
        type: T;
    }

    /**
     * A native setup SFC (D17). `data`, `computed` and `methods` hold the `swDefinePublic` entries unwrapped,
     * the way Options API overrides reach them through `this` and `$super`.
     * `setup` holds every top-level binding as a native override receives it in `previousState`.
     */
    interface NativeComponentShape extends ComponentShape {
        $meta: ComponentMeta & { lang: 'vue' };
        setup: {
            public: Record<string, SetupBinding<SetupBindingKind, unknown>>;
            /** Bindings missing from `swDefinePublic`, reachable as `previousState._private`. Never a break. */
            private: Record<string, SetupBinding<SetupBindingKind, unknown>>;
        };
    }

    interface ComponentOverrideShape {
        target: string;
        source: string;
        mixins: readonly string[];
        inject: Record<string, string>;
        data: Record<string, unknown>;
        computed: Record<string, unknown>;
        methods: Record<string, (...args: Untyped[]) => unknown>;
        /** Blocks the override template declares, with their parent in the target's tree. */
        blocks: Record<string, Block<string | null, BlockFacet>>;
    }

    interface MixinShape {
        $meta: { visibility: Visibility; visibilitySource: VisibilitySource };
        props: Record<string, Prop<unknown, boolean, PropDefault>>;
        data: Record<string, unknown>;
        computed: Record<string, unknown>;
        methods: Record<string, (...args: Untyped[]) => unknown>;
    }

    // ---------------------------------------------------------------------------------------------
    // Modules, routes, snippets, services, stores
    // ---------------------------------------------------------------------------------------------

    interface NavigationEntry {
        path: string | null;
        parent: string | null;
        privilege: string | null;
        label: string;
    }

    interface ModuleShape {
        $meta: { visibility: Visibility; visibilitySource: VisibilitySource };
        type: 'core' | 'plugin';
        entity: string | null;
        title: string;
        routePrefixName: string;
        routePrefixPath: string;
        /** Keyed by navigation id, entries without an id by their route name. */
        navigation: Record<string, NavigationEntry>;
        settingsItem: { group: string; to: string; privilege: string | null } | null;
    }

    interface RouteShape {
        module: string;
        /** Full path as vue-router sees it, including `:param?` and custom regexes. */
        path: string;
        params: Record<string, string | undefined>;
        component: string | null;
        /** `null` for top-level routes. Only those reach a `routeMiddleware`. */
        parent: string | null;
        redirect: string | null;
        alias: string | null;
        coreRoute: boolean;
        hasPropsMapper: boolean;
        privilege: string | null;
        parentPath: string | null;
        appSystemView: 'list' | 'detail' | null;
    }

    /**
     * Signature of a snippet value, never its text (D19).
     * `Params` lists the named placeholders, `Forms` the number of `|` separated plural forms, or `array`.
     */
    interface Snippet<Params extends string = never, Forms extends 1 | 2 | 3 | 'array' = 1> {
        params: Params;
        forms: Forms;
    }

    interface ServiceShape {
        $meta: { visibility: Visibility; visibilitySource: VisibilitySource };
        /** `typed`: declared in `ServiceContainer`; `any`: declared as `$TSFixMe`; `untyped`: registered only. */
        declared: 'typed' | 'any' | 'untyped';
        registration: 'eager' | 'module' | 'initializer' | 'api-service';
        type: unknown;
    }

    interface StoreShape {
        $meta: { visibility: Visibility; visibilitySource: VisibilitySource };
        style: 'options' | 'setup';
        declared: 'typed' | 'untyped';
        state: Record<string, unknown>;
        getters: Record<string, unknown>;
        actions: Record<string, (...args: Untyped[]) => unknown>;
    }

    // ---------------------------------------------------------------------------------------------
    // Consistency checks. Instantiated by `current.d.ts` and `major.d.ts`.
    // They report errors only when the snapshot is compiled with `skipLibCheck: false` (drift check).
    // ---------------------------------------------------------------------------------------------

    type Conforms<T extends Record<keyof T, S>, S> = T;

    /** Every component with `$meta.lang: 'vue'` has the native shape. */
    type NativeConforms<T extends { [N in keyof T]: T[N] extends { $meta: { lang: 'vue' } } ? NativeComponentShape : unknown }> = T;

    type ComponentName = keyof Components & string;
    type MixinName = keyof Mixins & string;
    type RouteName = keyof Routes & string;
    type SnippetKey = keyof SnippetKeys & string;

    type CheckReferences<
        C extends { [N in keyof C]: { $meta: { extends: ComponentName | null }; mixins: readonly MixinName[] } },
        R extends {
            [K in keyof R]: {
                component: ComponentName | null;
                parent: RouteName | null;
                redirect: RouteName | null;
                parentPath: RouteName | null;
            };
        },
    > = [C, R];

    // ---------------------------------------------------------------------------------------------
    // Helpers for plugins: `this`, `$super`, overrides, string names
    // ---------------------------------------------------------------------------------------------

    type Strict = Options extends { strictPublicApi: true } ? true : false;

    type PublicComponentName = {
        [N in ComponentName]: Components[N] extends { $meta: { visibility: 'public' } } ? N : never;
    }[ComponentName];

    /** Component names a plugin may target. With `strictPublicApi` only public ones. */
    type TargetComponentName = Strict extends true ? PublicComponentName : ComponentName;

    type Get<T, K> = K extends keyof T ? T[K] : never;

    type ParentOf<N> = Get<AdminApi.Components, N> extends { $meta: { extends: infer P extends string } } ? P : never;

    /** Deepest `extends` chain the helpers follow. Core has depth 2; the generator fails above this. */
    type MaxDepth = [0, 0, 0, 0];

    /** A member section along the `extends` chain; the child wins on name collisions. */
    type Section<N, S extends string, D extends 0[] = []> = D['length'] extends MaxDepth['length']
        ? {}
        : [ParentOf<N>] extends [never]
          ? Get<Get<AdminApi.Components, N>, S>
          : Omit<Section<ParentOf<N>, S, [...D, 0]>, keyof Get<Get<AdminApi.Components, N>, S>> &
                Get<Get<AdminApi.Components, N>, S>;

    type UnionToIntersection<U> = (U extends unknown ? (u: U) => void : never) extends (i: infer I) => void
        ? I
        : never;

    type OverrideKeys<N> = {
        [K in keyof ComponentOverrides]: ComponentOverrides[K] extends { target: N } ? K : never;
    }[keyof ComponentOverrides];

    /** Members that core-internal overrides add to component `N`. */
    type OverrideSection<N, S extends string> = UnionToIntersection<
        { [K in OverrideKeys<N>]: Get<ComponentOverrides[K], S> }[OverrideKeys<N>]
    >;

    type PropValues<P> = {
        [K in keyof P]: P[K] extends Prop<infer T, infer R, infer D>
            ? R extends true
                ? T
                : D extends 'none'
                  ? T | undefined
                  : T
            : never;
    };

    type InjectValues<I> = { [K in keyof I]: I[K] extends string ? Service<I[K]> : Untyped };

    type MixinMembers<M> = M extends readonly (infer X)[]
        ? UnionToIntersection<
              X extends MixinName
                  ? Strict extends true
                      ? Mixins[X] extends { $meta: { visibility: 'public' } }
                          ? MixinValues<X>
                          : {}
                      : MixinValues<X>
                  : {}
          >
        : {};

    type MixinValues<X extends MixinName> = PropValues<Mixins[X]['props']> &
        Mixins[X]['data'] &
        Mixins[X]['computed'] &
        Mixins[X]['methods'];

    type ChainMixins<N, D extends 0[] = []> = D['length'] extends MaxDepth['length']
        ? []
        : [ParentOf<N>] extends [never]
          ? Get<Get<AdminApi.Components, N>, 'mixins'>
          : readonly [...ChainMixins<ParentOf<N>, [...D, 0]>, ...Get<Get<AdminApi.Components, N>, 'mixins'>];

    interface SuperFn<M, C> {
        <K extends keyof M & string>(
            name: K,
            ...args: M[K] extends (...a: infer A) => unknown ? A : never
        ): M[K] extends (...a: Untyped[]) => infer R ? R : never;
        <K extends keyof C & string>(name: K): C[K];
    }

    /**
     * Every component with its `extends` chain and core overrides resolved.
     * Indexed with a concrete name only, so a generic `N` never instantiates the recursive `Section`.
     */
    type Resolved = {
        [N in ComponentName]: {
            props: PropValues<Section<N, 'props'>>;
            data: Section<N, 'data'> & OverrideSection<N, 'data'>;
            computed: Section<N, 'computed'> & OverrideSection<N, 'computed'>;
            methods: Section<N, 'methods'> & OverrideSection<N, 'methods'>;
            inject: InjectValues<Section<N, 'inject'>> & InjectValues<OverrideSection<N, 'inject'>>;
            mixins: MixinMembers<ChainMixins<N>>;
        };
    };

    /** What `this` is inside component `N` and inside an override of it. */
    type Instance<N extends ComponentName> = Resolved[N]['props'] &
        Resolved[N]['data'] &
        Resolved[N]['computed'] &
        Resolved[N]['methods'] &
        Resolved[N]['inject'] &
        Resolved[N]['mixins'] &
        import('vue').ComponentPublicInstance & {
            $super: SuperFn<Resolved[N]['methods'], Resolved[N]['computed']>;
        };

    /** Names valid as `$super(name)` in an override of `N`. */
    type SuperName<N extends ComponentName> = keyof (Resolved[N]['methods'] & Resolved[N]['computed']) & string;

    /**
     * What a native override of `N` receives as `previousState`:
     * the public bindings at the top level and the private ones under `_private`.
     */
    type PreviousState<N extends ComponentName> = Components[N] extends {
        setup: { public: infer Pub; private: infer Priv };
    }
        ? { [K in keyof Pub]: Get<Pub[K], 'type'> } & { _private: { [K in keyof Priv]: Get<Priv[K], 'type'> } }
        : Record<PropertyKey, Untyped>;

    type OverrideThis<N extends ComponentName, M, C, I extends readonly string[]> = Instance<N> &
        M & { [K in keyof C]: C[K] extends (...args: Untyped[]) => infer R ? R : never } & InjectValues<{
            [K in I[number]]: K;
        }>;

    type OverrideConfig<N extends ComponentName, M, C, I extends readonly string[]> = {
        template?: string;
        inject?: I;
        mixins?: readonly unknown[];
        props?: Record<string, unknown>;
        emits?: readonly string[];
        data?: () => Record<string, unknown>;
        computed?: C;
        methods?: M;
        watch?: Record<string, unknown>;
    } & Omit<import('vue').ComponentOptions, 'methods' | 'computed' | 'data' | 'inject' | 'props' | 'emits' | 'watch'> &
        ThisType<OverrideThis<N, M, C, I>> & {
            methods?: CompatibleMethods<N, M>;
            computed?: CompatibleComputed<N, C>;
            // The index signature of `ComponentOptions` would also accept a function, so a lazy
            // `() => import('./file')` would match the object signature without its target being checked.
            call?: never;
        };

    // The compatibility checks are intersected into `OverrideConfig` instead of constraining `M` and `C`:
    // a constraint like `M extends CompatibleMethods<N, M>` makes tsc drop the `ThisType` of the config.

    /** A redefined base method keeps its name, so it must stay assignable to the base signature. */
    type CompatibleMethods<N extends ComponentName, M> = {
        [K in keyof M]: K extends keyof Resolved[N]['methods'] ? Resolved[N]['methods'][K] : (...args: Untyped[]) => Untyped;
    };

    /** A redefined base computed keeps its name, so its value must stay assignable. */
    type CompatibleComputed<N extends ComponentName, C> = {
        [K in keyof C]: K extends keyof Resolved[N]['computed'] ? () => Resolved[N]['computed'][K] : (...args: Untyped[]) => Untyped;
    };

    /** What `Shopware.Component.override` returns, unchanged from core. */
    type OverrideResult = () => Promise<import('src/core/factory/async-component.factory').ComponentConfig>;

    /** Type-only key. A symbol, because the string index signature of `ComponentOptions` would swallow a string key. */
    const overrideTarget: unique symbol;

    /**
     * An override config typed by `Shopware.Component.wrapOverrideConfig`.
     * The `overrideTarget` brand exists only in the type, so a lazy `() => import('./file')` is matched to its target.
     */
    type WrappedOverrideConfig<N extends ComponentName, Config = unknown> = Config & { readonly [overrideTarget]: N };

    /** Every block a template override of `N` can target in this flag state. */
    type BlockName<N extends ComponentName> =
        | (keyof Components[N]['blocks'] & string)
        | (keyof OverrideSection<N, 'blocks'> & string);

    /** Template variables that an override of block `B` of component `N` can use. */
    type BlockScope<N extends ComponentName, B extends keyof Components[N]['blocks']> =
        Components[N]['blocks'][B] extends Block<string | null, infer F>
            ? F extends `scope:${infer V}` ? V : never
            : never;

    /** `true` when an override of block `B` may start with `v-else-if` or `v-else`. */
    type BlockAfterIf<N extends ComponentName, B extends keyof Components[N]['blocks']> =
        Components[N]['blocks'][B] extends Block<string | null, infer F>
            ? 'afterIf' extends F ? true : false
            : false;

    type CardIds<K extends string, Locations> = Locations extends readonly (infer L)[]
        ? [Extract<L, { kind: 'card' }>] extends [never]
            ? K
            : K | `${K}__before` | `${K}__after`
        : K;

    /** Static position identifiers, including the `__before` / `__after` ids of card hosts. */
    type PositionIdentifierName = {
        [N in ComponentName]: {
            [K in keyof Components[N]['positionIdentifiers'] & string]: CardIds<
                K,
                Components[N]['positionIdentifiers'][K]
            >;
        }[keyof Components[N]['positionIdentifiers'] & string];
    }[ComponentName];

    type DataSetId = { [N in ComponentName]: keyof Components[N]['dataSets'] & string }[ComponentName];

    /** Top-level routes. Only these reach a `routeMiddleware`, so only these can receive injected children. */
    type MiddlewareRouteName = { [R in RouteName]: Routes[R] extends { parent: null } ? R : never }[RouteName];

    type RouteParams<R extends RouteName> = Routes[R] extends { params: infer P } ? P : never;
}

/**
 * Core's loose signatures exclude these names, so a typed call that fails never falls back to them.
 * With `strictPublicApi` this includes private components, which then cannot be overridden without a cast.
 */
interface ComponentOverrideTypedTargets extends AdminApi.Components {}

/**
 * Typed signature of `Shopware.Component.override` (D20).
 * Core declares the loose signature for every name outside `ComponentOverrideTypedTargets`.
 * A call whose target is unknown to the snapshot, for example a component of another plugin, uses the loose one.
 */
interface ComponentOverrideFn {
    <
        N extends AdminApi.TargetComponentName,
        M = {},
        C = {},
        const I extends readonly string[] = [],
    >(
        componentName: N,
        componentConfiguration: AdminApi.OverrideConfig<N, M, C, I>,
        overrideIndex?: number | null,
    ): AdminApi.OverrideResult;
    <N extends AdminApi.TargetComponentName>(
        componentName: N,
        componentConfiguration: () => Promise<{ default: AdminApi.WrappedOverrideConfig<NoInfer<N>> }>,
        overrideIndex?: number | null,
    ): AdminApi.OverrideResult;
}

/**
 * Typed signature of `Shopware.Component.wrapOverrideConfig` (D21).
 * An override config in its own file gets the same `this`, `$super` and compatibility checks as an inline one.
 */
interface WrapOverrideConfigFn {
    <
        N extends AdminApi.TargetComponentName,
        M = {},
        C = {},
        const I extends readonly string[] = [],
    >(
        componentName: N,
        componentConfiguration: AdminApi.OverrideConfig<N, M, C, I>,
    ): AdminApi.WrappedOverrideConfig<N, AdminApi.OverrideConfig<N, M, C, I>>;
}
