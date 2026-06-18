/**
 * @sw-package framework
 */

import type { Plugin } from 'vite';
import path from 'node:path';
import type { transformShopwareSetupSfc as transformShopwareSetupSfcRuntime } from '../../vue-setup-transform';

type ShopwareSetupTransformModule = {
    transformShopwareSetupSfc: typeof transformShopwareSetupSfcRuntime;
};

type Options = {
    administrationRoot: string;
};

const emptySourceMap = {
    version: 3,
    sources: [],
    names: [],
    mappings: '',
};

/**
 * Keep the CommonJS transform out of Vite's config bundle.
 *
 * The shared transform is intentionally still CommonJS because Jest, ESLint, and
 * Volar consume it synchronously. Vite bundles `vite.config.mts` with esbuild by
 * default; if the transform is statically imported there, its `require()` calls
 * are inlined into an ESM config bundle and fail at runtime.
 */
async function loadShopwareSetupTransform(
    administrationRoot: string,
): Promise<typeof transformShopwareSetupSfcRuntime> {
    const transformModule = await import(
        path.join(administrationRoot, 'build/vue-setup-transform/index.js')
    ) as ShopwareSetupTransformModule;

    return transformModule.transformShopwareSetupSfc;
}

/**
 * @private
 *
 * Runs before @vitejs/plugin-vue so Vue only ever sees standard SFC syntax.
 * Parser-sensitive behavior stays in build/vue-setup-transform for reuse by Jest,
 * ESLint, and editor tooling.
 */
export default function ShopwareSetupPlugin(options: Options): Plugin {
    return {
        name: 'shopware-vite-plugin-shopware-setup',
        enforce: 'pre',

        async transform(code, id) {
            const fileName = id.split('?')[0];

            if (!fileName.endsWith('.vue')) {
                return null;
            }

            const transformShopwareSetupSfc = await loadShopwareSetupTransform(options.administrationRoot);
            const result = transformShopwareSetupSfc(code, fileName);

            if (!result) {
                return null;
            }

            return {
                code: result.code,
                map: result.map ?? emptySourceMap,
            };
        },
    };
}
