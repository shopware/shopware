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
 * Whether a resolved file belongs to an installed dependency rather than to authored source.
 *
 * Every authored `.vue` file must be a native setup component, and a missing `<script setup>` is a build
 * error. A dependency's `.vue` files are outside that contract: an extension author cannot add
 * `swDefinePublic()` to a package they do not own, so applying the rule there would fail their build with
 * no way out. Backslashes are normalized because Windows ids arrive with them.
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
    // `require`, not a dynamic `import()`. This plugin is bundled two ways - esbuild's ESM config bundle
    // for the Administration build, and a CommonJS resolver for extension builds (build/plugins.vite.ts)
    // - and no `import()` argument satisfies both: it needs a file: URL on Windows, where Node's ESM
    // resolver reads the `C:` drive letter as a URL scheme, and the CommonJS resolver cannot load a URL
    // ("Cannot find module 'file:///...'"). A plain path works everywhere.
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
    // Component name -> file that first declared it as a base component. The name is derived from the
    // filename and is the public override target, so two base components must not resolve to the same
    // name. Overrides intentionally reuse the base name, so only base components are tracked. This is
    // the per-compilation cross-file uniqueness check the transform's componentName seam enables.
    //
    // Scope is one plugin instance, and `getBaseConfig()` in build/plugins.vite.ts builds one per
    // extension - so this catches two base components colliding inside a build, not two extensions each
    // shipping the same name. Cross-extension collisions are invisible at build time (an extension
    // cannot see the others) and surface in the runtime component registry instead.
    const baseComponentFiles = new Map<string, string>();
    // Loaded on first use, once per plugin instance. Lazily, because a bad `administrationRoot` has to
    // surface when a `.vue` file is transformed: an eagerly created rejected promise would go unhandled
    // in a build that never reaches one. `require`'s own cache still reads the module from disk once per
    // process.
    //
    // A rejection is cached too. The only way this fails is a wrong `administrationRoot` or a missing
    // committed bridge file, neither of which is transient, so reporting the identical error once is
    // better than a fresh one per `.vue` file.
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
            // A rename reaches us as a delete of the old path plus a create of the new one, so releasing
            // the name on delete is what lets the new path claim it instead of colliding with a file that
            // no longer exists. An update keeps its claim: same path, same name.
            //
            // Do not swap this for a `buildStart` reset: `buildStart` runs per environment in Vite 6
            // (client and ssr share this plugin instance), so a reset would drop the claims the previous
            // environment made, and in `build --watch` Rollup re-uses cached modules, so unchanged files
            // would never re-register and real duplicates would stop being reported.
            if (change.event === 'delete') {
                forgetBaseComponentFile(withoutQuery(id));
            }
        },
    };
}
