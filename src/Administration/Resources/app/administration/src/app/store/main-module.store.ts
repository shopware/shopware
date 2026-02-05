/**
 * @private
 * @sw-package framework
 */

import { useExtensionOrderedArray } from '../composables/use-extension-ordered-container';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export type MainModule = {
    extensionName: string;
    moduleId: string;
};

const extensionMainModules = Shopware.Store.register('extensionMainModules', () => {
    const mainModulesOrdered = useExtensionOrderedArray<MainModule>();

    const addMainModule = ({ extensionName, moduleId }: MainModule) => {
        mainModulesOrdered.push({
            extensionName,
            moduleId,
        });
    };

    return {
        mainModules: mainModulesOrdered.items,
        addMainModule,
    };
});

/**
 * @private
 */
export type ExtensionMainModules = ReturnType<typeof extensionMainModules>;

/**
 * @private
 */
export default extensionMainModules;
