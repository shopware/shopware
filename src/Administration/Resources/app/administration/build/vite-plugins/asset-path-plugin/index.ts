/**
 * @package admin
 *
 * This plugin is used to dynamically change the public path of the assets.
 */
export default function assetPathPlugin() {
    return {
        name: 'shopware-asset-path-plugin',
        renderChunk(code) {
            // The code is minified afterward, so we can look for the none minified version directly
            // This code could change with every minor version of vite but there is no way around this.
            if (code.includes('const assetsURL = function(dep) { return "/bundles/administration/"+dep };')) {
                const modified = code.replace(
                    'const assetsURL = function(dep) { return "/bundles/administration/"+dep }',
                    'const assetsURL = function(dep) { return window.__sw__.assetPath+"/bundles/administration/"+dep }',
                );

                return {
                    code: modified,
                    map: null
                }
            }

            return null;
        }
    };
}
