import { Plugin } from 'vite';
import path from 'path';
import deprecatedList from './deprecated-list';

// @deprecated tag:v6.8.0
// After v6.8.0 all these images in `./deprecated-list` should be removed. Maybe consider leaving this plugin in here for a bit and adjust the warning.

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
                    `DEPRECATION: In file "${importer}", the image import "${source}" uses a deprecated format. PNG and JPG assets have been migrated to WebP. Please update your assets to WebP to ensure continued support.`,
                );
            }
            return null;
        },
    };
}
