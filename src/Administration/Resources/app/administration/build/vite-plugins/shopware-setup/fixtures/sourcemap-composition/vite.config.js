/**
 * @sw-package framework
 */

const { createRequire } = require('node:module');
const { pathToFileURL } = require('node:url');
const path = require('node:path');

const adminRoot = process.env.SHOPWARE_ADMIN_ROOT;
const root = __dirname;
const requireFromAdmin = createRequire(path.join(adminRoot, 'package.json'));

module.exports = (async () => {
    const { default: vue } = await import(pathToFileURL(requireFromAdmin.resolve('@vitejs/plugin-vue')).href);

    // Exercise the SHIPPED plugin, not a copy: this fixture is the integration proof that the real
    // resolveId/load/generateBundle path composes sourcemaps correctly. jiti bridges the TypeScript
    // source the same way build/vue-setup-transform/index.js does for the transform itself.
    //
    // The build WRITES its output on purpose. Reading the in-memory result would only prove that the
    // remap mutated `chunk.map`, and the `.js.map` file is written from the emitted asset instead - so an
    // in-memory assertion passed while every shipped plugin sourcemap still named the virtual files.
    const { createJiti } = requireFromAdmin('jiti');
    const jiti = createJiti(__filename);
    const ShopwareSetupPlugin = jiti(path.join(adminRoot, 'build/vite-plugins/shopware-setup/index.ts')).default;

    return {
        root,
        logLevel: 'silent',
        plugins: [
            ShopwareSetupPlugin({ administrationRoot: adminRoot }),
            vue(),
        ],
        build: {
            write: true,
            outDir: path.join(root, 'dist'),
            emptyOutDir: true,
            sourcemap: true,
            minify: false,
            rollupOptions: {
                input: path.join(root, 'src/Entry.js'),
                external: ['vue'],
            },
        },
    };
})();
