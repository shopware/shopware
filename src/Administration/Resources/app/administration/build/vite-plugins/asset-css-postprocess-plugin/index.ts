import { Plugin } from 'vite';
import path from 'path';

/**
 * Vite plugin that rewrites absolute asset URLs in generated CSS to be relative.
 */
export default function stripAssetsFolderInCss(folderToStrip: string): Plugin {
    return {
        name: 'asset-css-postprocess-plugin',
        generateBundle(_, bundle) {
            for (const [
                fileName,
                file,
            ] of Object.entries(bundle)) {
                if (fileName.endsWith('.css') && file.type === 'asset' && typeof file.source === 'string') {
                    const cssDir = path.dirname(fileName);

                    // Replace absolute prefixed URLs with relative ones
                    // Example: url(/bundles/.../assets/Inter-XXX.woff2?v=3.19) -> url(./Inter-XXX.woff2?v=3.19)
                    file.source = file.source.replace(
                        new RegExp(`${folderToStrip}([^)"'\\s]+)`, 'g'),
                        (match, assetPath) => {
                            // Extract asset path from URL (remove query params and hash)
                            const assetFileName = assetPath.split('?')[0].split('#')[0];

                            // Check if the asset exists in the same directory as the CSS file
                            // The assetFileName is relative to the assets folder, so we need to check
                            // if it exists relative to the CSS file's directory
                            const expectedAssetPath = path.join(cssDir, assetFileName).replace(/\\/g, '/');
                            const assetExists = Object.keys(bundle).some((bundleFileName) => {
                                const normalizedBundlePath = bundleFileName.replace(/\\/g, '/');
                                return (
                                    normalizedBundlePath === expectedAssetPath && bundle[bundleFileName]?.type === 'asset'
                                );
                            });

                            // Only replace if the asset exists in the same directory
                            if (assetExists) {
                                // Replace with relative path from CSS file to asset
                                // Preserve query params and hash from original assetPath
                                return `./${assetPath}`;
                            }

                            // Return original match if asset doesn't exist in same directory
                            return match;
                        },
                    );
                }
            }
        },
    };
}
