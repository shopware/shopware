import type { Plugin } from 'vite';
import path from 'path';
import assetMigrations from '../../../src/app/deprecation-registry/definitions/assets';
import deprecatedList from './deprecated-list';

/**
 * @sw-package framework
 * @private
 *
 * This plugin is used to show warnings if deprecated images are being used.
 *
 * @param adminDir absolute path to administration which is probably `/src/Administration/Resources/app/administration`
 * @param deprecatedImages list of file paths of images that when imported should show a deprecation warning. Working directory of those paths adminPath and they begin with `/`
 */
export default function viteImageDeprecationPlugin(adminDir: string, deprecatedImages: string[] = deprecatedList): Plugin {
    const deprecatedFilesSet = new Set(deprecatedImages);
    const extensionsThatArePartOfDeprecation = new Set(deprecatedImages.map((file) => path.extname(file)));
    const findAssetMigration = (file: string) => {
        return assetMigrations.find((migration) => migration.files?.includes(file));
    };

    return {
        name: 'shopware-vite-plugin-image-deprecation',
        enforce: 'pre',
        resolveId(source, importer) {
            // early check
            if (!extensionsThatArePartOfDeprecation.has(path.extname(source))) {
                return null;
            }

            // resolve relative path since adminDir
            const wholePath = path.join(path.dirname(importer ?? adminDir), source);
            if (!wholePath.startsWith(adminDir)) {
                return null;
            }
            const relativePath = wholePath.slice(adminDir.length);

            // Match imports that are part of the alias list
            if (deprecatedFilesSet.has(relativePath)) {
                console.warn(
                    `DEPRECATION: In file "${importer}", the image import "${source}" is deprecated. ` +
                        (findAssetMigration(relativePath)?.description ??
                            'Replace it before it is removed in the next major version.'),
                );
            }
            return null;
        },
    };
}
