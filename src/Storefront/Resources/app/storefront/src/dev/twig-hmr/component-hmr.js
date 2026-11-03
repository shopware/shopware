/* eslint no-console: 0 */

/**
 * Swaps the class of a changed component of the component system in the Vite dev server.
 * `build/vite/component-hmr-plugin.ts` adds a handler to every component module that calls `replace()`.
 *
 * @sw-package framework
 * @private
 */
import { LOG_PREFIX } from './twig-hmr';

function stripQuery(url) {
    const parsed = new URL(url, document.baseURI);

    return `${parsed.origin}${parsed.pathname}`;
}

/**
 * Finds the component name of a module URL in the import maps of the page.
 *
 * @param {string} moduleUrl
 * @returns {string|null}
 */
export function findComponentName(moduleUrl) {
    const target = stripQuery(moduleUrl);

    for (const script of document.querySelectorAll('script[type="importmap"]')) {
        let imports;
        try {
            imports = JSON.parse(script.textContent).imports ?? {};
        } catch {
            continue;
        }

        const match = Object.entries(imports).find(([name, url]) => !name.endsWith('/') && stripQuery(url) === target);
        if (match) {
            return match[0];
        }
    }

    return null;
}

/**
 * @param {string} moduleUrl - `import.meta.url` of the changed module
 * @param {unknown} componentClass - the default export of the new module
 * @returns {boolean} false if the page has to reload
 */
export function replace(moduleUrl, componentClass) {
    const componentName = findComponentName(moduleUrl);
    if (!componentName || typeof componentClass !== 'function' || typeof window.Shopware?.replaceComponent !== 'function') {
        return false;
    }

    try {
        const instances = window.Shopware.replaceComponent(componentName, componentClass);
        console.info(`${LOG_PREFIX} Component ${componentName} replaced in ${instances.length} element(s).`);

        return true;
    } catch (error) {
        console.error(`${LOG_PREFIX} Component ${componentName} could not be replaced.`, error);

        return false;
    }
}
