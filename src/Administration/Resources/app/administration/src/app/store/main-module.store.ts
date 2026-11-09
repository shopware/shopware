/**
 * @private
 * @sw-package framework
 */

type MainModule = {
    extensionName: string;
    moduleId: string;
};

interface MainModuleState {
    mainModules: MainModule[];
}

const extensionMainModules = Shopware.Store.register({
    id: 'extensionMainModules',

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
