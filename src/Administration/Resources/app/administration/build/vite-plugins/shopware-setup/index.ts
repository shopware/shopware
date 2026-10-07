/**
 * @sw-package framework
 */

import type { ErrorPayload, Plugin } from 'vite';
import fs from 'node:fs/promises';
import path from 'node:path';
import { createRequire } from 'node:module';
import type {
    ShopwareSetupTransformError,
    transformShopwareSetupSfc as transformShopwareSetupSfcRuntime,
} from '../../vue-setup-transform';
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
 * A transform failure in the two shapes the dev server reports it: a terminal line and an overlay payload.
 */
type TransformFailure = {
    log: string;
    overlay: ErrorPayload['err'];
};

/**
 * Reads the message, location and code frame a transform diagnostic carries, for reporting it to Vite.
 *
 * Use it instead of `instanceof ShopwareSetupTransformError`: the lazily required transform module may
 * throw from another realm, where `instanceof` fails. `loc` and `frame` are `null` for any other error.
 *
 * @example
 * const { message, loc, frame } = readTransformDiagnostic(error); // loc: { file, line, column } | null
 */
function readTransformDiagnostic(error: unknown): {
    message: string;
    loc: ShopwareSetupTransformError['loc'];
    frame: string | null;
} {
    const { message, loc, frame } = (error ?? {}) as { message?: unknown } & Partial<
        Pick<ShopwareSetupTransformError, 'loc' | 'frame'>
    >;

    return {
        message: typeof message === 'string' ? message : String(error),
        loc: loc ?? null,
        frame: frame ?? null,
    };
}

/**
 * Renders a transform diagnostic as `file:line:column`, its message and code frame, for the terminal and
 * the error overlay alike. Line and column are Vite's (1-based, 0-based), so a save reports the position
 * Vite prints when a page load hits the same error.
 */
function formatTransformError(error: unknown, fileName: string): TransformFailure {
    const { message, loc, frame } = readTransformDiagnostic(error);
    const position = loc ? `:${loc.line}:${loc.column}` : '';

    return {
        log: `[shopware-setup] ${fileName}${position}\n${message}${frame ? `\n${frame}` : ''}`,
        overlay: {
            message,
            stack: '',
            id: fileName,
            loc: loc ?? undefined,
            frame: frame ?? undefined,
        },
    };
}

const PLUGIN_NAME = 'shopware-vite-plugin-shopware-setup';

/**
 * Marks a located transform error as already attributed, which stops Vite from re-tracing its `loc`
 * through the importer's sourcemap.
 *
 * Vite's plugin context skips its own `id`/`loc`/`frame` attribution for an error that carries
 * `pluginCode`; `plugin` keeps the "Plugin:" line in the terminal. Dev server only: Rollup, which runs
 * `vite build`, reads `pluginCode` as the plugin's error code instead.
 */
function asReportedTransformError(error: unknown, source: string): unknown {
    if (readTransformDiagnostic(error).loc) {
        Object.assign(error as object, { pluginCode: source, plugin: PLUGIN_NAME });
    }

    return error;
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
    // resolveId has to run the transform to detect a setup SFC at all, and hotUpdate runs it for its
    // diagnostics, so the result is stashed here for the matching load(). Keyed by source content: Vite's
    // import-analysis re-resolves watched files after every transform and re-stashes, so an entry can
    // predate the user's next edit - reusing it unverified would serve every edit one save late.
    const resolvedTransforms = new Map<string, { source: string; result: ShopwareSetupTransformResult }>();
    // Set from the resolved Vite config; the remap is pointless when the build emits no maps.
    let sourcemapsEnabled = true;
    // Set from the resolved Vite config; see asReportedTransformError for why only the dev server marks errors.
    let isDevServer = false;
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
     * plugin reads it itself and returns the source alongside the result.
     */
    async function transformFile(
        fileName: string,
    ): Promise<{ source: string; result: ShopwareSetupTransformResult | null }> {
        const source = await fs.readFile(fileName, 'utf8');

        return { source, result: await transformSource(source, fileName) };
    }

    /**
     * Transforms SFC code and, in the dev server, marks a located failure as attributed to `fileName`, see
     * {@link asReportedTransformError}.
     */
    async function transformSource(code: string, fileName: string): Promise<ShopwareSetupTransformResult | null> {
        const transformShopwareSetupSfc = await loadShopwareSetupTransform();

        try {
            return transformShopwareSetupSfc(code, fileName);
        } catch (error) {
            throw isDevServer ? asReportedTransformError(error, code) : error;
        }
    }

    /**
     * Transforms a saved file and returns its failure for the terminal and the overlay, or `null` when it
     * compiles.
     *
     * A successful result is stashed for the `load` the hot update triggers, so a save is transformed once.
     */
    async function transformChangedFile(fileName: string, source: string): Promise<TransformFailure | null> {
        try {
            const result = await transformSource(source, fileName);

            if (result) {
                resolvedTransforms.set(fileName, { source, result });
            }

            return null;
        } catch (error) {
            return formatTransformError(error, fileName);
        }
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
        for (const [componentName, claimedBy] of baseComponentFiles) {
            if (claimedBy === fileName) {
                baseComponentFiles.delete(componentName);
                break;
            }
        }
    }

    return {
        name: PLUGIN_NAME,
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
         *
         * It is also where a transform failure gets reported. Otherwise the transform runs only once a
         * client requests the module (`resolveId`/`load`), so saving a file that does not compile printed
         * nothing at all (issue #19562). A failure is reported here instead of pushing the update, which
         * would make the client refetch the module, fail again in `load` and get it reported twice.
         */
        async hotUpdate({ type, file, modules, read }) {
            if (!file.endsWith('.vue') || virtualSourcemap.isVirtualFileName(file) || isDependencyFile(file)) {
                return undefined;
            }

            const virtualModule = this.environment.moduleGraph.getModuleById(virtualSourcemap.toVirtualFileName(file));

            // Vite runs this hook once per environment (client and ssr); report only once.
            if (type !== 'delete' && this.environment.name === 'client') {
                const failure = await transformChangedFile(file, await read());

                if (failure) {
                    this.environment.logger.error(failure.log);
                    this.environment.hot.send({ type: 'error', err: failure.overlay });

                    // Without a pushed update nothing drops the cached transform, so a reload would serve stale code.
                    if (virtualModule) {
                        this.environment.moduleGraph.invalidateModule(virtualModule);
                    }

                    return [];
                }
            }

            if (!virtualModule) {
                return undefined;
            }

            return [...modules, virtualModule];
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
            isDevServer = config.command === 'serve';
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
