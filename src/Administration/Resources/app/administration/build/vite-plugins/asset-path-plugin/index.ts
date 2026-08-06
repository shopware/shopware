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
        generateBundle(_options, bundle) {
            // Vite bakes Worker/SharedWorker script URLs as literal absolute strings, e.g.
            //   new SharedWorker("/bundles/administration/administration/assets/adminWorker-<hash>.js")
            // These bypass the assetsURL() helper patched above, so they never pick up
            // window.__sw__.assetPath and always resolve against the domain root. That breaks the
            // Admin Worker when the Administration is hosted under a base path / subdirectory.
            // Prefix them the same way assetsURL() is prefixed above. This runs on the final,
            // minified output because that is the only place the literal reliably matches.
            // Escape the bundle name so any regex metacharacters in it are matched literally.
            // RegExp.escape is only typed from TS 5.8 (lib esnext); the admin currently pins TS 5.7
            // (lib ES2023), so the suppressions below can be removed once TS is bumped.
            const workerUrlRegex = new RegExp(
                // @ts-expect-error - RegExp.escape is only typed from TS 5.8; admin pins TS 5.7 (lib ES2023)
                `(new\\s+(?:Shared)?Worker\\(\\s*)"(\\/bundles\\/${RegExp.escape(bundleName)}\\/administration\\/[^"]*)"`, // eslint-disable-line @typescript-eslint/no-unsafe-call
                'g',
            );

            for (const output of Object.values(bundle)) {
                if (output.type === 'chunk' && typeof output.code === 'string') {
                    output.code = output.code.replace(workerUrlRegex, '$1window.__sw__.assetPath+"$2"');
                }
            }
        },
    };
}
