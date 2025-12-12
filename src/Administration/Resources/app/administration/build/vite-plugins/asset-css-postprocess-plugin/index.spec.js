/**
 * @sw-package framework
 */
import stripAssetsFolderInCss from './index';

describe('build/vite-plugins/asset-css-postprocess-plugin', () => {
    it('exposes a named vite plugin with generateBundle hook', () => {
        expect(typeof stripAssetsFolderInCss).toBe('function');

        const plugin = stripAssetsFolderInCss('/bundles/test/assets/');

        expect(plugin.name).toBe('asset-css-postprocess-plugin');
        expect(typeof plugin.generateBundle).toBe('function');
    });

    it('rewrites matching asset urls inside css assets', () => {
        const plugin = stripAssetsFolderInCss('/bundles/test/assets/');
        const bundle = {
            'style.css': {
                type: 'asset',
                source: 'body{background:url(/bundles/test/assets/icons/foo.svg) font:url(/bundles/test/assets/fonts/bar.woff2?v=3.19);}',
            },
            'app.js': {
                type: 'chunk',
                code: 'console.log("noop")',
            },
        };

        plugin.generateBundle({}, bundle);

        expect(bundle['style.css'].source).toBe('body{background:url(./icons/foo.svg) font:url(./fonts/bar.woff2?v=3.19);}');
        expect(bundle['app.js'].code).toBe('console.log("noop")');
    });

    it('skips non-css filenames and non-string sources', () => {
        const plugin = stripAssetsFolderInCss('/bundles/test/assets/');
        const bufferSource = Buffer.from('binary');
        const bundle = {
            'fonts.woff2': {
                type: 'asset',
                source: 'url(/bundles/test/assets/fonts/bar.woff2)',
            },
            'style.css': {
                type: 'asset',
                source: bufferSource,
            },
        };

        plugin.generateBundle({}, bundle);

        expect(bundle['fonts.woff2'].source).toBe('url(/bundles/test/assets/fonts/bar.woff2)');
        expect(bundle['style.css'].source).toBe(bufferSource);
    });
});
