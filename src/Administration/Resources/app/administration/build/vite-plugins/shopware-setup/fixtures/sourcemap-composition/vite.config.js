/**
 * @sw-package framework
 */

const { createRequire } = require('node:module');
const { pathToFileURL } = require('node:url');
const path = require('node:path');

const adminRoot = process.env.SHOPWARE_ADMIN_ROOT;
const root = __dirname;
const requireFromAdmin = createRequire(path.join(adminRoot, 'package.json'));
const { transformShopwareSetupSfc } = requireFromAdmin('./build/vue-setup-transform/index.js');

module.exports = (async () => {
    const { default: vue } = await import(pathToFileURL(requireFromAdmin.resolve('@vitejs/plugin-vue')).href);
    const setupPlugin = {
        name: 'shopware-setup-probe',
        enforce: 'pre',
        transform(code, id) {
            const filename = id.split('?')[0];

            if (!filename.endsWith('.vue')) {
                return null;
            }

            const result = transformShopwareSetupSfc(code, filename);

            return result ? { code: result.code, map: result.map } : null;
        },
    };

    return {
        root,
        logLevel: 'silent',
        plugins: [setupPlugin, vue()],
        build: {
            write: false,
            sourcemap: true,
            minify: false,
            rollupOptions: {
                input: path.join(root, 'src/Entry.js'),
                external: ['vue'],
            },
        },
    };
})();
