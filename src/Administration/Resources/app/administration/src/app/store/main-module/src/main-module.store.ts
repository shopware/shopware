/**
 * @sw-package framework
 */
import { defineStoreOptions, getStore, type StoreOf } from '../dependencies';
/**
 * @public
 */
export type MainModule = {
    extensionName: string;
    moduleId: string;
};

interface MainModuleState {
    mainModules: MainModule[];
}

/**
 * @public
 * The id the store is registered under, for code that looks it up with `Shopware.Store.get()`.
 */
export const extensionMainModulesStoreId = 'extensionMainModules';

/**
 * @private
 * The options the store is registered with, see `register.ts`.
 */
export const extensionMainModulesStoreOptions = defineStoreOptions({
    id: extensionMainModulesStoreId,

    state: (): MainModuleState => ({
        mainModules: [],
    }),

    actions: {
        addMainModule({ extensionName, moduleId }: MainModule) {
            this.mainModules.push({
                extensionName,
                moduleId,
            });
        },
    },
});

/**
 * @public
 */
export type ExtensionMainModules = StoreOf<typeof extensionMainModulesStoreOptions>;

/**
 * @public
 * The store, looked up when it is called, so everyone who uses it shares one state.
 */
export const useExtensionMainModulesStore = (): ExtensionMainModules => getStore(extensionMainModulesStoreId);
