/**
 * @sw-package framework
 */
import AssetPathPlugin from './index';

describe('build/vite-plugins/asset-path-plugin', () => {
    it('should be a function', () => {
        expect(typeof AssetPathPlugin).toBe('function');
    });

    it('should return plugin', async () => {
        const plugin = AssetPathPlugin('testbundle');

        // Identify plugin by name
        expect(plugin).toHaveProperty('name');
        expect(plugin.name).toBe('shopware-vite-plugin-asset-path');

        // Check if the plugin has a closeBundle method
        expect(plugin).toHaveProperty('renderChunk');
        expect(typeof plugin.renderChunk).toBe('function');

        // Test renderChunk method does not modify code if it does not contain the module preload function
        expect(plugin.renderChunk('import foo from "./bar";')).toBe(null);

        // Test renderChunk method modifies code if it contains the module preload function
        const code = 'const assetsURL = function(dep) { return "/bundles/testbundle/administration/"+dep };';
        const modified =
            'const assetsURL = function(dep) { return window.__sw__.assetPath+"/bundles/testbundle/administration/"+dep };';
        expect(plugin.renderChunk(code)).toEqual({ code: modified, map: null });
    });

    it('should provide a generateBundle hook', () => {
        const plugin = AssetPathPlugin('testbundle');

        expect(plugin).toHaveProperty('generateBundle');
        expect(typeof plugin.generateBundle).toBe('function');
    });

    it('should prefix literal SharedWorker script URLs with the asset path', () => {
        const plugin = AssetPathPlugin();

        const bundle = {
            'sw-admin-worker.js': {
                type: 'chunk',
                code: 'new SharedWorker("/bundles/administration/administration/assets/adminWorker-abc123.js")',
            },
        };

        plugin.generateBundle({}, bundle);

        expect(bundle['sw-admin-worker.js'].code).toBe(
            'new SharedWorker(window.__sw__.assetPath+"/bundles/administration/administration/assets/adminWorker-abc123.js")',
        );
    });

    it('should prefix literal Worker script URLs with the asset path', () => {
        const plugin = AssetPathPlugin();

        const bundle = {
            'worker.js': {
                type: 'chunk',
                code: 'new Worker("/bundles/administration/administration/assets/worker-def456.js")',
            },
        };

        plugin.generateBundle({}, bundle);

        expect(bundle['worker.js'].code).toBe(
            'new Worker(window.__sw__.assetPath+"/bundles/administration/administration/assets/worker-def456.js")',
        );
    });

    it('should respect a custom bundle name when prefixing Worker script URLs', () => {
        const plugin = AssetPathPlugin('testbundle');

        const bundle = {
            'worker.js': {
                type: 'chunk',
                code: 'new SharedWorker("/bundles/testbundle/administration/assets/worker-ghi789.js")',
            },
        };

        plugin.generateBundle({}, bundle);

        expect(bundle['worker.js'].code).toBe(
            'new SharedWorker(window.__sw__.assetPath+"/bundles/testbundle/administration/assets/worker-ghi789.js")',
        );
    });

    it('should treat regex metacharacters in the bundle name as literals', () => {
        // A bundle name containing regex-special characters must be escaped before being
        // embedded into the RegExp, otherwise it would match the wrong (or no) URLs.
        const plugin = AssetPathPlugin('my.plugin+name');

        const bundle = {
            'worker.js': {
                type: 'chunk',
                code: 'new SharedWorker("/bundles/my.plugin+name/administration/assets/worker-jkl012.js")',
            },
            // Same shape but a different bundle segment that a naive (unescaped) pattern
            // could accidentally match because `.` and `+` would be metacharacters.
            'other.js': {
                type: 'chunk',
                code: 'new SharedWorker("/bundles/myXpluginnname/administration/assets/worker-jkl012.js")',
            },
        };

        plugin.generateBundle({}, bundle);

        expect(bundle['worker.js'].code).toBe(
            'new SharedWorker(window.__sw__.assetPath+"/bundles/my.plugin+name/administration/assets/worker-jkl012.js")',
        );
        expect(bundle['other.js'].code).toBe(
            'new SharedWorker("/bundles/myXpluginnname/administration/assets/worker-jkl012.js")',
        );
    });

    it('should not modify unrelated code or non-chunk assets', () => {
        const plugin = AssetPathPlugin();

        const bundle = {
            'app.js': {
                type: 'chunk',
                code: 'const foo = new SharedWorker("/some/other/path/worker.js");',
            },
            'style.css': {
                type: 'asset',
                source: 'new SharedWorker("/bundles/administration/administration/assets/worker.js")',
            },
        };

        plugin.generateBundle({}, bundle);

        // A Worker URL outside the administration bundle path is left untouched.
        expect(bundle['app.js'].code).toBe('const foo = new SharedWorker("/some/other/path/worker.js");');
        // Non-chunk outputs (assets) are never touched.
        expect(bundle['style.css'].source).toBe(
            'new SharedWorker("/bundles/administration/administration/assets/worker.js")',
        );
    });
});
