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

    it('rewrites matching asset urls inside css assets when assets exist in same directory', () => {
        const plugin = stripAssetsFolderInCss('/bundles/test/assets/');
        const bundle = {
            'style.css': {
                type: 'asset',
                source: 'body{background:url(/bundles/test/assets/icons/foo.svg) font:url(/bundles/test/assets/fonts/bar.woff2?v=3.19);}',
            },
            'icons/foo.svg': {
                type: 'asset',
                source: '<svg></svg>',
            },
            'fonts/bar.woff2': {
                type: 'asset',
                source: 'font-data',
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

    it('does not rewrite asset urls when assets do not exist in the same directory', () => {
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

        // URLs should remain unchanged because assets don't exist in bundle
        expect(bundle['style.css'].source).toBe(
            'body{background:url(/bundles/test/assets/icons/foo.svg) font:url(/bundles/test/assets/fonts/bar.woff2?v=3.19);}',
        );
        expect(bundle['app.js'].code).toBe('console.log("noop")');
    });

    it('does not rewrite asset urls when assets exist in a different directory', () => {
        const plugin = stripAssetsFolderInCss('/bundles/test/assets/');
        const bundle = {
            'style.css': {
                type: 'asset',
                source: 'body{background:url(/bundles/test/assets/icons/foo.svg);}',
            },
            'other-dir/icons/foo.svg': {
                type: 'asset',
                source: '<svg></svg>',
            },
            'app.js': {
                type: 'chunk',
                code: 'console.log("noop")',
            },
        };

        plugin.generateBundle({}, bundle);

        // URL should remain unchanged because asset is in different directory
        expect(bundle['style.css'].source).toBe('body{background:url(/bundles/test/assets/icons/foo.svg);}');
        expect(bundle['app.js'].code).toBe('console.log("noop")');
    });

    it('rewrites asset urls when CSS and assets are in a subdirectory', () => {
        const plugin = stripAssetsFolderInCss('/bundles/test/assets/');
        const bundle = {
            'css/styles.css': {
                type: 'asset',
                source: 'body{background:url(/bundles/test/assets/images/logo.png?v=1.0);}',
            },
            'css/images/logo.png': {
                type: 'asset',
                source: 'image-data',
            },
            'app.js': {
                type: 'chunk',
                code: 'console.log("noop")',
            },
        };

        plugin.generateBundle({}, bundle);

        // URL should be rewritten because asset exists in same directory (css/)
        expect(bundle['css/styles.css'].source).toBe('body{background:url(./images/logo.png?v=1.0);}');
        expect(bundle['app.js'].code).toBe('console.log("noop")');
    });

    it('preserves query params and hash when rewriting asset urls', () => {
        const plugin = stripAssetsFolderInCss('/bundles/test/assets/');
        const bundle = {
            'style.css': {
                type: 'asset',
                source: 'body{background:url(/bundles/test/assets/font.woff2?v=3.19#hash);}',
            },
            'font.woff2': {
                type: 'asset',
                source: 'font-data',
            },
        };

        plugin.generateBundle({}, bundle);

        // Query params and hash should be preserved
        expect(bundle['style.css'].source).toBe('body{background:url(./font.woff2?v=3.19#hash);}');
    });

    it('only rewrites urls for assets that exist, leaving others unchanged', () => {
        const plugin = stripAssetsFolderInCss('/bundles/test/assets/');
        const bundle = {
            'style.css': {
                type: 'asset',
                source: 'body{background:url(/bundles/test/assets/existing.svg) missing:url(/bundles/test/assets/missing.png);}',
            },
            'existing.svg': {
                type: 'asset',
                source: '<svg></svg>',
            },
        };

        plugin.generateBundle({}, bundle);

        // Only existing.svg should be rewritten, missing.png should remain unchanged
        expect(bundle['style.css'].source).toBe(
            'body{background:url(./existing.svg) missing:url(/bundles/test/assets/missing.png);}',
        );
    });

    it('rewrites asset urls with single quotes', () => {
        const plugin = stripAssetsFolderInCss('/bundles/test/assets/');
        const bundle = {
            'style.css': {
                type: 'asset',
                source: "body{background:url('/bundles/test/assets/icons/foo.svg') font:url('/bundles/test/assets/fonts/bar.woff2?v=3.19');}",
            },
            'icons/foo.svg': {
                type: 'asset',
                source: '<svg></svg>',
            },
            'fonts/bar.woff2': {
                type: 'asset',
                source: 'font-data',
            },
        };

        plugin.generateBundle({}, bundle);

        expect(bundle['style.css'].source).toBe(
            "body{background:url('./icons/foo.svg') font:url('./fonts/bar.woff2?v=3.19');}",
        );
    });

    it('rewrites asset urls with double quotes', () => {
        const plugin = stripAssetsFolderInCss('/bundles/test/assets/');
        const bundle = {
            'style.css': {
                type: 'asset',
                source: 'body{background:url("/bundles/test/assets/icons/foo.svg") font:url("/bundles/test/assets/fonts/bar.woff2?v=3.19");}',
            },
            'icons/foo.svg': {
                type: 'asset',
                source: '<svg></svg>',
            },
            'fonts/bar.woff2': {
                type: 'asset',
                source: 'font-data',
            },
        };

        plugin.generateBundle({}, bundle);

        expect(bundle['style.css'].source).toBe(
            'body{background:url("./icons/foo.svg") font:url("./fonts/bar.woff2?v=3.19");}',
        );
    });

    it('rewrites asset urls with mixed quote formats in same CSS', () => {
        const plugin = stripAssetsFolderInCss('/bundles/test/assets/');
        const bundle = {
            'style.css': {
                type: 'asset',
                source: 'body{background:url(/bundles/test/assets/unquoted.svg) single:url(\'/bundles/test/assets/single.svg\') double:url("/bundles/test/assets/double.svg");}',
            },
            'unquoted.svg': {
                type: 'asset',
                source: '<svg></svg>',
            },
            'single.svg': {
                type: 'asset',
                source: '<svg></svg>',
            },
            'double.svg': {
                type: 'asset',
                source: '<svg></svg>',
            },
        };

        plugin.generateBundle({}, bundle);

        expect(bundle['style.css'].source).toBe(
            'body{background:url(./unquoted.svg) single:url(\'./single.svg\') double:url("./double.svg");}',
        );
    });

    it('preserves query params and hash when rewriting quoted asset urls', () => {
        const plugin = stripAssetsFolderInCss('/bundles/test/assets/');
        const bundle = {
            'style.css': {
                type: 'asset',
                source: 'body{background:url(\'/bundles/test/assets/font.woff2?v=3.19#hash\') double:url("/bundles/test/assets/image.png?v=1.0#fragment");}',
            },
            'font.woff2': {
                type: 'asset',
                source: 'font-data',
            },
            'image.png': {
                type: 'asset',
                source: 'image-data',
            },
        };

        plugin.generateBundle({}, bundle);

        // Query params and hash should be preserved for both single and double quotes
        expect(bundle['style.css'].source).toBe(
            'body{background:url(\'./font.woff2?v=3.19#hash\') double:url("./image.png?v=1.0#fragment");}',
        );
    });

    it('does not rewrite quoted asset urls when assets do not exist', () => {
        const plugin = stripAssetsFolderInCss('/bundles/test/assets/');
        const bundle = {
            'style.css': {
                type: 'asset',
                source: 'body{background:url(\'/bundles/test/assets/missing.svg\') double:url("/bundles/test/assets/missing.png");}',
            },
            'app.js': {
                type: 'chunk',
                code: 'console.log("noop")',
            },
        };

        plugin.generateBundle({}, bundle);

        // URLs should remain unchanged because assets don't exist in bundle
        expect(bundle['style.css'].source).toBe(
            'body{background:url(\'/bundles/test/assets/missing.svg\') double:url("/bundles/test/assets/missing.png");}',
        );
        expect(bundle['app.js'].code).toBe('console.log("noop")');
    });
});
