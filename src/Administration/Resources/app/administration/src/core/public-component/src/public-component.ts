/**
 * @sw-package framework
 */

/**
 * The parts of a component that other code and extensions may rely on.
 * Everything the lists leave out is private.
 */
export type PublicComponentApi = {
    props?: string[];
    emits?: string[];
    slots?: string[];
    blocks?: string[];
    computed?: string[];
    methods?: string[];
};

/**
 * A component as its mini-module declares it.
 * `load` imports the component lazily, so declaring it costs no bundle size.
 * A native-setup component leaves out `public`, because `swDefinePublic()` already declares it.
 */
export type PublicComponent = {
    name: string;
    load: () => Promise<unknown>;
    public?: PublicComponentApi;
};

/**
 * Declares a component in the index.ts of its mini-module, and the register.ts of the module registers it.
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
 */
export function definePublicComponent<Component extends PublicComponent>(component: Component): Component {
    return component;
}
