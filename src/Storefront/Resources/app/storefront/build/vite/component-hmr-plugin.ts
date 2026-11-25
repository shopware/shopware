import path from 'node:path';
import type { EnvironmentModuleNode, Plugin } from 'vite';

const COMPONENT_SCRIPT = /\/Resources\/views\/components\/.+(?<!\.d|\.test|\.spec)\.(js|ts)$/;

/**
 * Accepts the update in the module and hands the new class to `src/dev/twig-hmr/component-hmr.js`.
 * Without the client, or if it cannot swap the class, only this page reloads.
 * `import.meta.hot.invalidate()` is not used: no importer accepts a component,
 * so Vite would reload every open page, also the ones that swapped the class.
 */
export const COMPONENT_HMR_FOOTER = `
if (import.meta.hot) {
    import.meta.hot.accept((module) => {
        if (!window.swComponentHmr?.replace(import.meta.url, module?.default)) {
            console.info('[sw-component-hmr] The component could not be swapped in place, reloading the page.');
            window.location.reload();
        }
    });
}
`;

/**
 * Vite plugin — swaps the class of a changed component of the component system without a page reload.
 *
 * Appends an HMR handler to every script under `Resources/views/components/`.
 * Helper modules that a component imports pass their updates up to it.
 * A change of the component system itself, `src/shopware.ts` and its imports, reloads the page,
 * because a second copy of `window.Shopware` would lose all instances.
 *
 * @param storefrontRoot  Absolute path to `Resources/app/storefront`.
 */
export function componentHmrPlugin(storefrontRoot: string): Plugin {
    const runtimeEntry = path.join(storefrontRoot, 'src/shopware.ts');

    const isImportedByRuntime = (modules: EnvironmentModuleNode[]): boolean => {
        const seen = new Set<EnvironmentModuleNode>();
        const queue = [...modules];

        while (queue.length > 0) {
            const module = queue.pop() as EnvironmentModuleNode;
            if (seen.has(module)) {
                continue;
            }

            seen.add(module);
            if (module.file === runtimeEntry) {
                return true;
            }

            queue.push(...Array.from(module.importers));
        }

        return false;
    };

    return {
        name: 'sw-component-hmr',
        apply: 'serve',

        transform(code, id) {
            if (this.environment.name !== 'client' || !COMPONENT_SCRIPT.test(id.replace(/\?.*/, ''))) {
                return undefined;
            }

            return { code: `${code}\n${COMPONENT_HMR_FOOTER}`, map: null };
        },

        hotUpdate({ modules }) {
            if (this.environment.name !== 'client' || !isImportedByRuntime(modules)) {
                return undefined;
            }

            this.environment.hot.send({ type: 'full-reload' });

            return [];
        },
    };
}
