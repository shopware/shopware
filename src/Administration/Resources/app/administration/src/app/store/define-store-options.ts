/**
 * @sw-package framework
 */
import type {
    DefineStoreOptions,
    StateTree,
    Store,
    _ExtractActionsFromSetupStore,
    _ExtractGettersFromSetupStore,
    _ExtractStateFromSetupStore,
    _GettersTree,
} from 'pinia';

/**
 * @private
 *
 * Declares the options of a Pinia store without registering it, so a store module's `src/` stays free of side
 * effects and its `register.ts` hands them to `Shopware.Store.register()`. Pinia infers the state, getters and
 * actions, and the type of `this` inside them, as `Shopware.Store.register()` does.
 *
 * @example
 * export const swExampleStoreOptions = defineStoreOptions({
 *     id: 'swExample',
 *     state: () => ({ isLoading: false }),
 *     actions: { setLoading(isLoading: boolean) { this.isLoading = isLoading; } },
 * });
 */
export function defineStoreOptions<
    Id extends string,
    S extends StateTree = NonNullable<unknown>,
    G extends _GettersTree<S> = NonNullable<unknown>,
    A = NonNullable<unknown>,
>(options: DefineStoreOptions<Id, S, G, A>): DefineStoreOptions<Id, S, G, A> {
    return options;
}

/**
 * @private
 *
 * The store that options declared with `defineStoreOptions()` create, as `Shopware.Store.get()` returns it.
 *
 * @example
 * export type SwExampleStore = StoreOf<typeof swExampleStoreOptions>;
 */
export type StoreOf<Options> =
    Options extends DefineStoreOptions<infer Id, infer S, infer G, infer A> ? Store<Id, S, G, A> : never;

/**
 * @private
 *
 * The store that a setup function creates, as `Shopware.Store.get()` returns it. Use it for a store module whose
 * `register.ts` registers a composable instead of options.
 *
 * @example
 * export type ContextStore = SetupStoreOf<typeof contextStoreId, typeof useContext>;
 */
export type SetupStoreOf<Id extends string, Setup extends () => object> = Store<
    Id,
    _ExtractStateFromSetupStore<ReturnType<Setup>>,
    _ExtractGettersFromSetupStore<ReturnType<Setup>>,
    _ExtractActionsFromSetupStore<ReturnType<Setup>>
>;
