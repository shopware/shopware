/**
 * @sw-package framework
 */

import type { Plugin } from 'vite';
import fs from 'node:fs/promises';
import path from 'node:path';
import { createRequire } from 'node:module';
import type { transformShopwareSetupSfc as transformShopwareSetupSfcRuntime } from '../../vue-setup-transform';
import { createVirtualSetupSourcemapContext } from './virtual-sfc-sourcemap';

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
    const virtualSourcemap = createVirtualSetupSourcemapContext(options.administrationRoot);
    // resolveId has to run the transform to detect a setup SFC at all, so its result is stashed here for
    // the matching load(). Keyed by source content: Vite's import-analysis re-resolves watched files after
    // every transform and re-stashes, so an entry can predate the user's next edit - reusing it unverified
    // would serve every edit one save late.
    const resolvedTransforms = new Map<string, { source: string; result: ShopwareSetupTransformResult }>();
    // Set from the resolved Vite config; the remap is pointless when the build emits no maps.
    let sourcemapsEnabled = true;
    // caveat: also rejections are cached
    let transformPromise: Promise<typeof transformShopwareSetupSfcRuntime> | null = null;

    function loadShopwareSetupTransform(): Promise<typeof transformShopwareSetupSfcRuntime> {
        transformPromise ??= importShopwareSetupTransform(options.administrationRoot);

        return transformPromise;
    }

    /**
     * Transforms a `.vue` file read from disk.
     *
     * The virtual-filename path (`resolveId`/`load`) hands us only an id, not the file's source, so the
     * plugin reads it itself. The in-hand-code variant is {@link transformSource}, used by `transform`
     * where Rollup already supplies the module code.
     */
    async function transformFile(
        fileName: string,
    ): Promise<{ source: string; result: ShopwareSetupTransformResult | null }> {
        const transformShopwareSetupSfc = await loadShopwareSetupTransform();
        const source = await fs.readFile(fileName, 'utf8');

        return { source, result: transformShopwareSetupSfc(source, fileName) };
    }

    /** Transforms already-loaded module code; see {@link transformFile} for the read-from-disk variant. */
    async function transformSource(code: string, fileName: string): Promise<ShopwareSetupTransformResult | null> {
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
        for (const [
            componentName,
            claimedBy,
        ] of baseComponentFiles) {
            if (claimedBy === fileName) {
                baseComponentFiles.delete(componentName);
                break;
            }
        }
    }

    return {
        name: 'shopware-vite-plugin-shopware-setup',
        enforce: 'pre',

        /**
         * Redirects a setup `.vue` import to a virtual `<realpath>.shopware-setup.vue` id so the rewritten
         * SFC is compiled under a source path distinct from the author's original file.
         *
         * `resolveId` is the earliest hook that can substitute a module id before Rollup loads and hands it
         * to @vitejs/plugin-vue. Compiling the rewritten body under its own name is what lets the two map
         * layers (our rewrite, then plugin-vue's compile) compose without the original and transformed
         * bodies claiming the same `sourcesContent`; `generateBundle` collapses the virtual name back out.
         * The transform result is stashed in {@link resolvedTransforms} so the paired `load` need not rerun it.
         */
        async resolveId(source, importer) {
            if (source.includes('?') || !source.endsWith('.vue')) {
                return null;
            }

            const resolved = await this.resolve(source, importer, { skipSelf: true });

            if (!resolved) {
                return null;
            }

            const fileName = withoutQuery(resolved.id);

            if (!fileName.endsWith('.vue') || virtualSourcemap.isVirtualFileName(fileName) || isDependencyFile(fileName)) {
                return null;
            }

            const { source: fileSource, result } = await transformFile(fileName);

            if (!result) {
                return null;
            }

            const virtualFileName = virtualSourcemap.toVirtualFileName(fileName);
            virtualSourcemap.rememberOriginalFile(virtualFileName, fileName);
            resolvedTransforms.set(fileName, { source: fileSource, result });

            return virtualFileName;
        },

        /**
         * Serves the rewritten SFC (code + map) for a virtual id minted by `resolveId`.
         *
         * Only the virtual id reaches this branch; real `.vue` files fall through to Vite's own loading.
         * Reuses the cached `resolveId` result when present, otherwise re-runs the transform. The emitted
         * map is registered via `rememberSetupMap` so `generateBundle` can later remap the shipped chunk.
         */
        async load(id) {
            if (id.includes('?')) {
                return null;
            }

            const fileName = withoutQuery(id);

            if (!virtualSourcemap.isVirtualFileName(fileName)) {
                return null;
            }

            const originalFileName = virtualSourcemap.getOriginalFileName(fileName);

            // The virtual module's content is derived from the real `.vue` file, which Rollup never
            // sees as a module of its own. Register it as a watched dependency so an edit invalidates
            // this virtual module in dev/watch mode.
            this.addWatchFile(originalFileName);

            // Reuse the stash only for unchanged source - a stale entry would serve the previous version.
            const source = await fs.readFile(originalFileName, 'utf8');
            const cached = resolvedTransforms.get(originalFileName);
            resolvedTransforms.delete(originalFileName);
            const result = cached?.source === source ? cached.result : await transformSource(source, originalFileName);

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

            if (!fileName.endsWith('.vue') || virtualSourcemap.isVirtualFileName(fileName) || isDependencyFile(fileName)) {
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

        /**
         * Routes a change of a real `.vue` file to its virtual `.shopware-setup.vue` module.
         *
         * The module graph knows the SFC only under its virtual id, and Vite derives hot updates purely
         * from `getModulesByFile(<changed file>)` - `addWatchFile` alone does not link file and module,
         * so without this hook an edit invalidated nothing until the dev server was restarted.
         * Returning the virtual module makes Vite invalidate it and push the update to the client.
         */
        hotUpdate({ file, modules }) {
            if (!file.endsWith('.vue') || virtualSourcemap.isVirtualFileName(file) || isDependencyFile(file)) {
                return undefined;
            }

            const virtualModule = this.environment.moduleGraph.getModuleById(virtualSourcemap.toVirtualFileName(file));

            if (!virtualModule) {
                return undefined;
            }

            return [
                ...modules,
                virtualModule,
            ];
        },

        watchChange(id, change) {
            // A rename arrives as delete + create, so releasing on delete is what frees the name.
            // Not a `buildStart` reset: that runs per environment and would drop live claims.
            if (change.event === 'delete') {
                forgetBaseComponentFile(withoutQuery(id));
            }
        },

        configResolved(config) {
            // Sourcemaps follow the build's own setting, which vite.config.mts and plugins.vite.ts derive
            // from GENERATE_SOURCEMAPS / SHOPWARE_ADMIN_SKIP_SOURCEMAP_GENERATION. Reading it here keeps
            // this plugin on par with the rest of the build instead of re-interpreting those variables.
            sourcemapsEnabled = Boolean(config.build?.sourcemap);
        },

        /**
         * Collapses the virtual `.shopware-setup.vue` source paths back to the real files in the shipped maps.
         *
         * This runs in `generateBundle` because it is the last hook where the final chunk maps exist and are
         * still mutable before they are written to disk - the only point at which the virtual filenames (a
         * build-internal detail) can be rewritten out of what actually ships.
         */
        generateBundle(outputOptions, bundle) {
            if (!sourcemapsEnabled) {
                return;
            }

            virtualSourcemap.remapBundle(outputOptions, bundle);
        },
    };
}
