/**
 * @sw-package framework
 */

import type { Plugin } from 'vite';
import path from 'node:path';
import { createRequire } from 'node:module';
import type { transformShopwareSetupSfc as transformShopwareSetupSfcRuntime } from '../../vue-setup-transform';

type ShopwareSetupTransformModule = {
    transformShopwareSetupSfc: typeof transformShopwareSetupSfcRuntime;
};
type ShopwareSetupTransformImport =
    | ShopwareSetupTransformModule
    | {
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
 * Whether a file belongs to an installed dependency.
 *
 * A missing `<script setup>` is a build error, and nobody can add `swDefinePublic()` to a package they do
 * not own - so the rule applies to authored source only. Backslashes are normalized for Windows ids.
 */
function isDependencyFile(fileName: string): boolean {
    return fileName.replace(/\\/g, '/').includes('/node_modules/');
}

/**
 * Keep the CommonJS transform out of Vite's config bundle.
 *
 * The shared transform is intentionally still CommonJS because the Jest transformer and
 * the ESLint rule consume it synchronously. Vite bundles `vite.config.mts` with esbuild
 * by default; if the transform is statically imported there, its `require()` calls are
 * inlined into an ESM config bundle and fail at runtime.
 */
// eslint-disable-next-line @typescript-eslint/require-await
async function importShopwareSetupTransform(administrationRoot: string): Promise<typeof transformShopwareSetupSfcRuntime> {
    // `require`, not `import()`: extension builds resolve this plugin as CommonJS, which cannot load the
    // file: URL that `import()` needs on Windows. A plain path works in both.
    const requireFromAdministration = createRequire(path.join(administrationRoot, 'package.json'));
    const transformImport = requireFromAdministration(
        path.join(administrationRoot, 'build/vue-setup-transform/index.js'),
    ) as ShopwareSetupTransformImport;
    const transformModule = 'default' in transformImport ? transformImport.default : transformImport;

    return transformModule.transformShopwareSetupSfc;
}

/**
 * @private
 *
 * Runs before @vitejs/plugin-vue so Vue only ever sees standard SFC syntax.
 * Parser-sensitive behavior stays in build/vue-setup-transform for reuse by Jest,
 * ESLint, and editor tooling.
 */
export default function shopwareSetupPlugin(options: Options): Plugin {
    // Component name -> the base file claiming it. Only bases: overrides reuse the base name by design.
    // One instance per extension, so this catches collisions within a build, not across extensions.
    const baseComponentFiles = new Map<string, string>();
    // caveat: also rejections are cached
    let transformPromise: Promise<typeof transformShopwareSetupSfcRuntime> | null = null;

    function loadShopwareSetupTransform(): Promise<typeof transformShopwareSetupSfcRuntime> {
        transformPromise ??= importShopwareSetupTransform(options.administrationRoot);

        return transformPromise;
    }

    async function transformCode(code: string, fileName: string): Promise<ShopwareSetupTransformResult | null> {
        const transformShopwareSetupSfc = await loadShopwareSetupTransform();

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
                    'filenames and must be unique within a build.',
            );
        }

        baseComponentFiles.set(result.componentName, fileName);
    }

    /**
     * Drops a file's claim on its component name.
     *
     * The registry outlives a single transform in a dev session, so a deleted or moved file would keep
     * its name reserved: transforming the file at its new path would then collide with the path that no
     * longer exists and report a duplicate until the dev server restarts.
     */
    function forgetBaseComponentFile(fileName: string): void {
        baseComponentFiles.forEach((claimedBy, componentName) => {
            if (claimedBy === fileName) {
                baseComponentFiles.delete(componentName);
            }
        });
    }

    return {
        name: 'shopware-vite-plugin-shopware-setup',
        enforce: 'pre',

        async transform(code, id) {
            const fileName = withoutQuery(id);

            if (!fileName.endsWith('.vue') || isDependencyFile(fileName)) {
                return null;
            }

            const result = await transformCode(code, fileName);

            if (!result) {
                return null;
            }

            assertUniqueBaseComponent(result, fileName);

            return {
                code: result.code,
                map: result.map,
            };
        },

        watchChange(id, change) {
            // A rename arrives as delete + create, so releasing on delete is what frees the name.
            // Not a `buildStart` reset: that runs per environment and would drop live claims.
            if (change.event === 'delete') {
                forgetBaseComponentFile(withoutQuery(id));
            }
        },
    };
}
