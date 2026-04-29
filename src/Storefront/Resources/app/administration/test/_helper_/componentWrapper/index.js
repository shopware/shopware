/**
 * @sw-package discovery
 *
 * wrapTestComponent helper for the Storefront's admin-modules. Loads its
 * companion component-imports.js (generated from this package's own src),
 * mirroring the Administration's wrapTestComponent helper.
 */

/* eslint-disable sw-test-rules/await-async-functions */
import path from 'path';
import components from './component-imports';
import syncComponents from './syncComponents';

// Vue lives in the Administration's node_modules — Storefront-admin has no
// node_modules of its own. Resolve via explicit path so jest's module resolver
// (which walks up from this file's location) doesn't fail.
//   <repo>/src/Storefront/Resources/app/administration/test/_helper_/componentWrapper
//   → up 7 → <repo>/src
const adminNodeModules = path.resolve(
    __dirname,
    '../../../../../../../Administration/Resources/app/administration/node_modules',
);
// eslint-disable-next-line import-x/no-dynamic-require, global-require
const { defineAsyncComponent } = require(require.resolve('vue', {
    paths: [adminNodeModules],
}));

const registryCache = new Map();

async function importComponent(componentName) {
    if (!components[componentName]) {
        throw new Error(`Component ${componentName} not found in storefront-admin component-imports.js. Resolve imports manually.`);
    }

    if (registryCache.has(componentName)) {
        return registryCache.get(componentName);
    }

    const componentConfig = components[componentName];
    const component = await import(componentConfig.p);

    if (componentConfig.r === true) {
        // eslint-disable-next-line sw-core-rules/enforce-async-component-registers
        Shopware.Component.register(componentName, component);
    }

    if (componentConfig.en) {
        if (!Shopware.Component.getComponentRegistry().has(componentConfig.en)) {
            await importComponent(componentConfig.en);
        }
    }

    if (componentConfig.e) {
        Shopware.Component.extend(componentName, componentConfig.en, component);
    }

    registryCache.set(componentName, component);

    return component;
}

/**
 * @private
 * @returns Promise<Component>
 */
export default async function wrapTestComponent(componentName, config = {}) {
    if (arguments.length > 2) {
        throw new Error('wrapTestComponent expects only two arguments.');
    }
    await importComponent(componentName);

    if (syncComponents.includes(componentName) || config?.sync === true) {
        return new Promise((resolve) => {
            Shopware.Component.build(componentName).then((res) => {
                res.name += '__wrapped';
                resolve(res);
            });
        });
    }

    return defineAsyncComponent({
        loader: () => {
            return new Promise((resolve) => {
                Shopware.Component.build(componentName).then((res) => {
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
