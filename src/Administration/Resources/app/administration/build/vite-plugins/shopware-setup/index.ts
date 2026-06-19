/**
 * @sw-package framework
 */

import type { Plugin } from 'vite';
import path from 'node:path';
import type { transformShopwareSetupSfc as transformShopwareSetupSfcRuntime } from '../../vue-setup-transform';

type ShopwareSetupTransformModule = {
    transformShopwareSetupSfc: typeof transformShopwareSetupSfcRuntime;
};
type ShopwareSetupTransformImport = ShopwareSetupTransformModule | {
    default: ShopwareSetupTransformModule;
};
type ShopwareSetupTransformResult = NonNullable<ReturnType<typeof transformShopwareSetupSfcRuntime>>;

type Options = {
    administrationRoot: string;
};

function withoutQuery(id: string): string {
    return id.split('?')[0];
}

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
    const transformImport = await import(
        path.join(administrationRoot, 'build/vue-setup-transform/index.js')
    ) as ShopwareSetupTransformImport;
    const transformModule = 'default' in transformImport
        ? transformImport.default
        : transformImport;

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
    async function transformCode(
        code: string,
        fileName: string,
    ): Promise<ShopwareSetupTransformResult | null> {
        const transformShopwareSetupSfc = await loadShopwareSetupTransform(options.administrationRoot);

        return transformShopwareSetupSfc(code, fileName);
    }

    return {
        name: 'shopware-vite-plugin-shopware-setup',
        enforce: 'pre',

        async transform(code, id) {
            const fileName = withoutQuery(id);

            if (!fileName.endsWith('.vue')) {
                return null;
            }

            const result = await transformCode(code, fileName);

            if (!result) {
                return null;
            }

            return {
                code: result.code,
                map: result.map,
            };
        },
    };
}
