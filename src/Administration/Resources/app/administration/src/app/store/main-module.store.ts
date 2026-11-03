/**
 * @private
 * @sw-package framework
 */

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export type MainModule = {
    extensionName: string;
    moduleId: string;
};

interface MainModuleState {
    mainModules: MainModule[];
}

/**
 * @private
 * The id the store is registered under, for code that looks it up with `Shopware.Store.get()`.
 */
export const extensionMainModulesStoreId = 'extensionMainModules';

const extensionMainModules = Shopware.Store.register({
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
 * @private
 */
export type ExtensionMainModules = ReturnType<typeof extensionMainModules>;

/**
 * @private
 */
export default extensionMainModules;
