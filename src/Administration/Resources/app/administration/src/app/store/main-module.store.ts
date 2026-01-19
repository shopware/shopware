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

const extensionMainModules = Shopware.Store.register({
    id: 'extensionMainModules',

    state: (): MainModuleState => ({
        mainModules: [],
    }),

    actions: {
        addMainModule({ extensionName, moduleId }: MainModule) {
            // Check if main module with same moduleId already exists to prevent duplicates on HMR
            const existingIndex = this.mainModules.findIndex((item) => item.moduleId === moduleId);

            if (existingIndex !== -1) {
                // Update existing main module
                this.mainModules[existingIndex] = { extensionName, moduleId };
                return;
            }

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
