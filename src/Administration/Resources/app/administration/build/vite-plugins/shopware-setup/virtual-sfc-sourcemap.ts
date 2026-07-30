/**
 * @sw-package framework
 */

import { createRequire } from 'node:module';
import path from 'node:path';
import type { transformShopwareSetupSfc as transformShopwareSetupSfcRuntime } from '../../vue-setup-transform';

type ShopwareSetupTransformMap = NonNullable<ReturnType<typeof transformShopwareSetupSfcRuntime>>['map'];

type Remapping = (
    map: { sources: string[] },
    loader: (source: string) => ShopwareSetupTransformMap | null,
    excludeContent?: boolean,
) => ShopwareSetupTransformMap;

type OutputOptionsLike = {
    dir?: string;
    file?: string;
};

type OutputChunkLike = {
    type: 'chunk';
    fileName: string;
    map: ShopwareSetupTransformMap | null;
};
type OutputChunkWithMap = {
    type: 'chunk';
    fileName: string;
    map: ShopwareSetupTransformMap;
};

type OutputAssetLike = {
    type: 'asset';
    fileName: string;
    source: string | Uint8Array;
};

type OutputItemLike =
    | {
          type: string;
      }
    | OutputChunkLike
    | OutputAssetLike;

type OutputBundleLike = Record<string, OutputItemLike>;

const virtualSetupSuffix = '.shopware-setup.vue';

function isOutputChunkWithMap(item: OutputItemLike): item is OutputChunkWithMap {
    return item.type === 'chunk' && 'map' in item && item.map !== null;
}

function isOutputAsset(item: OutputItemLike | undefined): item is OutputAssetLike {
    return item?.type === 'asset' && 'source' in item;
}

function resolveMapSourceFileName(outputDirectory: string, chunkFileName: string, source: string): string {
    if (path.isAbsolute(source)) {
        return source;
    }

    return path.resolve(outputDirectory, path.dirname(chunkFileName), source);
}

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

    function toVirtualFileName(fileName: string): string {
        return `${fileName}${virtualSetupSuffix}`;
    }

    function toOriginalFileName(virtualFileName: string): string {
        return virtualFileName.slice(0, -virtualSetupSuffix.length);
    }

    function isVirtualFileName(fileName: string): boolean {
        return fileName.endsWith(virtualSetupSuffix);
    }

    function rememberOriginalFile(virtualFileName: string, originalFileName: string): void {
        originalFileByVirtualFileName.set(virtualFileName, originalFileName);
    }

    function getOriginalFileName(virtualFileName: string): string {
        return originalFileByVirtualFileName.get(virtualFileName) ?? toOriginalFileName(virtualFileName);
    }

    function rememberSetupMap(virtualFileName: string, map: ShopwareSetupTransformMap): void {
        setupMapByVirtualFileName.set(virtualFileName, map);
    }

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
