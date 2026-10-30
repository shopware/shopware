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
 * The names a component declares for one kind of member, or any string while its type is unknown.
 */
type NamesOf<Component, Key extends PropertyKey> = unknown extends Component
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
};

/**
 * A component as its mini-module declares it.
 * `load` imports the component lazily, so declaring it costs no bundle size.
 * A native-setup component leaves out `public`, because `swDefinePublic()` already declares it.
 *
 * @public
 */
export type PublicComponent<
    Load extends () => Promise<unknown> = () => Promise<unknown>,
    Api extends PublicComponentApi<LoadedComponent<Load>> = PublicComponentApi<LoadedComponent<Load>>,
> = {
    name: string;
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
              Listed<Api, 'props'> | Listed<Api, 'computed'> | Listed<Api, 'methods'>,
              keyof ComponentInstance<LoadedComponent<Load>>
          >
      >
    : never;

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
}

/**
 * Declares a component in the index.ts of its mini-module, and the register.ts of the module registers it.
 * The `public` lists are checked against the component: a name it doesn't have is a type error.
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
    const Api extends PublicComponentApi<LoadedComponent<Load>> = object,
>(component: { name: string; load: Load; public?: Api }): PublicComponent<Load, Api> {
    return component;
}
