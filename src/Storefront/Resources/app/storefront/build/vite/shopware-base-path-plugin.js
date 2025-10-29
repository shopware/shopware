/**
 * @sw-package framework
 * 
 * Vite plugin to configure runtime base path for Shopware's theme directory structure.
 * This allows ES module chunks to be loaded from /theme/{hash}/js/storefront/
 */

export default function shopwareBasePathPlugin() {
    return {
        name: 'shopware-base-path',
        
        config() {
            return {
                experimental: {
                    renderBuiltUrl(filename) {
                        // Generate code that uses the runtime base path for chunk loading
                        return {
                            runtime: `(() => {
                                const base = (typeof window !== 'undefined' && window.__vite_plugin_config__ && window.__vite_plugin_config__.base) || '/';
                                const filename = ${JSON.stringify(filename)};
                                let normalizedFilename = filename.startsWith('/') ? filename.substring(1) : filename;
                                if (normalizedFilename.startsWith('js/') && base.match(/\\/js\\/?$/)) {
                                    normalizedFilename = normalizedFilename.substring(3);
                                }
                                const normalizedBase = base.endsWith('/') ? base : base + '/';
                                return normalizedBase + normalizedFilename;
                            })()`,
                        };
                    },
                },
            };
        },
    };
}

