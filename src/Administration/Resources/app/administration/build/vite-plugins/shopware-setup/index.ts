/**
 * @sw-package framework
 */

import fs from 'node:fs/promises';
import { createRequire } from 'node:module';
import type { Plugin } from 'vite';
import type * as ShopwareSetupTransform from '../../vue-setup-transform';

// Loaded through the CommonJS bridge, the one entry every consumer shares. `createRequire` because
// vite.config.mts is bundled to ESM, where a bare `require` of the bridge's jiti dependency fails.
const { isDependencyFile, transformShopwareSetupSfc } = createRequire(__filename)(
    '../../vue-setup-transform/index.js',
) as typeof ShopwareSetupTransform;

// The rewritten SFC is compiled under this id rather than the real path because @vitejs/plugin-vue
// re-reads the raw file from disk in handleHotUpdate and caches descriptors from disk for ids that
// exist there; under the real path it would mix the author's source with the transformed one. The cost:
// that disk-only cache never holds a virtual id, so every edit reloads the component instead of
// re-rendering only its template.
const VIRTUAL_SUFFIX = '.shopware-setup.vue';

function isVirtualId(id: string): boolean {
    return id.endsWith(VIRTUAL_SUFFIX);
}

function isSetupCandidate(fileName: string): boolean {
    return fileName.endsWith('.vue') && !isVirtualId(fileName) && !isDependencyFile(fileName);
}

/**
 * @private
 *
 * Runs before @vitejs/plugin-vue so Vue only ever sees standard SFC syntax.
 */
export default function shopwareSetupPlugin(): Plugin {
    // One instance per build, so this catches collisions within a build, not across extensions.
    // Only bases claim a name: overrides reuse the base name by design.
    const baseComponentFiles = new Map<string, string>();

    function assertUniqueBaseComponent(componentName: string, fileName: string): void {
        const existing = baseComponentFiles.get(componentName);

        if (existing && existing !== fileName) {
            throw new Error(
                `Duplicate native setup base component name "${componentName}": "${existing}" and ` +
                    `"${fileName}" resolve to the same extendable component. Component names are derived from ` +
                    'filenames and must be unique within a build.',
            );
        }

        baseComponentFiles.set(componentName, fileName);
    }

    return {
        name: 'shopware-vite-plugin-shopware-setup',
        enforce: 'pre',

        async resolveId(source, importer) {
            if (source.includes('?') || !source.endsWith('.vue')) {
                return null;
            }

            const resolved = await this.resolve(source, importer, { skipSelf: true });
            const fileName = resolved?.id.split('?')[0];

            if (!fileName || !isSetupCandidate(fileName)) {
                return null;
            }

            return `${fileName}${VIRTUAL_SUFFIX}`;
        },

        async load(id) {
            if (id.includes('?') || !isVirtualId(id)) {
                return null;
            }

            const fileName = id.slice(0, -VIRTUAL_SUFFIX.length);

            // The real file is not a module of its own, so watch mode would otherwise miss its edits.
            this.addWatchFile(fileName);

            const source = await fs.readFile(fileName, 'utf8');
            const result = transformShopwareSetupSfc(source, fileName);

            // `null` means the SFC does not parse; plugin-vue reports that better than we can.
            if (!result) {
                return { code: source, map: null };
            }

            if (result.mode === 'base') {
                assertUniqueBaseComponent(result.componentName, fileName);
            }

            return { code: result.code, map: result.map };
        },

        // Vite derives hot updates from the changed file's modules, and the real file has none: without
        // this an edit would invalidate nothing.
        hotUpdate({ file, modules }) {
            if (!isSetupCandidate(file)) {
                return undefined;
            }

            const virtualModule = this.environment.moduleGraph.getModuleById(`${file}${VIRTUAL_SUFFIX}`);

            return virtualModule ? [...modules, virtualModule] : undefined;
        },

        watchChange(id, change) {
            // A move arrives as delete + create; releasing on delete keeps it from looking like a duplicate.
            // Not a `buildStart` reset: that runs per environment and would drop live claims.
            if (change.event !== 'delete') {
                return;
            }

            const fileName = id.split('?')[0];

            baseComponentFiles.forEach((claimedBy, componentName) => {
                if (claimedBy === fileName) {
                    baseComponentFiles.delete(componentName);
                }
            });
        },
    };
}
