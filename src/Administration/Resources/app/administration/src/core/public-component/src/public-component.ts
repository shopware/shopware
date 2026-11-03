import type { ExtractPropTypes } from '../dependencies';

/**
 * @sw-package framework
 */

/**
 * The names an option declares, written as an array (`emits: ['save']`) or as an object (`methods: { save() {} }`).
 */
type OptionNames<Option> = Option extends readonly (infer Name)[]
    ? Extract<Name, string>
    : Option extends object
      ? Extract<keyof Option, string>
      : never;

type OptionOf<Component, Key extends PropertyKey> = Component extends { [K in Key]?: infer Option }
    ? NonNullable<Option>
    : never;

type OrEmpty<Type> = [Type] extends [never] ? object : Type;

/**
 * Whether the component comes from a `.vue` file, typed by the `*.vue` shim or by vue-tsc: either way a
 * constructor whose options TypeScript cannot read.
 */
type IsSfc<Component> = Component extends abstract new (...args: never[]) => unknown ? true : false;

/**
 * The names a component declares for one kind of member, or any string while its type is unknown.
 * A `.vue` file's names are checked by the build against `index.ts` instead.
 */
type NamesOf<Component, Key extends PropertyKey> = unknown extends Component
    ? string
    : IsSfc<Component> extends true
      ? string
      : OptionNames<OptionOf<Component, Key>>;

/**
 * The names a public API lists for one kind of member.
 */
type Listed<Api, Key extends keyof PublicComponentApi> = Api extends { [K in Key]?: readonly (infer Name)[] }
    ? Extract<Name, string>
    : never;

type ComputedValues<Computed> = {
    [K in keyof Computed]: Computed[K] extends (...args: never[]) => infer Value
        ? Value
        : Computed[K] extends { get: (...args: never[]) => infer Value }
          ? Value
          : unknown;
};

/**
 * What `this` is inside a component: a typed instance for a TypeScript component,
 * and its props, computed values and methods for a JavaScript one.
 */
type ComponentInstance<Component> = Component extends abstract new (...args: never[]) => infer Instance
    ? Instance
    : OrEmpty<ExtractPropTypes<OrEmpty<OptionOf<Component, 'props'>>>> &
          ComputedValues<OrEmpty<OptionOf<Component, 'computed'>>> &
          OrEmpty<OptionOf<Component, 'methods'>>;

/**
 * The component a declaration's `load` resolves to.
 *
 * @public
 */
export type LoadedComponent<Load> = Load extends () => Promise<infer Module>
    ? Module extends { default: infer Component }
        ? Component
        : Module
    : unknown;

/**
 * The parts of a component that other code and extensions may rely on.
 * Everything the lists leave out is private.
 * With the component's type, a name the component doesn't have is a type error.
 *
 * @public
 */
export type PublicComponentApi<Component = unknown> = {
    props?: readonly NamesOf<Component, 'props'>[];
    emits?: readonly NamesOf<Component, 'emits'>[];
    slots?: readonly string[];
    blocks?: readonly string[];
    computed?: readonly NamesOf<Component, 'computed'>[];
    methods?: readonly NamesOf<Component, 'methods'>[];
    // The swDefinePublic() bindings of a native-setup component. The build checks them, since a `.vue`
    // file has no type that names its bindings.
    setup?: readonly string[];
};

/**
 * A component as its mini-module declares it.
 * `load` imports the component lazily, so declaring it costs no bundle size.
 * A native-setup component lists its props, emits, slots, blocks and `swDefinePublic()` bindings (`setup`) too,
 * and the build fails when they differ from the component.
 * `name` keeps its literal type, so `Shopware.Component.override(SwExample.name, …)` checks the public API.
 * `extends` names the component it extends, which register.ts passes to `Shopware.Component.extend()`.
 *
 * @public
 */
export type PublicComponent<
    Load extends () => Promise<unknown> = () => Promise<unknown>,
    // definePublicComponent() checks the names; an extending component's names are unknown to the type
    Api extends object = PublicComponentApi,
    Name extends string = string,
> = {
    name: Name;
    extends?: string;
    load: Load;
    public?: Api;
};

/**
 * The public props, computed values and methods of a declared component: what a parent, a test or an override
 * may use.
 *
 * @example
 * const matrix = ref<PublicInstance<typeof SwSettingsShippingPriceMatrix>>();
 *
 * @public
 */
export type PublicInstance<Declaration> = Declaration extends { load: infer Load; public?: infer Api }
    ? Pick<
          ComponentInstance<LoadedComponent<Load>>,
          Extract<
              Listed<Api, 'props'> | Listed<Api, 'computed'> | Listed<Api, 'methods'> | Listed<Api, 'setup'>,
              keyof ComponentInstance<LoadedComponent<Load>>
          >
      >
    : never;

/**
 * The props, computed values and methods of a component declared with `definePrivateComponent()` that its parent
 * module may use, for a template ref or a private-api test. The same as `PublicInstance`, named for the private side.
 *
 * @example
 * const card = ref<PrivateInstance<typeof SwServiceCard>>();
 *
 * @public
 */
export type PrivateInstance<Declaration> = PublicInstance<Declaration>;

/**
 * The members of one kind an override may replace: the public ones with their original signature, new ones freely,
 * and none of the private ones.
 */
type Overridable<Members, PublicName> = {
    [K in keyof Members as K extends PublicName ? K : never]?: Members[K];
} & {
    [K in keyof Members as K extends PublicName ? never : K]?: never;
} & Record<string, unknown>;

/**
 * What `Shopware.Component.override()` accepts for a declared component.
 * It may replace public methods and computed properties, add its own, and calls `this.$super()` with a public
 * name only.
 *
 * @example
 * Shopware.Component.override('sw-settings-shipping-price-matrix', {
 *     methods: {
 *         onDeleteShippingPrice(price) {
 *             this.$super('onDeleteShippingPrice', price);
 *         },
 *     },
 * });
 *
 * @public
 */
export type PublicOverride<Declaration> = Declaration extends { load: infer Load; public?: infer Api }
    ? {
          methods?: Overridable<OrEmpty<OptionOf<LoadedComponent<Load>, 'methods'>>, Listed<Api, 'methods'>>;
          computed?: Overridable<OrEmpty<OptionOf<LoadedComponent<Load>, 'computed'>>, Listed<Api, 'computed'>>;
      } & Record<string, unknown> &
          ThisType<
              PublicInstance<Declaration> & {
                  $super: (name: Listed<Api, 'methods'> | Listed<Api, 'computed'>, ...args: unknown[]) => unknown;
              }
          >
    : never;

declare global {
    /**
     * Every component declared with `definePublicComponent()`, by name.
     * Each component's register.ts adds itself, so `Shopware.Component.override()` knows its public API.
     */
    // eslint-disable-next-line @typescript-eslint/no-empty-object-type -- each register.ts adds its component
    interface PublicComponents {}

    /**
     * Every globally registered component declared with `definePrivateComponent()`, by name.
     * Each one's global.d.ts adds itself, so `Shopware.Component.override()` inside the Administration knows its lists.
     * The extension type surface imports none of them, so for extensions such a name stays untyped.
     */
    // eslint-disable-next-line @typescript-eslint/no-empty-object-type -- each private module's global.d.ts adds its component
    interface PrivateComponents {}
}

/**
 * Declares a component in the index.ts of its mini-module, and the register.ts of the module registers it.
 * The `public` lists are checked against the component: a name it doesn't have is a type error.
 * A component that `extends` another one is registered with `Shopware.Component.extend()`, and its lists may name
 * what it inherits. TypeScript doesn't know the type of the component it extends, so only the meta test checks those.
 *
 * @example
 * // index.ts
 * export default definePublicComponent({
 *     name: 'sw-price-matrix',
 *     load: () => import('./src/sw-price-matrix'),
 *     public: { props: ['priceGroup'], blocks: ['sw_price_matrix'] },
 * });
 *
 * // register.ts
 * Shopware.Component.register(SwPriceMatrix);
 *
 * declare global {
 *     interface PublicComponents {
 *         'sw-price-matrix': typeof SwPriceMatrix;
 *     }
 * }
 *
 * @public
 */
export function definePublicComponent<
    Load extends () => Promise<unknown>,
    const Api extends PublicComponentApi<Base extends string ? unknown : LoadedComponent<Load>> = object,
    const Name extends string = string,
    const Base extends string | undefined = undefined,
>(component: {
    name: Name;
    extends?: Base;
    load: Load;
    public?: Api;
}): PublicComponent<Load, Api, Name> & (Base extends string ? { extends: Base } : unknown) {
    return component as PublicComponent<Load, Api, Name> & (Base extends string ? { extends: Base } : unknown);
}

/**
 * A component as a private mini-module declares it in its private.ts: how to load it, and what other
 * Administration code may use. A nested one has no global name, because its parent imports it and mounts it locally;
 * a registered one has its name, and `extends` when it extends another component.
 *
 * @public
 */
export type PrivateComponent<
    Load extends () => Promise<unknown> = () => Promise<unknown>,
    Api extends object = PublicComponentApi,
    Name extends string | undefined = string | undefined,
> = {
    name?: Name;
    extends?: string;
    load: Load;
    public?: Api;
};

/**
 * Declares a component in the private.ts of a private mini-module, which never reaches extensions.
 * The `public` lists name what other Administration code may use, and they are checked like those of
 * `definePublicComponent()`: a name the component doesn't have is a type error.
 *
 * A nested module, in the `src/` of its parent's module, has no name: the parent mounts it with
 * `defineAsyncComponent(SwExample.load)`.
 * A registered one has a name, its register.ts calls `Shopware.Component.register()` or `extend()`,
 * and its global.d.ts adds it to `PrivateComponents`.
 *
 * @example
 * // private.ts
 * export default definePrivateComponent({
 *     name: 'sw-service-card',
 *     load: () => import('./src/sw-service-card'),
 *     public: { props: ['service'] },
 * });
 *
 * @public
 */
export function definePrivateComponent<
    Load extends () => Promise<unknown>,
    const Api extends PublicComponentApi<Base extends string ? unknown : LoadedComponent<Load>> = object,
    const Name extends string | undefined = undefined,
    const Base extends string | undefined = undefined,
>(component: {
    name?: Name;
    extends?: Base;
    load: Load;
    public?: Api;
}): PrivateComponent<Load, Api, Name> &
    (Name extends string ? { name: Name } : unknown) &
    (Base extends string ? { extends: Base } : unknown) {
    return component as PrivateComponent<Load, Api, Name> &
        (Name extends string ? { name: Name } : unknown) &
        (Base extends string ? { extends: Base } : unknown);
}
