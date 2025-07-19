import type { Plugin } from 'vite';

/**
 * @sw-package framework
 * @private
 *
 * This plugin is used to dynamically change the public path of the assets.
 */
export default function assetPathPlugin(bundleName = 'administration'): Plugin {
    return {
        name: 'shopware-vite-plugin-asset-path',
        renderChunk(code) {
            // The code is minified afterward, so we can look for the none minified version directly
            // This code could change with every minor version of vite but there is no way around this.
            if (code.includes(`const assetsURL = function(dep) { return "/bundles/${bundleName}/administration/"+dep };`)) {
                const modified = code.replace(
                    `const assetsURL = function(dep) { return "/bundles/${bundleName}/administration/"+dep }`,
                    // eslint-disable-next-line max-len
                    `const assetsURL = function(dep) { return window.__sw__.assetPath+"/bundles/${bundleName}/administration/"+dep }`,
                );

                return {
                    code: modified,
                    map: null,
                };
            }

            return null;
        },
    };
}
