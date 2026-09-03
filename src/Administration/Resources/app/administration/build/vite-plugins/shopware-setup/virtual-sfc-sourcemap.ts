/**
 * @sw-package framework
 */

import { createRequire } from 'node:module';
import path from 'node:path';
import type { transformShopwareSetupSfc as transformShopwareSetupSfcRuntime } from '../../vue-setup-transform';

/**
 * The sourcemap shape the shopware-setup transform emits for its `<script setup>` rewrite.
 *
 * Reused as the single map type across every layer here (the setup map, the chunk map, and the
 * remapped result) so the two map layers compose without casting.
 */
type ShopwareSetupTransformMap = NonNullable<ReturnType<typeof transformShopwareSetupSfcRuntime>>['map'];

/**
 * The `@jridgewell/remapping` entry point, typed against our own map shape.
 *
 * remapping walks the `sources` of the outer `map` and, for each one, calls `loader` to fetch the
 * inner map that source was itself generated from; returning `null` means "leave this source as a
 * leaf". The two maps are then collapsed into one that points at the innermost originals.
 *
 * Typed locally because the package is pulled in via `createRequire` at runtime rather than imported,
 * so it carries no compile-time types of its own.
 */
type Remapping = (
    map: { sources: string[] },
    loader: (source: string) => ShopwareSetupTransformMap | null,
    excludeContent?: boolean,
) => ShopwareSetupTransformMap;

/**
 * The subset of Rollup's `OutputOptions` `generateBundle` hands us that we actually read.
 *
 * These `*Like` types are deliberately narrow structural stand-ins for Rollup's own types: this file
 * is consumed by both the Vite build and standalone extension builds, so depending only on the fields
 * we touch keeps it decoupled from a specific Rollup version's type surface.
 */
type OutputOptionsLike = {
    dir?: string;
    file?: string;
};

/** A bundle entry that is an emitted JS chunk, whose sourcemap may or may not have been generated. */
type OutputChunkLike = {
    type: 'chunk';
    fileName: string;
    map: ShopwareSetupTransformMap | null;
};
/** A chunk narrowed to the case where a map is present - the only shape {@link remapBundle} rewrites. */
type OutputChunkWithMap = {
    type: 'chunk';
    fileName: string;
    map: ShopwareSetupTransformMap;
};

/** A bundle entry that is an emitted asset - here specifically the standalone `.js.map` file. */
type OutputAssetLike = {
    type: 'asset';
    fileName: string;
    source: string | Uint8Array;
};

/** Any bundle entry: an unknown `type` falls back to the bare-`type` arm so the guards can narrow it. */
type OutputItemLike =
    | {
          type: string;
      }
    | OutputChunkLike
    | OutputAssetLike;

/** Rollup's output bundle: a map of emitted file name to its chunk or asset entry. */
type OutputBundleLike = Record<string, OutputItemLike>;

const virtualSetupSuffix = '.shopware-setup.vue';

/** Type guard selecting chunks that carry a generated map - the entries worth remapping. */
function isOutputChunkWithMap(item: OutputItemLike): item is OutputChunkWithMap {
    return item.type === 'chunk' && 'map' in item && item.map !== null;
}

/** Type guard for the emitted `.js.map` asset, so its serialized `source` can be rewritten in place. */
function isOutputAsset(item: OutputItemLike | undefined): item is OutputAssetLike {
    return item?.type === 'asset' && 'source' in item;
}

/**
 * Resolves a sourcemap `sources` entry to an absolute path.
 *
 * A map's `sources` are stored relative to the map file itself, which sits next to the chunk. Absolute
 * entries are already resolved; relative ones are anchored at the chunk's directory inside the output
 * dir. Used to turn a virtual `sources` entry back into the key under which its setup map was stored.
 */
function resolveMapSourceFileName(outputDirectory: string, chunkFileName: string, source: string): string {
    if (path.isAbsolute(source)) {
        return source;
    }

    return path.resolve(outputDirectory, path.dirname(chunkFileName), source);
}

/**
 * Resolves the directory the bundle is emitted into.
 *
 * Rollup reports the target either as `dir` (multi-file output) or `file` (single-file output); when
 * neither is set the write is relative to the current working directory. All chunk/map paths here are
 * anchored against this directory.
 */
function getOutputDirectory(outputOptions: OutputOptionsLike): string {
    if (outputOptions.dir) {
        return outputOptions.dir;
    }

    if (outputOptions.file) {
        return path.dirname(outputOptions.file);
    }

    return process.cwd();
}

/**
 * @private
 *
 * Keeps the transformed intermediate SFC under a separate virtual filename.
 *
 * The virtual id is `<realpath>.shopware-setup.vue` rather than a NUL-prefixed (`\0`) Rollup virtual
 * id on purpose: @vitejs/plugin-vue must still pick the module up and compile it as a real SFC, and it
 * refuses to transform `\0`-namespaced ids. The name only has to stay `.vue`-terminated and unique;
 * generateBundle collapses it back out of the shipped sourcemap.
 *
 * Without this, Rollup sees the original `.vue` file and the transformed SFC body
 * as conflicting `sourcesContent` for the same source path when plugin-vue composes maps.
 */
function createVirtualSetupSourcemapContext(administrationRoot: string) {
    const requireFromAdministration = createRequire(path.join(administrationRoot, 'package.json'));
    const remapping = requireFromAdministration('@jridgewell/remapping') as Remapping;
    const originalFileByVirtualFileName = new Map<string, string>();
    const setupMapByVirtualFileName = new Map<string, ShopwareSetupTransformMap>();

    /** Derives the virtual id plugin-vue compiles under from a real `.vue` path (`resolveId`). */
    function toVirtualFileName(fileName: string): string {
        return `${fileName}${virtualSetupSuffix}`;
    }

    /**
     * Strips the virtual suffix back to the real `.vue` path.
     *
     * Pure string fallback for {@link getOriginalFileName}; because the suffix is a fixed `.vue`-ending
     * tail, this holds even for a virtual name never registered via {@link rememberOriginalFile}.
     */
    function toOriginalFileName(virtualFileName: string): string {
        return virtualFileName.slice(0, -virtualSetupSuffix.length);
    }

    /** Whether an id is one of our virtual SFCs - used to skip it in the plugin's own resolve/transform. */
    function isVirtualFileName(fileName: string): boolean {
        return fileName.endsWith(virtualSetupSuffix);
    }

    /** Records which real `.vue` a virtual id stands for, so `load` can read the source it derives from. */
    function rememberOriginalFile(virtualFileName: string, originalFileName: string): void {
        originalFileByVirtualFileName.set(virtualFileName, originalFileName);
    }

    /** The real `.vue` behind a virtual id, falling back to suffix-stripping if it was never registered. */
    function getOriginalFileName(virtualFileName: string): string {
        return originalFileByVirtualFileName.get(virtualFileName) ?? toOriginalFileName(virtualFileName);
    }

    /**
     * Stashes the setup rewrite's map for a virtual id, keyed for later lookup in {@link remapBundle}.
     *
     * Called from `load` once per compiled SFC; {@link remapBundle}'s remapping loader reads it back to
     * fold the setup-rewrite layer into the final chunk map.
     */
    function rememberSetupMap(virtualFileName: string, map: ShopwareSetupTransformMap): void {
        setupMapByVirtualFileName.set(virtualFileName, map);
    }

    /**
     * Rewrites the finished bundle's sourcemaps so they point at the author's real `.vue` files.
     *
     * Run from the plugin's `generateBundle`. For every chunk whose map still references a virtual
     * source, it composes the setup-rewrite map back in (collapsing the virtual filename out via
     * {@link Remapping}) and rewrites absolute `sources` to be relative to the emitted `.map`. Both the
     * chunk's own `map` and any already-emitted `.js.map` asset are updated, since Rollup may serialize
     * either one.
     */
    function remapBundle(outputOptions: OutputOptionsLike, bundle: OutputBundleLike): void {
        const outputDirectory = getOutputDirectory(outputOptions);

        Object.values(bundle).forEach((item) => {
            if (!isOutputChunkWithMap(item)) {
                return;
            }

            const currentMap = item.map;

            if (!currentMap.sources.some((source) => isVirtualFileName(source))) {
                return;
            }

            const remapped = remapping(
                currentMap,
                (source) => {
                    if (!isVirtualFileName(source)) {
                        return null;
                    }

                    const virtualFileName = resolveMapSourceFileName(outputDirectory, item.fileName, source);

                    return setupMapByVirtualFileName.get(virtualFileName) ?? null;
                },
                false,
            );

            // Our transform records absolute source paths, and remapping carries them through - so without
            // this the shipped map advertises the build machine's filesystem layout. Rollup writes module
            // sources relative to the map, so match that.
            const mapDirectory = path.resolve(outputDirectory, path.dirname(item.fileName));

            remapped.sources = remapped.sources.map((source) =>
                path.isAbsolute(source) ? path.relative(mapDirectory, source).split(path.sep).join('/') : source,
            );

            item.map = remapped;

            // The `.js.map` file is written from the emitted asset when one already exists at this point,
            // in which case mutating `chunk.map` alone changes nothing on disk - which is how the virtual
            // filenames survived into shipped plugin sourcemaps. Update both so the remap lands whichever
            // of the two Rollup ends up serializing.
            const mapAsset = bundle[`${item.fileName}.map`];

            if (isOutputAsset(mapAsset)) {
                mapAsset.source = JSON.stringify(remapped);
            }
        });
    }

    return {
        getOriginalFileName,
        isVirtualFileName,
        rememberOriginalFile,
        rememberSetupMap,
        remapBundle,
        toVirtualFileName,
    };
}

/**
 * @private
 */
export { createVirtualSetupSourcemapContext };
