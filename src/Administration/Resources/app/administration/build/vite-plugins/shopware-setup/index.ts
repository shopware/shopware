/**
 * @sw-package framework
 */

import type { Plugin } from 'vite';
import fs from 'node:fs/promises';
import path from 'node:path';
import type { transformShopwareSetupSfc as transformShopwareSetupSfcRuntime } from '../../vue-setup-transform';
import { createVirtualSetupSourcemapContext } from './virtual-sfc-sourcemap';

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
    // Component name -> file that first declared it as a base component. The name is derived from the
    // filename and is the public override target, so two base components must not resolve to the same
    // name. Overrides intentionally reuse the base name, so only base components are tracked. This is
    // the per-compilation cross-file uniqueness check the transform's componentName seam enables.
    const baseComponentFiles = new Map<string, string>();
    const virtualSourcemap = createVirtualSetupSourcemapContext(options.administrationRoot);

    async function transformFile(
        fileName: string,
    ): Promise<ShopwareSetupTransformResult | null> {
        const transformShopwareSetupSfc = await loadShopwareSetupTransform(options.administrationRoot);
        const code = await fs.readFile(fileName, 'utf8');

        return transformShopwareSetupSfc(code, fileName);
    }

    async function transformSource(
        code: string,
        fileName: string,
    ): Promise<ShopwareSetupTransformResult | null> {
        const transformShopwareSetupSfc = await loadShopwareSetupTransform(options.administrationRoot);

        return transformShopwareSetupSfc(code, fileName);
    }

    function assertUniqueBaseComponent(result: ShopwareSetupTransformResult, fileName: string): void {
        if (result.mode !== 'base') {
            return;
        }

        const existing = baseComponentFiles.get(result.componentName);

        if (existing && existing !== fileName) {
            throw new Error(
                `Duplicate native setup base component name "${result.componentName}": "${existing}" and ` +
                    `"${fileName}" resolve to the same extendable component. Component names are derived from ` +
                    'filenames and must be unique.',
            );
        }

        baseComponentFiles.set(result.componentName, fileName);
    }

    return {
        name: 'shopware-vite-plugin-shopware-setup',
        enforce: 'pre',

        async resolveId(source, importer) {
            if (source.includes('?') || !source.endsWith('.vue')) {
                return null;
            }

            const resolved = await this.resolve(source, importer, { skipSelf: true });

            if (!resolved) {
                return null;
            }

            const fileName = withoutQuery(resolved.id);

            if (!fileName.endsWith('.vue') || virtualSourcemap.isVirtualFileName(fileName)) {
                return null;
            }

            const result = await transformFile(fileName);

            if (!result) {
                return null;
            }

            const virtualFileName = virtualSourcemap.toVirtualFileName(fileName);
            virtualSourcemap.rememberOriginalFile(virtualFileName, fileName);

            return virtualFileName;
        },

        async load(id) {
            if (id.includes('?')) {
                return null;
            }

            const fileName = withoutQuery(id);

            if (!virtualSourcemap.isVirtualFileName(fileName)) {
                return null;
            }

            const originalFileName = virtualSourcemap.getOriginalFileName(fileName);
            const result = await transformFile(originalFileName);

            if (!result) {
                return null;
            }

            assertUniqueBaseComponent(result, originalFileName);

            virtualSourcemap.rememberSetupMap(fileName, result.map);

            return {
                code: result.code,
                map: result.map,
            };
        },

        async transform(code, id) {
            const fileName = withoutQuery(id);

            if (!fileName.endsWith('.vue') || virtualSourcemap.isVirtualFileName(fileName)) {
                return null;
            }

            const result = await transformSource(code, fileName);

            if (!result) {
                return null;
            }

            assertUniqueBaseComponent(result, fileName);

            return {
                code: result.code,
                map: result.map,
            };
        },

        generateBundle(outputOptions, bundle) {
            virtualSourcemap.remapBundle(outputOptions, bundle);
        },
    };
}
