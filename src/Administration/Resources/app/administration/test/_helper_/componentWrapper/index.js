/**
 * @sw-package framework
 */

/* eslint-disable sw-test-rules/await-async-functions */
import { defineAsyncComponent } from 'vue';
import components from './component-imports';
import syncComponents from './syncComponents';

const registryCache = new Map();

const IMPORT_MAP_FILE = 'test/_helper_/componentWrapper/component-imports.js';
const REGENERATE_HINT =
    `${IMPORT_MAP_FILE} is generated from the \`Component.register\`/\`Component.extend\` calls in ` +
    'src/. Run `npm run generate-component-import-resolver-map` (or use `composer run admin:unit`, ' +
    'which regenerates it) after moving or renaming a component.';

// eslint-disable-next-line no-control-regex
const ANSI_SGR_PATTERN = /\x1b\[[0-9;]*m/g;

/**
 * Whether `error` is Jest failing to resolve exactly `specifier`.
 *
 * It cannot be recognised by an error code: every generated `src/…` path matches the
 * `^src(.*)$` mapper, so a missing file there arrives as a bare `Error` with no `code` and an empty
 * `name`. Hence the match on the specifier itself, which also keeps everything the module body
 * throws - a compile error, a stale import *inside* the component - out of the relabel.
 */
function isUnresolvedSpecifierError(error, specifier) {
    if (typeof error?.message !== 'string') {
        return false;
    }

    // chalk colours the message unless Jest runs with `--ci`, and an escape sits right before the
    // specifier.
    return error.message.replace(ANSI_SGR_PATTERN, '').includes(`Could not locate module ${specifier} mapped as:`);
}

async function importComponent(componentName, requestedBy = null) {
    const requestedFor = requestedBy === null ? componentName : `${requestedBy} -> ${componentName}`;

    // Check if the component is registered in the component-imports.js.
    // If not, the component is not wrapped and needs to be resolved manually.
    if (!components[componentName]) {
        throw new Error(
            `Component ${requestedFor} has no entry in ${IMPORT_MAP_FILE}, so its import cannot be ` +
                `resolved. Either the component is registered somewhere the generator does not scan and ` +
                `has to be imported manually, or the map is stale: ${REGENERATE_HINT}`,
        );
    }

    // Check if the component is already registered and cached
    if (registryCache.has(componentName)) {
        return registryCache.get(componentName);
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
    let component;

    try {
        component = await import(componentConfig.p);
    } catch (error) {
        // A stale entry fails inside Jest's module resolution, which reports it as a `moduleNameMapper`
        // problem - the mapper is correct, the generated path is not. Name the real culprit instead.
        if (!isUnresolvedSpecifierError(error, componentConfig.p)) {
            throw error;
        }

        throw new Error(
            `Component ${requestedFor} resolved to "${componentConfig.p}" through ${IMPORT_MAP_FILE}, ` +
                `which could not be imported. ${REGENERATE_HINT}`,
            { cause: error },
        );
    }

    // The component still needs registration after the import statement
    if (componentConfig.r === true) {
        // eslint-disable-next-line sw-core-rules/enforce-async-component-registers
        Shopware.Component.register(componentName, component);
    }

    // The component extends another component check the extended component is registered
    if (componentConfig.en) {
        if (!Shopware.Component.getComponentRegistry().has(componentConfig.en)) {
            // The component requested to extend is not yet registered
            await importComponent(componentConfig.en, requestedFor);
        }
    }

    // The component still needs extension after the import statement
    if (componentConfig.e) {
        Shopware.Component.extend(componentName, componentConfig.en, component);
    }

    // Cache the component
    registryCache.set(componentName, component);

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
