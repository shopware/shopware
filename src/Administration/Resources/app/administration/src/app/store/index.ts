import { createPinia, defineStore } from 'pinia';
import type { Store as PiniaStore, Pinia, _GettersTree, DefineStoreOptions, StateTree } from 'pinia';


/**
 * @package admin
 * @private
 * @deprecated: tag:v6.7.0 - Will be public
 */
export default class Store {
    // eslint-disable-next-line no-use-before-define
    static #instance: Store;

    static #stores = new Map<keyof PiniaRootState, PiniaStore<keyof PiniaRootState>>();

    /**
     * @private - Only to be used by vue.adapter.ts
     */
    _rootState: Pinia;

    private constructor() {
        this._rootState = createPinia();
    }

    /**
     * @private
     */
    public static get instance(): Store {
        if (!Store.#instance) {
            Store.#instance = new Store();
        }

        return Store.#instance;
    }

    /**
     * Returns a list of all registered Pinia store ids.
     */
    public list(): string[] {
        return Object.keys(this._rootState.state.value);
    }

    /**
     * Get the Pinia store with the given id.
     */
    public get<
        Id extends keyof PiniaRootState,
        S extends PiniaRootState[Id]['state'],
        G extends PiniaRootState[Id]['getters'],
        A extends PiniaRootState[Id]['actions']
    >(id: Id): PiniaStore<Id, S, G, A> {
        const piniaStore = Store.#stores.get(id);
        if (!piniaStore) {
            throw new Error(`Store with id "${id}" not found`);
        }

        // eslint-disable-next-line @typescript-eslint/no-unsafe-return
        return piniaStore as unknown as PiniaStore<Id, S, G, A>;
    }

    /**
     * Register a new Pinia store. Works similar like Vuex's registerModule.
     */
    public register<
        Id extends keyof PiniaRootState,
        S extends StateTree = NonNullable<unknown>,
        G extends _GettersTree<S> = NonNullable<unknown>,
        A = NonNullable<unknown>
    >(
        options: DefineStoreOptions<Id, S, G, A>,
    ): void {
        // Create new pinia store by calling the useStore function
        const newStore = (defineStore(options))();

        // Cache the store in internal map
        // @ts-expect-error - Pinia type includes internals, which we don't want to mirror here because of stability
        Store.#stores.set(options.id, newStore);
    }

    /**
     * Unregister a Pinia store. Works similar like Vuex's unregisterModule.
     */
    public unregister(id: keyof PiniaRootState): void {
        const piniaStore = Store.#stores.get(id);
        if (!piniaStore) {
            return;
        }

        // Stop reactive effects
        piniaStore.$dispose();

        // Delete store in root state
        delete this._rootState.state.value[piniaStore.$id];

        // Clear cached store
        Store.#stores.delete(id);
    }
}
