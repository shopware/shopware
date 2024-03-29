/**
 * @package admin
*/
/* eslint-disable sw-test-rules/await-async-functions */
import { defineAsyncComponent } from 'vue';
// eslint-disable-next-line import/no-unresolved, import/extensions
import components from './component-imports';
import syncComponents from './syncComponents';

async function importComponent(componentName) {
    // Check if the component is registered in the component-imports.js.
    // If not, the component is not wrapped and needs to be resolved manually.
    if (!components[componentName]) {
        throw new Error(`Component ${componentName} not found in component-imports.js. Resolve imports manually.`);
    }

    /**
     * @see type componentInfo in scripts/componentImportResolver/generate.ts
     */
    const componentConfig = components[componentName];
    /**
     * Contains the component configuration in all cases.
     * Depending on how the component is registered or extended, the component may or may not be registered or extended just by the import statement.
     * The componentConfig flags r for registration and e for extension are used to determine if the component needs to be registered or extended after the import.
     */
    const component = await import(componentConfig.p);

    // The component still needs registration after the import statement
    if (componentConfig.r === true) {
        Shopware.Component.register(componentName, component);
    }

    // The component extends another component check the extended component is registered
    if (componentConfig.en) {
        if (!Shopware.Component.getComponentRegistry().has(componentConfig.en)) {
            // The component requested to extend is not yet registered
            await importComponent(componentConfig.en);
        }
    }

    // The component still needs extension after the import statement
    if (componentConfig.e) {
        Shopware.Component.extend(componentName, componentConfig.en, component);
    }

    return component;
}

/**
 * Resolves component imports, registration and extensions. Wraps the component in an async component if needed.
 *
 * @private
 * @returns Promise<Component>
 */
export default async function wrapTestComponent(componentName, config = {}) {
    if (arguments.length > 2) {
        throw new Error('wrapTestComponent expects only two arguments.');
    }
    // Imports the component and handles registration and extensions
    await importComponent(componentName);

    // If the component is sync or the config has a sync flag, return the component directly
    if (syncComponents.includes(componentName) || config?.sync === true) {
        return new Promise((resolve) => {
            Shopware.Component.build(componentName).then((res) => {
                // Workaround for vue-test-utils to not trigger endless loops
                res.name += '__wrapped';

                resolve(res);
            });
        });
    }

    return defineAsyncComponent({
        loader: () => {
            return new Promise((resolve) => {
                Shopware.Component.build(componentName).then((res) => {
                    // Workaround for vue-test-utils to not trigger endless loops
                    res.name += '__wrapped';

                    resolve(res);
                });
            });
        },
        delay: 0,
        loadingComponent: {
            name: 'AsyncComponentWrapper',
            template: `<div>Loading ${componentName} async</div>`,
        },
    });
}
